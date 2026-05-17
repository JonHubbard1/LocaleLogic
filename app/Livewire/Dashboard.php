<?php

namespace App\Livewire;

use App\Models\BoundaryImport;
use App\Models\DataVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * Boundary types that support automatic ArcGIS discovery and import.
     */
    public array $autoBoundaryTypes = [
        'region'           => ['label' => 'Region Boundaries',              'icon' => 'map'],
        'counties'         => ['label' => 'Counties and Unitary Authorities', 'icon' => 'building-office-2'],
        'lad'              => ['label' => 'Local Authority District Boundaries', 'icon' => 'building-office'],
        'wards'            => ['label' => 'Electoral Ward Boundaries',      'icon' => 'users'],
        'parishes'         => ['label' => 'Parish Boundaries',              'icon' => 'home'],
        'ced'              => ['label' => 'County Electoral Division Boundaries', 'icon' => 'document-text'],
        'constituencies'   => ['label' => 'Westminster Parliamentary Constituencies', 'icon' => 'academic-cap'],
        'police_force_areas' => ['label' => 'Police Force Area Boundaries', 'icon' => 'shield-check'],
    ];

    public array $lookupTypes = [
        'ward_hierarchy_lookup' => ['label' => 'Ward → LAD → County → CED', 'table' => 'ward_hierarchy_lookups'],
        'parish_lookup'         => ['label' => 'Parish → Ward → LAD',          'table' => 'parish_lookups'],
    ];

    public function render()
    {
        return view('livewire.dashboard');
    }

    // ─────────────────────────────────────────────────────────────
    //  ONSUD
    // ─────────────────────────────────────────────────────────────

    public int $onsudOptimisticStartedAt = 0;

    public function getOnsudStatus(): array
    {
        $current = DataVersion::where('dataset', 'ONSUD')
            ->whereIn('status', ['current', 'importing'])
            ->latest()
            ->first();

        if (! $current) {
            // Optimistic importing state: show importing for 120s after user clicked
            if ($this->onsudOptimisticStartedAt > 0 && (now()->timestamp - $this->onsudOptimisticStartedAt) < 120) {
                return [
                    'state'        => 'importing',
                    'epoch'        => null,
                    'release_date' => null,
                    'records'      => 0,
                    'progress'     => 0,
                    'message'      => 'Starting import...',
                ];
            }

            return ['state' => 'missing', 'epoch' => null, 'release_date' => null, 'records' => 0, 'message' => 'No ONSUD imported'];
        }

        if ($current->status === 'importing') {
            $this->onsudOptimisticStartedAt = 0; // Real state caught up, clear optimistic

            return [
                'state'        => 'importing',
                'id'           => $current->id,
                'epoch'        => $current->epoch,
                'release_date' => $current->release_date?->format('F Y'),
                'records'      => $current->record_count ?? 0,
                'progress'     => (float) $current->progress_percentage,
                'message'      => $current->status_message ?? 'Importing...',
            ];
        }

        // Real state caught up, clear optimistic
        $this->onsudOptimisticStartedAt = 0;

        // Check if a newer epoch exists on ArcGIS
        $latestAvailable = $this->getLatestAvailableOnsudEpoch();

        if ($latestAvailable && (int) $latestAvailable['epoch'] > (int) $current->epoch) {
            return [
                'state'         => 'outdated',
                'epoch'         => $current->epoch,
                'release_date'  => $current->release_date?->format('F Y'),
                'records'       => $current->record_count ?? 0,
                'newer_epoch'   => $latestAvailable['epoch'],
                'newer_date'    => $latestAvailable['release_date']?->format('F Y'),
                'message'       => "Epoch {$latestAvailable['epoch']} available",
            ];
        }

        return [
            'state'        => 'current',
            'epoch'        => $current->epoch,
            'release_date' => $current->release_date?->format('F Y'),
            'records'      => $current->record_count ?? 0,
            'message'      => 'Up to date',
        ];
    }

    private function getLatestAvailableOnsudEpoch(): ?array
    {
        return Cache::remember('dashboard.onsud.latest_epoch', 3600, function () {
            try {
                $response = Http::timeout(15)->get('https://www.arcgis.com/sharing/rest/search', [
                    'q'         => 'type:"CSV Collection" owner:ONSGeography_data ONSUD',
                    'sortField' => 'modified',
                    'sortOrder' => 'desc',
                    'num'       => 20,
                    'f'         => 'json',
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();

                foreach ($data['results'] ?? [] as $item) {
                    if (str_contains($item['title'], 'User Guide')) {
                        continue;
                    }
                    if ($item['type'] !== 'CSV Collection') {
                        continue;
                    }

                    $epoch = $this->parseEpochFromTitle($item['title']);
                    if (! $epoch) {
                        continue;
                    }

                    $releaseDate = $this->parseDateFromTitle($item['title']);

                    return [
                        'epoch'        => $epoch,
                        'release_date' => $releaseDate ? \Carbon\Carbon::parse($releaseDate) : null,
                        'title'        => $item['title'],
                    ];
                }

                return null;
            } catch (\Throwable $e) {
                Log::warning('ONSUD epoch discovery failed on dashboard', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function parseEpochFromTitle(string $title): ?int
    {
        if (preg_match('/Epoch\s+(\d+)/i', $title, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseDateFromTitle(string $title): ?string
    {
        if (preg_match('/\(([A-Za-z]+)\s+(\d{4})\)/', $title, $matches)) {
            $monthMap = [
                'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
                'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
                'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12',
            ];
            $month = $monthMap[$matches[1]] ?? null;
            if ($month) {
                return "{$matches[2]}-{$month}-01";
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Boundaries
    // ─────────────────────────────────────────────────────────────

    public function getBoundaryStatuses(): array
    {
        $statuses = [];

        foreach ($this->autoBoundaryTypes as $key => $meta) {
            $statuses[$key] = $this->computeBoundaryStatus($key, $meta);
        }

        return $statuses;
    }

    private function computeBoundaryStatus(string $type, array $meta): array
    {
        $latestImport = BoundaryImport::where('boundary_type', $type)
            ->where('data_type', 'polygons')
            ->latest()
            ->first();

        // Currently importing?
        if ($latestImport && in_array($latestImport->status, ['pending', 'processing'])) {
            return [
                'state'    => 'importing',
                'label'    => $meta['label'],
                'icon'     => $meta['icon'],
                'version'  => null,
                'records'  => $latestImport->records_processed ?? 0,
                'total'    => $latestImport->records_total ?? 0,
                'progress' => $latestImport->getProgressPercentage(),
                'message'  => $latestImport->status === 'pending' ? 'Queued' : 'Importing...',
            ];
        }

        // Failed?
        if ($latestImport && $latestImport->status === 'failed') {
            return [
                'state'   => 'failed',
                'label'   => $meta['label'],
                'icon'    => $meta['icon'],
                'version' => null,
                'records' => 0,
                'message' => $latestImport->error_message ?? 'Import failed',
            ];
        }

        // Check version in geometry table
        $geometry = DB::table('boundary_geometries')
            ->where('boundary_type', $type)
            ->orderBy('version_date', 'desc')
            ->first();

        if (! $geometry) {
            return [
                'state'   => 'missing',
                'label'   => $meta['label'],
                'icon'    => $meta['icon'],
                'version' => null,
                'records' => 0,
                'message' => 'Not imported',
            ];
        }

        $versionDate = $geometry->version_date;
        $recordCount = DB::table('boundary_geometries')->where('boundary_type', $type)->count();

        // Is it outdated based on schedule?
        $outdated = $this->isBoundaryOutdated($type, $versionDate);

        if ($outdated) {
            return [
                'state'   => 'outdated',
                'label'   => $meta['label'],
                'icon'    => $meta['icon'],
                'version' => \Carbon\Carbon::parse($versionDate)->format('F Y'),
                'records' => $recordCount,
                'message' => 'Update expected',
            ];
        }

        return [
            'state'   => 'current',
            'label'   => $meta['label'],
            'icon'    => $meta['icon'],
            'version' => \Carbon\Carbon::parse($versionDate)->format('F Y'),
            'records' => $recordCount,
            'message' => 'Up to date',
        ];
    }

    private function isBoundaryOutdated(string $type, string $currentVersion): bool
    {
        $schedule = [
            'wards'              => ['month' => 'December'],
            'parishes'           => ['month' => 'April'],
            'lad'                => ['month' => 'April'],
            'ced'                => ['month' => 'May'],
            'constituencies'     => ['month' => 'July'],
            'police_force_areas' => ['month' => 'December'],
            'region'             => ['month' => 'May'],
            'counties'           => ['month' => 'December'],
        ];

        if (! isset($schedule[$type])) {
            return false;
        }

        $monthMap = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
        ];

        $expectedMonthNum = $monthMap[$schedule[$type]['month']] ?? null;
        if (! $expectedMonthNum) {
            return false;
        }

        $currentYear  = now()->year;
        $currentMonth = now()->month;
        $expectedYear = ($currentMonth >= $expectedMonthNum) ? $currentYear : $currentYear - 1;
        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        // Has the user manually checked and confirmed no update?
        $manualCheck = DB::table('boundary_update_checks')
            ->where('boundary_type', $type)
            ->where('expected_version', $expectedVersion)
            ->first();

        if ($manualCheck) {
            return false;
        }

        try {
            return new \DateTime($expectedVersion) > new \DateTime($currentVersion);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Lookups
    // ─────────────────────────────────────────────────────────────

    public function getLookupStatuses(): array
    {
        $statuses = [];

        foreach ($this->lookupTypes as $key => $meta) {
            $statuses[$key] = $this->computeLookupStatus($key, $meta);
        }

        return $statuses;
    }

    private function computeLookupStatus(string $type, array $meta): array
    {
        $latestImport = BoundaryImport::where('boundary_type', $type)
            ->where('data_type', 'lookups')
            ->latest()
            ->first();

        // Currently importing?
        if ($latestImport && in_array($latestImport->status, ['pending', 'processing'])) {
            return [
                'state'    => 'importing',
                'label'    => $meta['label'],
                'version'  => null,
                'records'  => $latestImport->records_processed ?? 0,
                'total'    => $latestImport->records_total ?? 0,
                'progress' => $latestImport->getProgressPercentage(),
                'message'  => $latestImport->status === 'pending' ? 'Queued' : 'Importing...',
            ];
        }

        // Failed?
        if ($latestImport && $latestImport->status === 'failed') {
            return [
                'state'   => 'failed',
                'label'   => $meta['label'],
                'version' => null,
                'records' => 0,
                'message' => $latestImport->error_message ?? 'Import failed',
            ];
        }

        $recordCount = DB::table($meta['table'])->count();

        if ($recordCount === 0) {
            return [
                'state'   => 'missing',
                'label'   => $meta['label'],
                'version' => null,
                'records' => 0,
                'message' => 'Not imported',
            ];
        }

        // Extract version from filename if available
        $versionDate = null;
        $originalFilename = $latestImport?->metadata['original_filename'] ?? null;
        if ($originalFilename) {
            $versionDate = $this->extractOnsDateFromFilename($originalFilename);
        }

        // Check outdated
        $outdated = $this->isLookupOutdated($type, $versionDate);

        if ($outdated) {
            return [
                'state'   => 'outdated',
                'label'   => $meta['label'],
                'version' => $versionDate ? \Carbon\Carbon::parse($versionDate)->format('F Y') : 'Unknown',
                'records' => $recordCount,
                'message' => 'Update expected',
            ];
        }

        return [
            'state'   => 'current',
            'label'   => $meta['label'],
            'version' => $versionDate ? \Carbon\Carbon::parse($versionDate)->format('F Y') : 'Current',
            'records' => $recordCount,
            'message' => 'Up to date',
        ];
    }

    private function extractOnsDateFromFilename(string $filename): ?string
    {
        if (preg_match('/_\(([A-Za-z]+)_(\d{4})\)_/', $filename, $matches)) {
            $monthNum = $this->monthNameToNumber($matches[1]);
            if ($monthNum) {
                return "{$matches[2]}-{$monthNum}-01";
            }
        }

        if (preg_match('/_([A-Za-z]+)_(\d{4})_/', $filename, $matches)) {
            $monthNum = $this->monthNameToNumber($matches[1]);
            if ($monthNum) {
                return "{$matches[2]}-{$monthNum}-01";
            }
        }

        return null;
    }

    private function monthNameToNumber(string $month): ?string
    {
        $map = [
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12',
        ];

        return $map[ucfirst(strtolower($month))] ?? null;
    }

    private function isLookupOutdated(string $type, ?string $versionDate): bool
    {
        $schedule = [
            'ward_hierarchy_lookup' => ['month' => 'May'],
            'parish_lookup'         => ['month' => 'April'],
        ];

        if (! isset($schedule[$type]) || ! $versionDate) {
            return false;
        }

        $monthMap = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
        ];

        $expectedMonthNum = $monthMap[$schedule[$type]['month']] ?? null;
        if (! $expectedMonthNum) {
            return false;
        }

        $currentYear  = now()->year;
        $currentMonth = now()->month;
        $expectedYear = ($currentMonth >= $expectedMonthNum) ? $currentYear : $currentYear - 1;
        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        try {
            return new \DateTime($expectedVersion) > new \DateTime($versionDate);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Summary
    // ─────────────────────────────────────────────────────────────

    public function getSummary(): array
    {
        $all = [];

        // ONSUD
        $onsud = $this->getOnsudStatus();
        $all[] = $onsud['state'];

        // Boundaries
        foreach ($this->getBoundaryStatuses() as $status) {
            $all[] = $status['state'];
        }

        // Lookups
        foreach ($this->getLookupStatuses() as $status) {
            $all[] = $status['state'];
        }

        return [
            'total'     => count($all),
            'current'   => count(array_filter($all, fn ($s) => $s === 'current')),
            'outdated'  => count(array_filter($all, fn ($s) => $s === 'outdated')),
            'importing' => count(array_filter($all, fn ($s) => $s === 'importing')),
            'failed'    => count(array_filter($all, fn ($s) => $s === 'failed')),
            'missing'   => count(array_filter($all, fn ($s) => $s === 'missing')),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────────

    public function autoImportBoundary(string $type): void
    {
        if (! isset($this->autoBoundaryTypes[$type])) {
            return;
        }

        $existing = BoundaryImport::where('boundary_type', $type)
            ->whereIn('status', ['pending', 'processing'])
            ->where('data_type', 'polygons')
            ->first();

        if ($existing) {
            $this->dispatch('toast', message: 'An import is already running for this boundary type.');

            return;
        }

        try {
            \App\Jobs\AutoImportBoundary::dispatch($type, 'dashboard_auto');
            $this->dispatch('toast', message: 'Auto-import queued for ' . $this->autoBoundaryTypes[$type]['label'] . '.');
        } catch (\Throwable $e) {
            Log::error('Dashboard auto-import failed', ['type' => $type, 'error' => $e->getMessage()]);
            $this->dispatch('toast', message: 'Failed to queue import: ' . $e->getMessage());
        }
    }

    public function autoImportOnsud(): void
    {
        $existing = DataVersion::where('dataset', 'ONSUD')
            ->where('status', 'importing')
            ->first();

        if ($existing) {
            // If the record is a stale pre-created one (epoch=0, older than 10 min),
            // delete it so we can start fresh. Otherwise warn and bail.
            if ($existing->epoch === 0 && $existing->created_at->diffInMinutes(now()) > 10) {
                $existing->delete();
            } else {
                $this->dispatch('toast', message: 'An ONSUD import is already running.');

                return;
            }
        }

        // Set optimistic state immediately so the card flips to importing
        $this->onsudOptimisticStartedAt = now()->timestamp;

        try {
            $basePath   = base_path();
            $phpBinary  = '/usr/bin/php';
            $logFile    = storage_path('logs/onsud-import-' . date('Y-m-d-His') . '.log');
            $batchSize  = config('onsud.dev_batch_size', 1000);

            // Pre-create the DataVersion record with steps so the progress page
            // has something to track immediately
            $dataVersion = DataVersion::create([
                'dataset' => 'ONSUD',
                'epoch' => 0,
                'release_date' => now(),
                'status' => 'importing',
                'progress_percentage' => 0,
                'status_message' => 'Discovering latest release...',
                'current_file' => 0,
                'total_files' => 0,
                'log_file' => $logFile,
                'notes' => "Auto-discover started at " . now()->toDateTimeString(),
                'steps' => [
                    ['key' => 'discover',  'label' => 'Discover Latest Release',       'status' => 'active',    'progress' => 0,   'message' => 'Discovering...'],
                    ['key' => 'download',  'label' => 'Download ONSUD ZIP',           'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'extract',   'label' => 'Extract ZIP Archive',          'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'import',    'label' => 'Import CSVs to Staging',      'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'lookups',   'label' => 'Import Geography Lookups',     'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'validate',  'label' => 'Validate Staging Table',       'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'swap',      'label' => 'Swap Production Table',        'status' => 'pending',   'progress' => 0,   'message' => null],
                    ['key' => 'index',     'label' => 'Create Indexes',               'status' => 'pending',   'progress' => 0,   'message' => null],
                ],
                'current_step' => 'discover',
            ]);

            $command = sprintf(
                '%s %s/artisan onsud:import --auto-discover --batch-size=%s --log-file=%s --data-version-id=%s >> %s 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($basePath),
                escapeshellarg((string) $batchSize),
                escapeshellarg($logFile),
                escapeshellarg((string) $dataVersion->id),
                escapeshellarg($logFile)
            );

            exec($command);

            Log::info('Dashboard: ONSUD auto-discover started', [
                'command'         => $command,
                'log_file'        => $logFile,
                'data_version_id' => $dataVersion->id,
            ]);

            $this->dispatch('import-started', logFile: basename($logFile));
        } catch (\Throwable $e) {
            Log::error('Dashboard: ONSUD auto-import failed', ['error' => $e->getMessage()]);
            $this->dispatch('import-error', message: 'Failed to start ONSUD import: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Livewire\Admin;

use App\Jobs\AutoImportBoundary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Boundary & Geography Import')]
class BoundaryImport extends Component
{
    use WithFileUploads;

    #[Url]
    public string $boundaryType = '';
    public $file;
    public $downloadUrl = '';
    public $importing = false;
    public $useUrl = 0; // Use 0/1 to match radio button values

    /**
     * Boundary types that support automatic ArcGIS discovery and import.
     */
    public array $autoBoundaryTypes = [
        'region' => 'Region Boundaries',
        'counties' => 'Counties and Unitary Authorities',
        'lad' => 'Local Authority District Boundaries',
        'wards' => 'Electoral Ward Boundaries',
        'parishes' => 'Parish Boundaries',
        'ced' => 'County Electoral Division Boundaries',
        'constituencies' => 'Westminster Parliamentary Constituencies',
        'police_force_areas' => 'Police Force Area Boundaries',
    ];

    /**
     * All boundary types that can be imported.
     */
    public array $boundaryTypes = [
        // Auto-importable (from ArcGIS)
        'region' => 'Region Boundaries',
        'counties' => 'Counties and Unitary Authorities',
        'lad' => 'Local Authority District Boundaries',
        'wards' => 'Electoral Ward Boundaries',
        'parishes' => 'Parish Boundaries',
        'ced' => 'County Electoral Division Boundaries',
        'constituencies' => 'Westminster Parliamentary Constituencies',
        'police_force_areas' => 'Police Force Area Boundaries',

        // Optional/Additional
        'lpa' => 'Local Planning Authority Boundaries',
        'combined_authorities' => 'Combined Authorities',
        'scottish_constituencies' => 'Scottish Parliament Constituencies',
        'scottish_regions' => 'Scottish Parliament Regions',
        'senedd_constituencies' => 'Welsh Senedd Constituencies',
        'senedd_regions' => 'Welsh Senedd Regions',
    ];

    public array $boundaryDescriptions = [
        // Core Geography
        'region' => 'Government office regions of England',
        'counties' => 'Upper-tier local authorities (county councils)',
        'lad' => 'Local authority districts and unitary authorities',
        'wards' => 'Electoral wards for local council elections',
        'parishes' => 'Civil parish and community councils',
        'ced' => 'Divisions for county council elections',
        'constituencies' => 'UK Parliament constituencies (Westminster)',
        'police_force_areas' => 'Police force areas for England and Wales',

        // Optional/Additional
        'lpa' => 'Local planning authority areas',
        'combined_authorities' => 'Groups of local authorities with devolved powers',
        'scottish_constituencies' => 'Constituencies for Scottish Parliament elections',
        'scottish_regions' => 'Regional list areas for Scottish Parliament',
        'senedd_constituencies' => 'Constituencies for Welsh Senedd elections',
        'senedd_regions' => 'Regional list areas for Welsh Senedd',
    ];

    public array $onsPageUrls = [
        // Core Geography
        'region' => 'https://geoportal.statistics.gov.uk/search?q=Regions%20May%202025%20Boundaries',
        'counties' => 'https://geoportal.statistics.gov.uk/search?q=Counties%20December%202024%20Boundaries',
        'lad' => 'https://geoportal.statistics.gov.uk/search?q=Local%20Authority%20Districts%20April%202025%20Boundaries',
        'wards' => 'https://geoportal.statistics.gov.uk/search?q=Wards%20December%202024%20Boundaries',
        'parishes' => 'https://geoportal.statistics.gov.uk/search?q=Parishes%20April%202025%20Boundaries',
        'ced' => 'https://geoportal.statistics.gov.uk/search?q=County%20Electoral%20Divisions%20May%202025%20Boundaries',
        'constituencies' => 'https://geoportal.statistics.gov.uk/search?q=Westminster%20Parliamentary%20Constituencies%20July%202024%20Boundaries',
        'police_force_areas' => 'https://geoportal.statistics.gov.uk/search?q=Police%20Force%20Areas%20December%202023%20Boundaries',

        // Optional/Additional
        'lpa' => 'https://geoportal.statistics.gov.uk/search?q=Local%20Planning%20Authorities%20April%202023%20Boundaries',
        'combined_authorities' => 'https://geoportal.statistics.gov.uk/search?q=Combined%20Authorities%20May%202024%20Boundaries',
        'scottish_constituencies' => 'https://geoportal.statistics.gov.uk/search?q=Scottish%20Parliament%20Constituencies%20May%202011%20Boundaries',
        'scottish_regions' => 'https://geoportal.statistics.gov.uk/search?q=Scottish%20Parliament%20Regions%20May%202011%20Boundaries',
        'senedd_constituencies' => 'https://geoportal.statistics.gov.uk/search?q=Senedd%20Constituencies%20May%202024%20Boundaries',
        'senedd_regions' => 'https://geoportal.statistics.gov.uk/search?q=Senedd%20Regions%20May%202024%20Boundaries',
    ];

    public array $existingFiles = [
        'wards' => 'WD Ward names and codes UK as at 05_25.csv',
        'ced' => 'CED County Electoral Division names and codes EN as at 05_25.csv',
        'lad' => 'LAD Local Authority District names and codes UK as at 04_25.csv',
        'parish' => 'PARNCP Parish_Non_Civil Parish names and codes ew as at 04_25 v2.csv',
        'region' => 'RGN Region names and codes EN as at 05_25.csv',
    ];

    public function getBoundaryFileInfo($boundaryType)
    {
        $directory = 'boundaries/' . $boundaryType;

        if (!Storage::exists($directory)) {
            return null;
        }

        // Get all files in the directory
        $files = Storage::files($directory);

        if (empty($files)) {
            return null;
        }

        // Get the most recent file
        $file = collect($files)->sortByDesc(function ($file) {
            return Storage::lastModified($file);
        })->first();

        if (!$file) {
            return null;
        }

        return [
            'path' => $file,
            'date' => date('d M Y H:i', Storage::lastModified($file)),
            'size' => $this->formatFileSize(Storage::size($file)),
        ];
    }

    public function getImportStatus($boundaryType, $dataType)
    {
        $import = \App\Models\BoundaryImport::where('boundary_type', $boundaryType)
            ->where('data_type', $dataType)
            ->orderBy('created_at', 'desc')
            ->first();

        return $import;
    }

    public function getNameImportStatus($boundaryType)
    {
        // For lookup types (ward_hierarchy_lookup, parish_lookup), check for 'lookups' data_type
        if (str_ends_with($boundaryType, '_lookup')) {
            $lookupImport = $this->getImportStatus($boundaryType, 'lookups');
            if ($lookupImport) {
                return $lookupImport;
            }
        }

        // Check for polygon imports first (they include names)
        $polygonImport = $this->getImportStatus($boundaryType, 'polygons');
        if ($polygonImport && $polygonImport->status === 'completed') {
            return $polygonImport;
        }

        // Fall back to CSV name imports
        return $this->getImportStatus($boundaryType, 'names');
    }

    public function getOnsVersionDate($boundaryType): ?string
    {
        $geometry = \Illuminate\Support\Facades\DB::table('boundary_geometries')
            ->where('boundary_type', $boundaryType)
            ->orderBy('version_date', 'desc')
            ->first();

        return $geometry?->version_date;
    }

    /**
     * Get the latest lookup file date for manual-upload-only types.
     * Returns array with 'date' (YYYY-MM-01), 'source' (filename or upload date), and 'status'.
     */
    public function getLatestLookupFileDate(string $boundaryType): ?array
    {
        $import = \App\Models\BoundaryImport::where('boundary_type', $boundaryType)
            ->where('data_type', 'lookups')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $import) {
            return null;
        }

        $originalFilename = $import->metadata['original_filename'] ?? null;

        if ($originalFilename) {
            $date = $this->extractOnsDateFromFilename($originalFilename);
            if ($date) {
                return [
                    'date' => $date,
                    'source' => $originalFilename,
                    'status' => $import->status,
                ];
            }
        }

        return [
            'date' => $import->created_at->format('Y-m-d'),
            'source' => 'Uploaded ' . $import->created_at->format('d M Y'),
            'status' => $import->status,
        ];
    }

    /**
     * Extract an ONS release date (YYYY-MM-01) from a filename.
     * Handles patterns like _(May_2025)_ or _May_2025_ in filenames.
     */
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

    /**
     * Convert a full month name to a two-digit number.
     */
    private function monthNameToNumber(string $month): ?string
    {
        $map = [
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12',
        ];

        return $map[ucfirst(strtolower($month))] ?? null;
    }

    /**
     * Convert a month abbreviation to a two-digit number.
     */
    private function monthAbbreviationToNumber(string $month): ?string
    {
        $map = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
        ];

        return $map[strtoupper($month)] ?? null;
    }

    /**
     * Check if a manual lookup file may be outdated based on ONS release schedule.
     */
    public function isLookupOutdated(string $boundaryType): bool
    {
        $schedule = [
            'ward_hierarchy_lookup' => ['month' => 'May'],
            'parish_lookup' => ['month' => 'April'],
        ];

        if (! isset($schedule[$boundaryType])) {
            return false;
        }

        $fileInfo = $this->getLatestLookupFileDate($boundaryType);
        if (! $fileInfo || ! $fileInfo['date']) {
            return false;
        }

        $expectedMonthNum = (int) $this->monthNameToNumber($schedule[$boundaryType]['month']);
        if (! $expectedMonthNum) {
            return false;
        }

        $currentYear = now()->year;
        $currentMonth = now()->month;
        $expectedYear = ($currentMonth >= $expectedMonthNum) ? $currentYear : $currentYear - 1;
        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        try {
            return new \DateTime($expectedVersion) > new \DateTime($fileInfo['date']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a boundary type is outdated based on ONS update schedule
     */
    public function isOutdated($boundaryType): bool
    {
        // TODO: Tidy up outdated logic for boundary types where ONS publishes
        //       names/codes tables before boundary polygons (e.g. PFA Dec 2025).
        //       Currently police_force_areas shows as outdated because the schedule
        //       expects Dec 2025, but only a names-only FeatureServer exists; the
        //       latest polygon boundary is Dec 2024. Need to:
        //       1. Distinguish between "no newer polygon available" vs "not checked"
        //       2. Auto-update records when a genuine newer polygon release appears
        //       3. Consider using the ArcGIS item release date rather than a hardcoded schedule.
        // Update schedule for each boundary type (from CheckForBoundaryUpdates command)
        $updateSchedule = [
            'wards' => ['month' => 'December', 'frequency' => 'annual'],
            'parishes' => ['month' => 'April', 'frequency' => 'annual'],
            'lad' => ['month' => 'April', 'frequency' => 'annual'],
            'ced' => ['month' => 'May', 'frequency' => 'annual'],
            'constituencies' => ['month' => 'July', 'frequency' => 'varies'],
            'police_force_areas' => ['month' => 'December', 'frequency' => 'varies'],
            'region' => ['month' => 'May', 'frequency' => 'annual'],
            'counties' => ['month' => 'December', 'frequency' => 'annual'],
        ];

        // Get current version from database
        $currentVersion = $this->getOnsVersionDate($boundaryType);

        if (!$currentVersion || !isset($updateSchedule[$boundaryType])) {
            return false;
        }

        // Calculate expected latest version based on schedule
        $schedule = $updateSchedule[$boundaryType];
        $expectedMonth = $schedule['month'];
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $monthMap = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
        ];

        $expectedMonthNum = $monthMap[$expectedMonth] ?? null;

        if (!$expectedMonthNum) {
            return false;
        }

        // Determine expected version based on current date
        if ($currentMonth >= $expectedMonthNum) {
            $expectedYear = $currentYear;
        } else {
            $expectedYear = $currentYear - 1;
        }

        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        // Check if user has manually confirmed no update available for this expected version
        $manualCheck = \Illuminate\Support\Facades\DB::table('boundary_update_checks')
            ->where('boundary_type', $boundaryType)
            ->where('expected_version', $expectedVersion)
            ->first();

        if ($manualCheck) {
            // User has confirmed they checked and no update was available
            return false;
        }

        // Compare versions
        try {
            $currentDate = new \DateTime($currentVersion);
            $expectedDate = new \DateTime($expectedVersion);

            return $expectedDate > $currentDate;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark that user checked for update but none was available
     */
    public function markAsChecked($boundaryType): void
    {
        $updateSchedule = [
            'wards' => ['month' => 'December'],
            'parishes' => ['month' => 'April'],
            'lad' => ['month' => 'April'],
            'ced' => ['month' => 'May'],
            'constituencies' => ['month' => 'July'],
            'police_force_areas' => ['month' => 'December'],
            'region' => ['month' => 'May'],
            'counties' => ['month' => 'December'],
        ];

        if (!isset($updateSchedule[$boundaryType])) {
            return;
        }

        $schedule = $updateSchedule[$boundaryType];
        $monthMap = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
        ];

        $expectedMonthNum = $monthMap[$schedule['month']] ?? null;
        if (!$expectedMonthNum) {
            return;
        }

        $currentYear = now()->year;
        $currentMonth = now()->month;
        $expectedYear = ($currentMonth >= $expectedMonthNum) ? $currentYear : $currentYear - 1;
        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        \Illuminate\Support\Facades\DB::table('boundary_update_checks')->updateOrInsert(
            [
                'boundary_type' => $boundaryType,
                'expected_version' => $expectedVersion,
            ],
            [
                'checked_at' => now(),
                'checked_by' => auth()->user()?->name ?? 'System',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->dispatch('check-recorded', message: 'Recorded: No update available for ' . $this->boundaryTypes[$boundaryType]);
    }

    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function useExistingFile()
    {
        $this->validate([
            'boundaryType' => 'required|in:' . implode(',', array_keys($this->boundaryTypes)),
        ]);

        if (!isset($this->existingFiles[$this->boundaryType])) {
            $this->dispatch('import-error', message: 'No existing file available for this boundary type');
            return;
        }

        $this->importing = true;

        try {
            // Use existing ONSUD CSV file
            $filename = $this->existingFiles[$this->boundaryType];
            $path = 'onsud/' . $filename;

            if (!Storage::exists($path)) {
                throw new \Exception("ONSUD file not found: {$filename}");
            }

            // Dispatch import job
            \App\Jobs\ProcessBoundaryImport::dispatch($path, $this->boundaryType, 'onsud_csv');

            Log::info('Queued boundary import from ONSUD file', [
                'boundary_type' => $this->boundaryType,
                'file' => $filename,
            ]);

            $this->dispatch('import-success', message: 'Import queued from existing ONSUD file. Processing will begin shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to queue ONSUD import', [
                'error' => $e->getMessage(),
                'boundary_type' => $this->boundaryType,
            ]);
            $this->dispatch('import-error', message: 'Failed to queue import: ' . $e->getMessage());
        }

        $this->importing = false;
    }

    public function startImport()
    {
        Log::info('startImport called', [
            'boundaryType' => $this->boundaryType,
            'useUrl' => $this->useUrl,
            'downloadUrl' => $this->downloadUrl,
            'hasFile' => !is_null($this->file),
        ]);

        // Validate based on whether using URL or file upload
        if ($this->useUrl == 1) {
            $this->validate([
                'boundaryType' => 'required|in:' . implode(',', array_keys($this->boundaryTypes)),
                'downloadUrl' => 'required|url',
            ]);
        } else {
            $this->validate([
                'boundaryType' => 'required|in:' . implode(',', array_keys($this->boundaryTypes)),
                'file' => 'required|file|mimes:csv,json,geojson,zip|max:5242880', // 5GB max
            ]);
        }

        $this->importing = true;

        try {
            if ($this->useUrl == 1) {
                // Download file from URL
                Log::info('Starting boundary download from URL', [
                    'url' => $this->downloadUrl,
                    'boundary_type' => $this->boundaryType,
                ]);

                // Extract filename from URL first
                $urlParts = parse_url($this->downloadUrl);
                $baseFilename = basename($urlParts['path'] ?? 'boundary-download');
                $baseFilename = preg_replace('/\.(csv|json|geojson|zip)$/i', '', $baseFilename);

                // Default extension based on boundary type
                $extension = str_ends_with($this->boundaryType, '_lookup') ? 'csv' : 'geojson';
                $filename = $baseFilename . '.' . $extension;
                $path = 'boundaries/' . $this->boundaryType . '/' . $filename;

                // Create storage directory if it doesn't exist
                $storagePath = Storage::path('boundaries/' . $this->boundaryType);
                if (!is_dir($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }

                // Stream download directly to disk - doesn't load into memory
                $fullPath = Storage::path($path);
                $response = Http::withOptions([
                    'sink' => $fullPath,
                    'timeout' => 3600,
                    'connect_timeout' => 60,
                ])->get($this->downloadUrl);

                if (!$response->successful()) {
                    // Clean up partial file
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                    throw new \Exception('Failed to download file from URL. HTTP Status: ' . $response->status());
                }

                // Get file size for logging
                $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

                Log::info('Boundary file downloaded successfully via streaming', [
                    'path' => $path,
                    'size' => $fileSize,
                ]);

                // Dispatch import job
                Log::info('About to dispatch ProcessBoundaryImport job', [
                    'path' => $path,
                    'boundary_type' => $this->boundaryType,
                    'source' => 'url_download',
                ]);

                $metadata = [];
                $urlFilename = basename(parse_url($this->downloadUrl, PHP_URL_PATH) ?? '');
                if ($urlFilename) {
                    $metadata['original_filename'] = urldecode($urlFilename);
                }

                try {
                    \App\Jobs\ProcessBoundaryImport::dispatch($path, $this->boundaryType, 'url_download', $metadata);
                    Log::info('ProcessBoundaryImport job dispatched successfully');
                } catch (\Throwable $dispatchError) {
                    Log::error('Failed to dispatch ProcessBoundaryImport job', [
                        'error' => $dispatchError->getMessage(),
                        'trace' => $dispatchError->getTraceAsString(),
                    ]);
                    throw $dispatchError;
                }

                $this->dispatch('import-success', message: 'File downloaded successfully. Import processing has been queued and will begin shortly.');
            } else {
                // Store the uploaded file
                $path = $this->file->store('boundaries/' . $this->boundaryType, 'local');

                Log::info('Boundary file uploaded successfully', [
                    'path' => $path,
                    'boundary_type' => $this->boundaryType,
                ]);

                // Dispatch import job
                Log::info('About to dispatch ProcessBoundaryImport job', [
                    'path' => $path,
                    'boundary_type' => $this->boundaryType,
                    'source' => 'manual_upload',
                ]);

                try {
                    \App\Jobs\ProcessBoundaryImport::dispatch($path, $this->boundaryType, 'manual_upload');
                    Log::info('ProcessBoundaryImport job dispatched successfully');
                } catch (\Throwable $dispatchError) {
                    Log::error('Failed to dispatch ProcessBoundaryImport job', [
                        'error' => $dispatchError->getMessage(),
                        'trace' => $dispatchError->getTraceAsString(),
                    ]);
                    throw $dispatchError;
                }

                $this->dispatch('import-success', message: 'File uploaded successfully. Import processing has been queued and will begin shortly.');
            }

            $this->reset(['file', 'downloadUrl', 'importing']);
        } catch (\Exception $e) {
            Log::error('Boundary import failed', [
                'error' => $e->getMessage(),
                'boundary_type' => $this->boundaryType,
                'method' => $this->useUrl == 1 ? 'url' : 'upload',
            ]);
            $this->dispatch('import-error', message: 'Import failed: ' . $e->getMessage());
            $this->importing = false;
        }
    }

    public function processExistingFile($boundaryType)
    {
        try {
            // Get the most recent file for this boundary type
            $fileInfo = $this->getBoundaryFileInfo($boundaryType);

            if (!$fileInfo) {
                $this->dispatch('import-error', message: 'No file found for this boundary type');
                return;
            }

            Log::info('Manually processing existing boundary file', [
                'boundary_type' => $boundaryType,
                'file_path' => $fileInfo['path'],
            ]);

            // Dispatch import job
            Log::info('About to dispatch ProcessBoundaryImport job (manual reprocess)', [
                'path' => $fileInfo['path'],
                'boundary_type' => $boundaryType,
                'source' => 'manual_reprocess',
            ]);

            try {
                \App\Jobs\ProcessBoundaryImport::dispatch($fileInfo['path'], $boundaryType, 'manual_reprocess');
                Log::info('ProcessBoundaryImport job dispatched successfully (manual reprocess)');
            } catch (\Throwable $dispatchError) {
                Log::error('Failed to dispatch ProcessBoundaryImport job (manual reprocess)', [
                    'error' => $dispatchError->getMessage(),
                    'trace' => $dispatchError->getTraceAsString(),
                ]);
                throw $dispatchError;
            }

            $this->dispatch('import-success', message: 'Import processing has been queued and will begin shortly.');

        } catch (\Exception $e) {
            Log::error('Failed to process existing boundary file', [
                'error' => $e->getMessage(),
                'boundary_type' => $boundaryType,
            ]);
            $this->dispatch('import-error', message: 'Failed to queue import: ' . $e->getMessage());
        }
    }

    /**
     * Trigger automatic import of a boundary type from ArcGIS.
     */
    public function autoImport(string $boundaryType): void
    {
        if (! isset($this->autoBoundaryTypes[$boundaryType])) {
            $this->dispatch('import-error', message: 'Boundary type does not support automatic import');

            return;
        }

        // Check if an import is already running for this type
        $existingImport = \App\Models\BoundaryImport::where('boundary_type', $boundaryType)
            ->whereIn('status', ['pending', 'processing'])
            ->where('data_type', 'polygons')
            ->first();

        if ($existingImport) {
            $this->dispatch('import-error', message: 'An import is already running for ' . $this->boundaryTypes[$boundaryType]);

            return;
        }

        try {
            AutoImportBoundary::dispatch($boundaryType, 'manual_auto');

            Log::info('Queued auto boundary import', [
                'boundary_type' => $boundaryType,
                'triggered_by' => auth()->user()?->name ?? 'System',
            ]);

            $this->dispatch('import-success', message: 'Auto-import queued for ' . $this->boundaryTypes[$boundaryType] . '. Download will begin shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to queue auto boundary import', [
                'boundary_type' => $boundaryType,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('import-error', message: 'Failed to queue import: ' . $e->getMessage());
        }
    }

    /**
     * Trigger automatic import for all auto-importable boundaries.
     */
    public function autoImportAll(): void
    {
        $queued = 0;

        foreach (array_keys($this->autoBoundaryTypes) as $boundaryType) {
            $existingImport = \App\Models\BoundaryImport::where('boundary_type', $boundaryType)
                ->whereIn('status', ['pending', 'processing'])
                ->where('data_type', 'polygons')
                ->first();

            if ($existingImport) {
                continue;
            }

            try {
                AutoImportBoundary::dispatch($boundaryType, 'manual_auto_all');
                $queued++;
            } catch (\Exception $e) {
                Log::warning('Failed to queue auto import for boundary type', [
                    'boundary_type' => $boundaryType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($queued > 0) {
            $this->dispatch('import-success', message: "Queued auto-import for {$queued} boundary type(s).");
        } else {
            $this->dispatch('import-error', message: 'All boundary types are already importing or have no updates available.');
        }
    }

    /**
     * Check if a specific boundary type supports automatic import.
     */
    public function supportsAutoImport(string $boundaryType): bool
    {
        return isset($this->autoBoundaryTypes[$boundaryType]);
    }

    public function updatedFile(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Illuminate\Http\UploadedFile|null $value): void
    {
        Log::info('BoundaryImport file property updated', [
            'has_file' => ! is_null($value),
            'file_class' => $value ? get_class($value) : null,
            'file_name' => $value ? $value->getClientOriginalName() : null,
            'file_size' => $value ? $value->getSize() : null,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.boundary-import');
    }
}

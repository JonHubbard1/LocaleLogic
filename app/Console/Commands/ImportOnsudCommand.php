<?php

namespace App\Console\Commands;

use App\Models\DataVersion;
use App\Services\CoordinateConverter;
use App\Services\TableSwapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ImportOnsudCommand extends Command
{
    protected $signature = 'onsud:import
        {--epoch= : ONSUD epoch number (e.g., 114 for November 2024)}
        {--release-date= : Release date in YYYY-MM-DD format}
        {--file= : Path to local ONSUD CSV or ZIP file}
        {--batch-size=10000 : Number of records per batch}
        {--skip-download : Skip download step and use existing file}
        {--skip-indexes : Skip index creation after table swap}
        {--skip-swap : Import to staging only without swapping}
        {--force : Force import even if validation fails}
        {--cleanup-old : Drop old table immediately after successful swap}';

    protected $description = 'Import ONS UPRN Directory (ONSUD) data into properties table';

    private CoordinateConverter $coordinateConverter;
    private TableSwapService $tableSwapService;

    private array $stats = [
        'total_rows' => 0,
        'successful' => 0,
        'skipped' => 0,
        'errors' => 0,
        'coordinate_errors' => 0,
        'missing_required_fields' => 0,
    ];

    private ?DataVersion $dataVersion = null;

    public function __construct(CoordinateConverter $coordinateConverter, TableSwapService $tableSwapService)
    {
        parent::__construct();
        $this->coordinateConverter = $coordinateConverter;
        $this->tableSwapService = $tableSwapService;
    }

    public function handle(): int
    {
        try {
            // 1. Validate input options
            $epoch = $this->option('epoch');
            $releaseDate = $this->option('release-date');

            if (!$epoch || !$releaseDate) {
                $this->error("Both --epoch and --release-date are required");
                $this->info("Example: php artisan onsud:import --file=/path/to/onsud.zip --epoch=114 --release-date=2024-11-01");
                return 1;
            }

            // 2. Determine CSV file path
            $csvPath = $this->resolveCsvPath($epoch);

            if (!$csvPath || !File::exists($csvPath)) {
                $this->error("CSV file not found: " . ($csvPath ?? 'unknown'));
                return 1;
            }

            $this->info("Using CSV file: {$csvPath}");

            // 3. Create data version record
            $this->createDataVersion($csvPath, (int) $epoch, $releaseDate);

            // 4. Truncate staging table
            $this->truncateStagingTable();

            // 5. Import CSV to staging
            $this->importCsvToStaging($csvPath);

            // 6. Display statistics
            $this->displayStatistics();

            // 7. Perform table swap
            $this->performTableSwap($this->stats['successful']);

            // 8. Create indexes
            $this->createIndexes();

            // 9. Update data version to current
            $this->updateDataVersionStatus('current', 'Import completed successfully');

            $this->info("ONSUD import completed successfully!");
            Log::info('ONSUD Import Complete', ['stats' => $this->stats]);

            return 0;

        } catch (\Throwable $e) {
            $this->handleImportFailure($e);
            return 1;
        }
    }

    private function resolveCsvPath(string $epoch): ?string
    {
        $filePath = $this->option('file');

        if ($filePath) {
            // Handle ZIP files
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip') {
                $extractPath = $this->extractZip($filePath);
                return $this->findOnsudCsv($extractPath);
            }

            return $filePath;
        }

        if (!$this->option('skip-download')) {
            // Try to find existing extracted file
            $existingPath = storage_path("app/onsud/epoch-{$epoch}/extracted");
            if (File::exists($existingPath)) {
                return $this->findOnsudCsv($existingPath);
            }

            $this->warn("Automatic download not fully implemented.");
            $this->warn("Please download ONSUD epoch {$epoch} manually from:");
            $this->warn("https://www.data.gov.uk/ and search for 'ONSUD epoch {$epoch}'");
            $this->error("Use --file option to specify downloaded ZIP or CSV path");

            return null;
        }

        // Try to find existing file
        $existingPath = storage_path("app/onsud/epoch-{$epoch}/extracted");
        if (File::exists($existingPath)) {
            return $this->findOnsudCsv($existingPath);
        }

        return null;
    }

    private function extractZip(string $zipPath): string
    {
        $zip = new ZipArchive();
        $extractPath = dirname($zipPath) . '/extracted';

        if (!File::exists($extractPath)) {
            File::makeDirectory($extractPath, 0755, true);
        }

        $this->info("Extracting ZIP file...");

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->info("Extraction complete");
        } else {
            throw new \RuntimeException("Failed to open ZIP file: {$zipPath}");
        }

        return $extractPath;
    }

    private function findOnsudCsv(string $extractPath): string
    {
        $csvFiles = File::glob("{$extractPath}/*.csv");

        if (empty($csvFiles)) {
            throw new \RuntimeException("No CSV file found in: {$extractPath}");
        }

        if (count($csvFiles) > 1) {
            usort($csvFiles, fn($a, $b) => filesize($b) <=> filesize($a));
            $this->warn("Multiple CSV files found, using largest: " . basename($csvFiles[0]));
        }

        return $csvFiles[0];
    }

    private function validateCsvHeader(array $header): void
    {
        $requiredColumns = ['UPRN', 'PCDS', 'GRIDGB1E', 'GRIDGB1N', 'LAD25CD'];

        $missing = array_diff($requiredColumns, $header);

        if (!empty($missing)) {
            throw new \RuntimeException(
                "CSV header missing required columns: " . implode(', ', $missing)
            );
        }

        $this->info("CSV header validation passed");
        $this->line("Columns: " . implode(', ', array_slice($header, 0, 15)) . '...');
    }

    private function validateRequiredFields(array $data): bool
    {
        $requiredFields = ['UPRN', 'PCDS', 'GRIDGB1E', 'GRIDGB1N', 'LAD25CD'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $this->stats['missing_required_fields']++;
                return false;
            }
        }

        return true;
    }

    private function validateCoordinates(int $easting, int $northing): bool
    {
        return $easting >= 0 && $easting <= 700000
            && $northing >= 0 && $northing <= 1300000;
    }

    private function importCsvToStaging(string $csvPath): void
    {
        $file = fopen($csvPath, 'r');
        if (!$file) {
            throw new \RuntimeException("Failed to open CSV file: {$csvPath}");
        }

        $header = fgetcsv($file);
        $this->validateCsvHeader($header);

        // Count total rows
        $totalRows = 0;
        while (fgetcsv($file) !== false) {
            $totalRows++;
        }
        rewind($file);
        fgetcsv($file);

        $this->info("Processing {$totalRows} rows...");
        $this->stats['total_rows'] = $totalRows;

        $batchSize = (int) $this->option('batch-size');
        $batch = [];
        $progressBar = $this->output->createProgressBar($totalRows);

        try {
            while (($row = fgetcsv($file)) !== false) {
                $processedRow = $this->processRow($row, $header);

                if ($processedRow !== null) {
                    $batch[] = $processedRow;
                    $this->stats['successful']++;
                } else {
                    $this->stats['skipped']++;
                }

                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $batch = [];
                }

                $progressBar->advance();
            }

            if (!empty($batch)) {
                $this->insertBatch($batch);
            }

            $progressBar->finish();
            $this->newLine(2);

        } finally {
            fclose($file);
        }
    }

    private function processRow(array $row, array $header): ?array
    {
        $data = array_combine($header, $row);

        if (!$this->validateRequiredFields($data)) {
            return null;
        }

        $easting = (int) $data['GRIDGB1E'];
        $northing = (int) $data['GRIDGB1N'];

        if (!$this->validateCoordinates($easting, $northing)) {
            $this->stats['coordinate_errors']++;
            return null;
        }

        try {
            $wgs84 = $this->coordinateConverter->osGridToWgs84($easting, $northing);
        } catch (\InvalidArgumentException $e) {
            $this->stats['coordinate_errors']++;
            return null;
        }

        return [
            'uprn' => (int) $data['UPRN'],
            'pcds' => trim(strtoupper($data['PCDS'])),
            'gridgb1e' => $easting,
            'gridgb1n' => $northing,
            'lat' => $wgs84['lat'],
            'lng' => $wgs84['lng'],
            'wd25cd' => !empty($data['WD25CD']) ? $data['WD25CD'] : null,
            'ced25cd' => !empty($data['CED25CD']) ? $data['CED25CD'] : null,
            'parncp25cd' => !empty($data['PARNCP25CD']) ? $data['PARNCP25CD'] : null,
            'lad25cd' => $data['LAD25CD'],
            'pcon24cd' => !empty($data['PCON24CD']) ? $data['PCON24CD'] : null,
            'lsoa21cd' => !empty($data['LSOA21CD']) ? $data['LSOA21CD'] : null,
            'msoa21cd' => !empty($data['MSOA21CD']) ? $data['MSOA21CD'] : null,
            'rgn25cd' => !empty($data['RGN25CD']) ? $data['RGN25CD'] : null,
            'ruc21ind' => !empty($data['RUC21IND']) ? $data['RUC21IND'] : null,
            'pfa23cd' => !empty($data['PFA23CD']) ? $data['PFA23CD'] : null,
        ];
    }

    private function insertBatch(array $batch): void
    {
        try {
            DB::table('properties_staging')->insert($batch);
        } catch (\Exception $e) {
            $this->error("Batch insert failed: " . $e->getMessage());
            $this->stats['errors'] += count($batch);

            if ($this->option('force')) {
                $this->warn("Attempting individual inserts for failed batch...");
                foreach ($batch as $row) {
                    try {
                        DB::table('properties_staging')->insert($row);
                        $this->stats['successful']++;
                        $this->stats['errors']--;
                    } catch (\Exception $e2) {
                        // Keep as error
                    }
                }
            }
        }
    }

    private function truncateStagingTable(): void
    {
        $this->info("Truncating properties_staging table...");

        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        DB::table('properties_staging')->truncate();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->info("Staging table truncated successfully");
    }

    private function performTableSwap(int $expectedCount): void
    {
        if ($this->option('skip-swap')) {
            $this->warn("Skipping table swap (--skip-swap flag set)");
            return;
        }

        $this->info("Validating staging table before swap...");

        try {
            $validation = $this->tableSwapService->validateStagingTable($expectedCount);

            if (!$validation['valid']) {
                throw new \RuntimeException($validation['message']);
            }

            $this->info("Validation passed: {$validation['record_count']} records");

            $this->info("Performing atomic table swap...");
            $this->tableSwapService->swapPropertiesTable($expectedCount);

            $this->info("Table swap completed successfully!");

            $this->updateDataVersionStatus('current', 'Table swap successful');
            $this->archiveOldVersions();

            if ($this->option('cleanup-old')) {
                $this->info("Dropping old properties table...");
                $this->tableSwapService->dropOldTable();
                $this->info("Old table dropped");
            } else {
                $this->warn("Old table (properties_old) retained. Run with --cleanup-old to drop.");
            }

        } catch (\Exception $e) {
            $this->error("Table swap failed: " . $e->getMessage());
            $this->updateDataVersionStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    private function createIndexes(): void
    {
        if ($this->option('skip-indexes')) {
            $this->warn("Skipping index creation (--skip-indexes flag set)");
            $this->warn("Remember to create indexes manually later!");
            return;
        }

        if ($this->option('skip-swap')) {
            $this->warn("Skipping index creation (table not swapped)");
            return;
        }

        $this->info("Creating indexes on properties table...");
        $this->warn("This may take 30-60 minutes for large datasets...");

        $indexes = [
            ['columns' => ['pcds'], 'name' => 'idx_properties_pcds'],
            ['columns' => ['wd25cd'], 'name' => 'idx_properties_wd25cd'],
            ['columns' => ['ced25cd'], 'name' => 'idx_properties_ced25cd'],
            ['columns' => ['parncp25cd'], 'name' => 'idx_properties_parncp25cd'],
            ['columns' => ['lad25cd'], 'name' => 'idx_properties_lad25cd'],
            ['columns' => ['pcon24cd'], 'name' => 'idx_properties_pcon24cd'],
            ['columns' => ['rgn25cd'], 'name' => 'idx_properties_rgn25cd'],
            ['columns' => ['pfa23cd'], 'name' => 'idx_properties_pfa23cd'],
            ['columns' => ['parncp25cd', 'pcds'], 'name' => 'idx_properties_parish_postcode'],
            ['columns' => ['lad25cd', 'pcds'], 'name' => 'idx_properties_lad_postcode'],
            ['columns' => ['wd25cd', 'pcds'], 'name' => 'idx_properties_ward_postcode'],
        ];

        $progressBar = $this->output->createProgressBar(count($indexes));
        $progressBar->setFormat('Creating indexes: %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($indexes as $index) {
            $progressBar->setMessage($index['name']);

            try {
                $columns = implode(', ', $index['columns']);
                DB::statement("CREATE INDEX IF NOT EXISTS {$index['name']} ON properties ({$columns})");
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("Failed to create index {$index['name']}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Index creation completed!");
    }

    private function createDataVersion(string $csvPath, int $epoch, string $releaseDate): void
    {
        $this->dataVersion = DataVersion::create([
            'dataset' => 'ONSUD',
            'epoch' => $epoch,
            'release_date' => $releaseDate,
            'imported_at' => now(),
            'record_count' => null,
            'file_hash' => hash_file('sha256', $csvPath),
            'status' => 'importing',
            'notes' => "Import started at " . now()->toDateTimeString(),
        ]);

        $this->info("Created data version record (ID: {$this->dataVersion->id})");
        Log::info('ONSUD Import Started', [
            'epoch' => $epoch,
            'release_date' => $releaseDate,
            'csv_path' => $csvPath,
        ]);
    }

    private function updateDataVersionStatus(string $status, ?string $notes = null): void
    {
        if (!$this->dataVersion) {
            return;
        }

        $this->dataVersion->update([
            'status' => $status,
            'record_count' => $this->stats['successful'],
            'notes' => $notes ?? $this->dataVersion->notes,
        ]);

        $this->info("Updated data version status to: {$status}");
    }

    private function archiveOldVersions(): void
    {
        DataVersion::where('dataset', 'ONSUD')
            ->where('status', 'current')
            ->where('id', '!=', $this->dataVersion->id)
            ->update(['status' => 'archived']);

        $this->info("Archived previous ONSUD versions");
    }

    private function handleImportFailure(\Throwable $e): void
    {
        $this->error("Import failed: " . $e->getMessage());
        $this->line($e->getTraceAsString());

        $this->updateDataVersionStatus('failed', $e->getMessage());

        Log::error('ONSUD Import Failed', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'stats' => $this->stats,
            'data_version_id' => $this->dataVersion?->id,
        ]);

        $this->warn("Staging table may contain partial data.");
        $this->warn("Run: php artisan onsud:cleanup --staging to clear staging table");
    }

    private function displayStatistics(): void
    {
        $this->newLine();
        $this->info("Import Statistics:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rows', number_format($this->stats['total_rows'])],
                ['Successful', number_format($this->stats['successful'])],
                ['Skipped', number_format($this->stats['skipped'])],
                ['Errors', number_format($this->stats['errors'])],
                ['Coordinate Errors', number_format($this->stats['coordinate_errors'])],
                ['Missing Required Fields', number_format($this->stats['missing_required_fields'])],
            ]
        );

        $successRate = $this->stats['total_rows'] > 0
            ? ($this->stats['successful'] / $this->stats['total_rows']) * 100
            : 0;

        $this->info(sprintf("Success Rate: %.2f%%", $successRate));
    }
}

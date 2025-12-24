<?php

namespace App\Console\Commands;

use App\Models\DataVersion;
use App\Services\CoordinateConverter;
use App\Services\TableSwapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ImportOnsudCommand extends Command
{
    protected $signature = 'onsud:import
        {--epoch= : ONSUD epoch number (e.g., 114 for November 2024)}
        {--release-date= : Release date in YYYY-MM-DD format}
        {--file= : Path to local ONSUD CSV or ZIP file}
        {--url= : URL to download ONSUD ZIP file from ONS}
        {--batch-size=1000 : Number of records per batch}
        {--log-file= : Path to log file for progress tracking}
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

            // 2. Determine CSV file path(s)
            $csvPaths = $this->resolveCsvPath($epoch);

            if (!$csvPaths) {
                $this->error("CSV file not found");
                return 1;
            }

            // Handle both single file and multiple files
            $csvFiles = is_array($csvPaths) ? $csvPaths : [$csvPaths];

            // Validate all files exist
            foreach ($csvFiles as $csvPath) {
                if (!File::exists($csvPath)) {
                    $this->error("CSV file not found: {$csvPath}");
                    return 1;
                }
            }

            $fileCount = count($csvFiles);
            if ($fileCount === 1) {
                $this->info("Using CSV file: {$csvFiles[0]}");
            } else {
                $this->info("Found {$fileCount} CSV files to process:");
                foreach ($csvFiles as $i => $file) {
                    $this->line("  " . ($i + 1) . ". " . basename($file) . " (" . $this->formatFileSize(filesize($file)) . ")");
                }
            }

            // 3. Create data version record (use first file for metadata)
            $this->createDataVersion($csvFiles[0], (int) $epoch, $releaseDate);

            // Set total files and initialize file tracking
            $this->initializeFileTracking($csvFiles);

            // 4. Truncate staging table (once at the start)
            $this->truncateStagingTable();

            // 5. Import all CSV files to staging
            foreach ($csvFiles as $index => $csvFile) {
                if ($fileCount > 1) {
                    $this->newLine();
                    $this->info("Processing file " . ($index + 1) . " of {$fileCount}: " . basename($csvFile));
                    $this->dataVersion->update(['current_file' => $index + 1]);
                }

                // Mark file as processing
                $this->updateFileStatus($index, 'processing');

                $this->importCsvToStaging($csvFile, $index + 1, $fileCount);

                // Mark file as completed
                $this->updateFileStatus($index, 'completed');
            }

            // 6. Display and save statistics
            $this->displayStatistics();
            $this->updateStats();

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

    /**
     * Resolve CSV file path(s) - can return single file or array of files
     *
     * @return string|array|null Single CSV path, array of CSV paths, or null if not found
     */
    private function resolveCsvPath(string $epoch): string|array|null
    {
        $downloadUrl = $this->option('url');
        $filePath = $this->option('file');

        // Priority 1: Download from URL if provided
        if ($downloadUrl) {
            $this->info("Downloading from URL: {$downloadUrl}");
            $downloadedFile = $this->downloadFromUrl($downloadUrl, $epoch);

            if ($downloadedFile) {
                $extractPath = $this->extractZip($downloadedFile);
                return $this->findOnsudCsv($extractPath);
            }

            $this->error("Failed to download from URL");
            return null;
        }

        // Priority 2: Use local file if provided
        if ($filePath) {
            // Handle ZIP files (may contain multiple regional CSVs)
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip') {
                $extractPath = $this->extractZip($filePath);
                return $this->findOnsudCsv($extractPath);
            }

            // Single CSV file provided
            return $filePath;
        }

        // Priority 3: Try automatic download if not skipped
        if (!$this->option('skip-download')) {
            // Try to find existing extracted files
            $existingPath = storage_path("app/onsud/epoch-{$epoch}/extracted");
            if (File::exists($existingPath)) {
                $csvFiles = $this->findOnsudCsv($existingPath);
                if ($csvFiles) {
                    $this->info("Using existing extracted file(s)");
                    return $csvFiles;
                }
            }

            // Attempt automatic download
            $this->info("Attempting automatic download of ONSUD epoch {$epoch}...");
            $downloadedFile = $this->downloadOnsudFile($epoch);

            if ($downloadedFile) {
                return $downloadedFile;
            }

            // If automatic download fails, show manual download instructions
            $this->newLine();
            $this->line("<fg=yellow>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
            $this->error("  MANUAL DOWNLOAD REQUIRED");
            $this->line("<fg=yellow>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
            $this->newLine();

            $this->info("ONSUD epoch {$epoch} must be downloaded manually from the ONS Open Geography Portal.");
            $this->newLine();

            $this->line("<fg=cyan>Step 1:</> Visit the ONS Open Geography Portal");
            $this->line("  <fg=white>https://geoportal.statistics.gov.uk/</>");
            $this->newLine();

            $this->line("<fg=cyan>Step 2:</> Search for the dataset");
            $this->line("  Search for: <fg=white>\"ONSUD epoch {$epoch}\"</> or <fg=white>\"ONS UPRN Directory\"</>");
            $this->newLine();

            $this->line("<fg=cyan>Step 3:</> Download the CSV file");
            $this->line("  - Click on the dataset");
            $this->line("  - Click the <fg=white>Download</> button");
            $this->line("  - Select <fg=white>CSV</> format");
            $this->line("  - Wait for the file to be generated (this may take a few minutes)");
            $this->line("  - Download the generated file");
            $this->newLine();

            $this->line("<fg=cyan>Step 4:</> Upload to server");
            $this->line("  Upload the downloaded file to your server at:");
            $this->line("  <fg=white>" . storage_path("app/onsud/") . "</>");
            $this->newLine();

            $this->line("<fg=cyan>Step 5:</> Run import with --file option");
            $this->line("  <fg=white>php artisan onsud:import \\</>");
            $this->line("  <fg=white>  --file=/path/to/ONSUD_file.zip \\</>");
            $this->line("  <fg=white>  --epoch={$epoch} \\</>");
            $this->line("  <fg=white>  --release-date=YYYY-MM-DD</>");
            $this->newLine();

            $this->line("<fg=yellow>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
            $this->newLine();

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
        if ($this->dataVersion) {
            $this->updateProgress(2, "Extracting ZIP file: " . basename($zipPath));
        }

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->info("Extraction complete");
            if ($this->dataVersion) {
                $this->updateProgress(5, "Extraction complete, preparing to process CSV files");
            }
        } else {
            throw new \RuntimeException("Failed to open ZIP file: {$zipPath}");
        }

        return $extractPath;
    }

    /**
     * Find all ONSUD CSV files in the extraction path
     *
     * @return array|null Array of CSV file paths, or null if none found
     */
    private function findOnsudCsv(string $extractPath): ?array
    {
        // First try the root directory
        $csvFiles = File::glob("{$extractPath}/*.csv");

        // If not found in root, check Data subdirectory (common in ONS ZIP files)
        if (empty($csvFiles) && File::exists("{$extractPath}/Data")) {
            $csvFiles = File::glob("{$extractPath}/Data/*.csv");
        }

        // Also check for ONSUD-specific patterns in case there are other CSVs
        if (empty($csvFiles)) {
            $csvFiles = File::glob("{$extractPath}/**/ONSUD*.csv");
        }

        if (empty($csvFiles)) {
            // Return null instead of throwing exception - caller will handle
            return null;
        }

        // Sort by filename for consistent ordering
        sort($csvFiles);

        if (count($csvFiles) > 1) {
            $this->info("Found " . count($csvFiles) . " CSV files (ONSUD regional files)");
        }

        return $csvFiles;
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

    private function importCsvToStaging(string $csvPath, int $currentFile = 1, int $totalFiles = 1): void
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
        $this->stats['total_rows'] += $totalRows;

        $batchSize = (int) $this->option('batch-size');
        $batch = [];
        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%% %message%');

        $lastProgressUpdate = 0;
        $processedInFile = 0;

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

                $processedInFile++;
                $progressBar->advance();

                // Update progress every 5% or every 50,000 records
                $fileProgress = ($processedInFile / $totalRows) * 100;
                if ($fileProgress - $lastProgressUpdate >= 5 || $processedInFile % 50000 === 0) {
                    $overallProgress = (($currentFile - 1) / $totalFiles * 100) + ($fileProgress / $totalFiles);
                    $fileName = basename($csvPath);
                    $message = $totalFiles > 1
                        ? "Processing file {$currentFile}/{$totalFiles}: {$fileName} ({$processedInFile}/{$totalRows} records)"
                        : "Processing {$fileName} ({$processedInFile}/{$totalRows} records)";

                    $this->updateProgress($overallProgress, $message, $this->stats['successful']);
                    $this->updateFileStatus($currentFile - 1, 'processing', $processedInFile);
                    $this->updateStats();
                    $lastProgressUpdate = $fileProgress;
                }
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

            // Check if this is a PostgreSQL parameter limit error
            if (str_contains($e->getMessage(), 'number of parameters must be between 0 and 65535')) {
                $batchSize = count($batch);
                $columnsPerRow = 16; // Number of columns in properties table
                $totalParams = $batchSize * $columnsPerRow;

                throw new \RuntimeException(
                    "PostgreSQL parameter limit exceeded: Batch size of {$batchSize} records × {$columnsPerRow} columns = {$totalParams} parameters (limit: 65,535). " .
                    "Please reduce --batch-size to 1000 or lower and restart the import.",
                    0,
                    $e
                );
            }

            // For other errors, try individual inserts if --force flag is set
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
            } else {
                // Without --force flag, batch failures should stop the import
                throw new \RuntimeException(
                    "Batch insert failed. Use --force flag to attempt individual row inserts.",
                    0,
                    $e
                );
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
        $logFile = $this->option('log-file');

        // Use updateOrCreate to handle re-imports of the same epoch
        $this->dataVersion = DataVersion::updateOrCreate(
            [
                'dataset' => 'ONSUD',
                'epoch' => $epoch,
            ],
            [
                'release_date' => $releaseDate,
                'imported_at' => now(),
                'record_count' => null,
                'file_hash' => hash_file('sha256', $csvPath),
                'status' => 'importing',
                'progress_percentage' => 0,
                'status_message' => 'Starting import...',
                'current_file' => 1,
                'total_files' => 1,
                'log_file' => $logFile,
                'notes' => "Import started at " . now()->toDateTimeString(),
            ]
        );

        $action = $this->dataVersion->wasRecentlyCreated ? 'Created' : 'Updated existing';
        $this->info("{$action} data version record (ID: {$this->dataVersion->id})");

        Log::info('ONSUD Import Started', [
            'epoch' => $epoch,
            'release_date' => $releaseDate,
            'csv_path' => $csvPath,
            'log_file' => $logFile,
            'action' => $action,
        ]);
    }

    private function initializeFileTracking(array $csvFiles): void
    {
        $files = [];
        foreach ($csvFiles as $index => $csvPath) {
            $files[] = [
                'name' => basename($csvPath),
                'path' => $csvPath,
                'total' => null, // We'll update this as we process the file
                'processed' => 0,
                'status' => $index === 0 ? 'processing' : 'pending',
            ];
        }

        $this->dataVersion->update([
            'total_files' => count($csvFiles),
            'files' => $files,
        ]);

        $this->info("Initialized tracking for " . count($csvFiles) . " file(s)");
    }

    private function updateFileStatus(int $fileIndex, string $status, ?int $processed = null): void
    {
        if (!$this->dataVersion || !$this->dataVersion->files) {
            return;
        }

        $files = $this->dataVersion->files;
        if (isset($files[$fileIndex])) {
            $files[$fileIndex]['status'] = $status;
            if ($processed !== null) {
                $files[$fileIndex]['processed'] = $processed;
            }
            $this->dataVersion->update(['files' => $files]);
        }
    }

    private function updateStats(): void
    {
        if (!$this->dataVersion) {
            return;
        }

        $this->dataVersion->update([
            'stats' => $this->stats,
        ]);
    }

    private function updateProgress(float $percentage, string $message, ?int $currentRecords = null): void
    {
        if (!$this->dataVersion) {
            return;
        }

        $this->dataVersion->update([
            'progress_percentage' => min(100, $percentage),
            'status_message' => $message,
            'record_count' => $currentRecords ?? $this->dataVersion->record_count,
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

        // Update data version with failure details including progress info
        if ($this->dataVersion) {
            $this->dataVersion->update([
                'status' => 'failed',
                'record_count' => $this->stats['successful'],
                'status_message' => $e->getMessage(),
                'notes' => "Import failed: " . $e->getMessage(),
            ]);
        }

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

    /**
     * Download ONSUD file from a direct URL
     */
    private function downloadFromUrl(string $url, string $epoch): ?string
    {
        $destination = storage_path("app/onsud/epoch-{$epoch}-" . date('YmdHis') . '.zip');
        $destinationDir = dirname($destination);

        if (!File::exists($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
        }

        $this->info("Downloading ONSUD file...");
        $this->info("URL: {$url}");
        $this->info("Destination: {$destination}");

        try {
            // Use Laravel HTTP client with streaming for large files
            $response = Http::timeout(3600)->withOptions([
                'sink' => $destination,
                'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) {
                    if ($downloadTotal > 0) {
                        $percentage = round(($downloadedBytes / $downloadTotal) * 100, 1);
                        if ($downloadedBytes > 0 && $downloadedBytes % (10 * 1024 * 1024) === 0) {
                            // Update every 10MB
                            $this->info(sprintf(
                                "Downloaded: %s / %s (%s%%)",
                                $this->formatFileSize($downloadedBytes),
                                $this->formatFileSize($downloadTotal),
                                $percentage
                            ));
                        }
                    }
                },
            ])->get($url);

            if ($response->successful() && File::exists($destination)) {
                $size = filesize($destination);
                $this->info("Download complete! Size: " . $this->formatFileSize($size));
                return $destination;
            }

            $this->error("Download failed with status: " . $response->status());
            if (File::exists($destination)) {
                File::delete($destination);
            }
            return null;

        } catch (\Exception $e) {
            $this->error("Download failed: " . $e->getMessage());
            if (File::exists($destination)) {
                File::delete($destination);
            }
            return null;
        }
    }

    /**
     * Attempt to download ONSUD file
     *
     * Note: Automatic download is not currently supported for ONSUD.
     * The ONS Open Geography Portal uses ArcGIS Hub which doesn't provide
     * direct download URLs for large datasets (2-3GB+ files, 41M+ records).
     *
     * Users must download manually from the portal.
     */
    private function downloadOnsudFile(string $epoch): ?string
    {
        $this->newLine();
        $this->warn("Automatic download not available for ONSUD.");
        $this->warn("ONSUD files are very large (2-3GB) and must be downloaded manually.");
        $this->newLine();

        // Automatic download is not feasible because:
        // 1. ONS uses ArcGIS Hub which generates downloads on-demand via browser
        // 2. No static/direct download URLs exist for ONSUD ZIP files
        // 3. Portal requires JavaScript/interactive session to trigger export
        //
        // Future enhancement: Could use Selenium/Playwright for browser automation

        return null;
    }

    /**
     * Get possible ONSUD file patterns for an epoch
     */
    private function getOnsudFilePatterns(string $epoch): array
    {
        // Map epoch to approximate month/year
        // This is approximate - users may need to adjust
        $epochToDate = $this->epochToDateMapping();

        if (isset($epochToDate[$epoch])) {
            $date = $epochToDate[$epoch];
        } else {
            // Default to current date if unknown epoch
            $date = ['month' => date('M'), 'year' => date('y')];
        }

        $monthYear = strtoupper($date['month']) . $date['year'];
        $epocStr = str_pad($epoch, 3, '0', STR_PAD_LEFT);

        return [
            [
                'filename' => "ONSUD_{$monthYear}_EP{$epocStr}.zip",
                'url' => "https://www.arcgis.com/sharing/rest/content/items/ONSUDLatest/data",
            ],
            [
                'filename' => "ONSUD_EP{$epocStr}.zip",
                'url' => "https://geoportal.statistics.gov.uk/datasets/onsud-{$monthYear}-ep{$epocStr}/data",
            ],
        ];
    }

    /**
     * Format file size in human-readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Epoch to date mapping (approximate)
     */
    private function epochToDateMapping(): array
    {
        return [
            '114' => ['month' => 'NOV', 'year' => '24'],
            '115' => ['month' => 'FEB', 'year' => '25'],
            '116' => ['month' => 'MAY', 'year' => '25'],
            '117' => ['month' => 'AUG', 'year' => '25'],
            // Add more as needed
        ];
    }

    /**
     * Download a file from URL
     */
    private function downloadFile(string $url, string $destination): bool
    {
        $progressBar = null;

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: LocaleLogic-ONSUD-Importer/1.0',
                    'timeout' => 300, // 5 minutes
                ]
            ]);

            // Get file size for progress bar
            $headers = get_headers($url, 1, $context);
            $fileSize = isset($headers['Content-Length']) ? (int) $headers['Content-Length'] : 0;

            if ($fileSize > 0) {
                $progressBar = $this->output->createProgressBar($fileSize);
                $progressBar->setFormat('Downloading: %current%/%max% bytes [%bar%] %percent:3s%%');
                $progressBar->start();
            }

            $source = fopen($url, 'r', false, $context);
            if (!$source) {
                return false;
            }

            $dest = fopen($destination, 'w');
            if (!$dest) {
                fclose($source);
                return false;
            }

            $bytes = 0;
            while (!feof($source)) {
                $buffer = fread($source, 8192);
                fwrite($dest, $buffer);
                $bytes += strlen($buffer);

                if ($progressBar) {
                    $progressBar->setProgress($bytes);
                }
            }

            fclose($source);
            fclose($dest);

            if ($progressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            return File::exists($destination);

        } catch (\Exception $e) {
            if ($progressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            if (File::exists($destination)) {
                File::delete($destination);
            }

            throw $e;
        }
    }
}

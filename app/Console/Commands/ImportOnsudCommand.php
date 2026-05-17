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
        {--cleanup-old : Drop old table immediately after successful swap}
        {--auto-discover : Automatically discover and download the latest ONSUD release from ArcGIS}
        {--skip-if-current : Skip import if discovered epoch matches current DataVersion}
        {--postcode-filter= : Import only records where postcode starts with this prefix (dev mode, e.g. SN15)}
        {--lad-filter= : Import only records matching this LAD code (e.g. E06000054 for Wiltshire)}
        {--limit=0 : Maximum records to import (0 = no limit)}
        {--data-version-id= : Pre-created DataVersion ID to update (dashboard pre-creates record)}';

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
        'filtered_by_postcode' => 0,
    ];

    private ?DataVersion $dataVersion = null;

    private ?array $discoveredRelease = null;

    private array $columnMap = [];

    private array $cleanupPaths = [];

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
            $autoDiscover = $this->option('auto-discover');

            // If pre-created DataVersion exists, load it early so download/extract steps can update it
            $dataVersionId = $this->option('data-version-id');
            if ($dataVersionId) {
                $this->dataVersion = DataVersion::find($dataVersionId);
            }

            if ($autoDiscover) {
                $release = $this->discoverLatestOnsudRelease();
                if (! $release) {
                    $this->error('Could not discover latest ONSUD release from ArcGIS');

                    return 1;
                }

                $epoch = (string) $release['epoch'];
                $releaseDate = $release['release_date'];
                $this->discoveredRelease = $release;

                $this->info("Discovered ONSUD Epoch {$epoch} ({$releaseDate}) — {$release['title']}");

                // Mark discover step as completed
                $this->updateStep('discover', 'completed', 100, "Epoch {$epoch} discovered");

                if ($this->option('skip-if-current')) {
                    $current = DataVersion::where('dataset', 'ONSUD')
                        ->where('status', 'current')
                        ->first();
                    if ($current && (int) $current->epoch === (int) $epoch) {
                        $this->info("Epoch {$epoch} is already current. Skipping import.");

                        return 0;
                    }
                }
            } elseif (! $epoch || ! $releaseDate) {
                $this->error('Both --epoch and --release-date are required (or use --auto-discover)');
                $this->info('Example: php artisan onsud:import --auto-discover');
                $this->info('Example: php artisan onsud:import --file=/path/to/onsud.zip --epoch=123 --release-date=2025-12-01');

                return 1;
            }

            // 2. Apply automatic dev-mode filter for non-production environments
            // (Disabled: importing full dataset for testing)
            // $this->applyDevModeFilter();

            // 3. Determine CSV file path(s)
            $csvPaths = $this->resolveCsvPath((string) $epoch);

            if (!$csvPaths) {
                $this->error("CSV file not found");
                return 1;
            }

            // Handle both single file and multiple files
            $csvFiles = is_array($csvPaths) ? $csvPaths : [$csvPaths];
            $this->cleanupPaths = $csvFiles;

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

            // Mark import step as completed
            $this->updateStep('import', 'completed', 100, "Imported " . number_format($this->stats['successful']) . " records");

            // 6. Import geography lookup files from Documents folder
            $extractPath = count($csvFiles) > 0 ? dirname($csvFiles[0]) : null;
            if ($extractPath && basename($extractPath) === 'Data') {
                $extractPath = dirname($extractPath);
            }
            if ($extractPath && File::isDirectory($extractPath . '/Documents')) {
                $this->importLookupFiles($extractPath, (int) $epoch, $releaseDate);
            }

            // 7. Reconcile geography codes on staging table using spatial containment
            $this->reconcileGeographyCodes();

            // 8. Display and save statistics
            $this->displayStatistics();
            $this->updateStats();

            // 9. Validate staging table
            $this->updateStep('validate', 'active', 0, 'Validating staging table...');
            $this->info("Validating staging table before swap...");
            $validation = $this->tableSwapService->validateStagingTable($this->stats['successful']);
            if (! $validation['valid']) {
                throw new \RuntimeException($validation['message']);
            }
            $this->updateStep('validate', 'completed', 100, "Validation passed: " . number_format($validation['record_count']) . " records");

            // 8. Perform table swap
            $this->updateStep('swap', 'active', 0, 'Swapping production table...');
            $this->performTableSwap($this->stats['successful']);
            $this->updateStep('swap', 'completed', 100, 'Table swap successful');

            // 9. Create indexes
            $this->updateStep('index', 'active', 0, 'Creating indexes...');
            $this->createIndexes();
            $this->updateStep('index', 'completed', 100, 'Indexes created');

            // 10. Update data version to current
            $this->updateDataVersionStatus('current', 'Import completed successfully');

            $this->info("ONSUD import completed successfully!");
            Log::info('ONSUD Import Complete', ['stats' => $this->stats]);

            // Clean up extracted CSV files to save disk space
            $this->cleanupExtractedFiles();

            return 0;

        } catch (\Throwable $e) {
            // Mark current step as failed
            if ($this->dataVersion) {
                $currentStep = $this->dataVersion->current_step ?? 'import';
                $this->updateStep($currentStep, 'failed', null, $e->getMessage());
            }
            $this->handleImportFailure($e);
            return 1;
        }
    }

    /**
     * Automatically apply data-limiting filters when running in non-production environments.
     * Prevents accidental full 41M-record imports on dev/staging servers.
     */
    private function applyDevModeFilter(): void
    {
        $env = config('app.env');
        $isProduction = in_array($env, ['production', 'prod'], true);

        if ($isProduction) {
            return;
        }

        $hasExplicitFilter = $this->option('postcode-filter') || $this->option('lad-filter');
        $hasExplicitLimit = (int) $this->option('limit') > 0;

        if (! $hasExplicitFilter && ! $hasExplicitLimit) {
            $defaultPostcode = config('onsud.dev_postcode_filter');
            $defaultLimit = config('onsud.dev_record_limit');
            $defaultLad = config('onsud.dev_lad_filter');

            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->warn("  DEV MODE DETECTED (APP_ENV={$env})");
            $this->warn('  Automatically limiting import:');
            if ($defaultLad) {
                $this->warn("    LAD filter: {$defaultLad}");
            } elseif ($defaultPostcode) {
                $this->warn("    Postcode filter: {$defaultPostcode}");
            }
            $this->warn("    Record limit: {$defaultLimit}");
            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();

            // Set the input options directly so downstream code sees them
            if ($defaultLad) {
                $this->input->setOption('lad-filter', $defaultLad);
            } elseif ($defaultPostcode) {
                $this->input->setOption('postcode-filter', $defaultPostcode);
            }
            $this->input->setOption('limit', (string) $defaultLimit);

            Log::info('Auto-applied dev-mode ONSUD filter', [
                'env' => $env,
                'postcode_filter' => $defaultPostcode,
                'lad_filter' => $defaultLad,
                'record_limit' => $defaultLimit,
            ]);
        } else {
            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->warn("  DEV MODE DETECTED (APP_ENV={$env})");
            $this->warn('  Custom filter/limit already set — using your values:');
            if ($this->option('postcode-filter')) {
                $this->warn('    Postcode filter: ' . $this->option('postcode-filter'));
            }
            if ($this->option('lad-filter')) {
                $this->warn('    LAD filter: ' . $this->option('lad-filter'));
            }
            if ($hasExplicitLimit) {
                $this->warn('    Record limit: ' . $this->option('limit'));
            }
            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();
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
                $extractPath = $this->extractZip($downloadedFile);
                return $this->findOnsudCsv($extractPath);
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

        if (! File::exists($extractPath)) {
            File::makeDirectory($extractPath, 0755, true);
        }

        $this->info("Extracting ZIP file...");
        $this->updateStep('extract', 'active', 0, 'Extracting ZIP archive...');
        if ($this->dataVersion) {
            $this->updateProgress(2, "Extracting ZIP file: " . basename($zipPath));
        }

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->info("Extraction complete");
            $this->updateStep('extract', 'completed', 100, 'Extraction complete');
            if ($this->dataVersion) {
                $this->updateProgress(5, "Extraction complete, preparing to process CSV files");
            }

            // Delete ZIP after extraction to save disk space
            if (File::exists($zipPath)) {
                File::delete($zipPath);
                $this->info("Removed ZIP archive to free disk space");
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

    private function discoverColumns(array $header): void
    {
        $patterns = [
            'UPRN' => '/^UPRN$/i',
            'PCDS' => '/^PCDS$/i',
            'GRIDGB1E' => '/^GRIDGB1E$/i',
            'GRIDGB1N' => '/^GRIDGB1N$/i',
            'LAD' => '/^LAD\d{2}CD$/i',
            'WD' => '/^WD\d{2}CD$/i',
            'CED' => '/^CED\d{2}CD$/i',
            'PARNCP' => '/^PARNCP\d{2}CD$/i',
            'PCON' => '/^PCON\d{2}CD$/i',
            'LSOA' => '/^LSOA\d{2}CD$/i',
            'MSOA' => '/^MSOA\d{2}CD$/i',
            'RGN' => '/^RGN\d{2}CD$/i',
            'RUC' => '/^RUC\d{2}IND$/i',
            'PFA' => '/^PFA\d{2}CD$/i',
        ];

        $this->columnMap = [];
        foreach ($header as $fieldName) {
            foreach ($patterns as $key => $pattern) {
                if (! isset($this->columnMap[$key]) && preg_match($pattern, $fieldName)) {
                    $this->columnMap[$key] = $fieldName;
                }
            }
        }

        $required = ['UPRN', 'PCDS', 'GRIDGB1E', 'GRIDGB1N', 'LAD'];
        $missing = array_filter($required, fn (string $key) => ! isset($this->columnMap[$key]));

        if (! empty($missing)) {
            throw new \RuntimeException(
                'CSV header missing required columns. Found: ' . implode(', ', $this->columnMap) .
                ' | Missing patterns: ' . implode(', ', $missing)
            );
        }

        Log::info('Discovered ONSUD columns from header', $this->columnMap);
        $this->info('CSV header validation passed');
        $this->line('Columns: ' . implode(', ', array_slice($header, 0, 15)) . '...');
    }

    private function col(array $data, string $key): ?string
    {
        $field = $this->columnMap[$key] ?? null;

        return $field ? ($data[$field] ?? null) : null;
    }

    private function validateRequiredFields(array $data): bool
    {
        $required = ['UPRN', 'PCDS', 'GRIDGB1E', 'GRIDGB1N', 'LAD'];

        foreach ($required as $key) {
            $value = $this->col($data, $key);
            if (empty($value)) {
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
        if (! $file) {
            throw new \RuntimeException("Failed to open CSV file: {$csvPath}");
        }

        $header = fgetcsv($file);
        $this->discoverColumns($header);

        $postcodeFilter = $this->option('postcode-filter');
        $limit = (int) $this->option('limit');

        // Count total rows (respecting filter if applied)
        $totalRows = 0;
        while (($row = fgetcsv($file)) !== false) {
            if ($postcodeFilter) {
                $data = array_combine($header, $row);
                $postcode = strtoupper($data[$this->columnMap['PCDS']] ?? '');
                if (! str_starts_with($postcode, strtoupper($postcodeFilter))) {
                    continue;
                }
            }
            $totalRows++;
        }
        rewind($file);
        fgetcsv($file);

        $this->info("Processing {$totalRows} rows" . ($postcodeFilter ? " (filtered by postcode: {$postcodeFilter})" : '') . '...');
        $this->stats['total_rows'] += $totalRows;

        // Store total row count on the file record for progress tracking
        $files = $this->dataVersion->files ?? [];
        if (isset($files[$currentFile - 1])) {
            $files[$currentFile - 1]['total'] = $totalRows;
            $this->dataVersion->update(['files' => $files]);
        }

        // Immediately update UI so the user knows scanning finished and import started
        $fileName = basename($csvPath);
        $this->updateStep('import', 'active', 0, "Scan complete — {$fileName}: {$totalRows} matching rows");

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

                // Stop if limit reached
                if ($limit > 0 && $this->stats['successful'] >= $limit) {
                    $this->info("\nReached limit of {$limit} records. Stopping.");
                    break;
                }

                // Update progress frequently: every 1% for small files (< 100k rows),
                // every 5% or 50k rows for large files
                if ($totalRows > 0) {
                    $fileProgress = ($processedInFile / $totalRows) * 100;
                    $threshold = $totalRows < 100_000 ? 1 : 5;
                    $rowInterval = $totalRows < 100_000 ? 1_000 : 50_000;

                    if ($fileProgress - $lastProgressUpdate >= $threshold || $processedInFile % $rowInterval === 0) {
                        $overallProgress = (($currentFile - 1) / $totalFiles * 100) + ($fileProgress / $totalFiles);
                        $message = $totalFiles > 1
                            ? "Processing file {$currentFile}/{$totalFiles}: {$fileName} ({$processedInFile}/{$totalRows} records)"
                            : "Processing {$fileName} ({$processedInFile}/{$totalRows} records)";

                        $this->updateProgress($overallProgress, $message, $this->stats['successful']);
                        $this->updateFileStatus($currentFile - 1, 'processing', $processedInFile);
                        $this->updateStats();
                        $lastProgressUpdate = $fileProgress;
                    }
                }
            }

            if (! empty($batch)) {
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

        $postcodeFilter = $this->option('postcode-filter');
        if ($postcodeFilter) {
            $postcode = strtoupper($this->col($data, 'PCDS') ?? '');
            if (! str_starts_with($postcode, strtoupper($postcodeFilter))) {
                $this->stats['filtered_by_postcode']++;

                return null;
            }
        }

        $ladFilter = $this->option('lad-filter');
        if ($ladFilter) {
            $ladCode = $this->col($data, 'LAD');
            if (empty($ladCode) || strtoupper($ladCode) !== strtoupper($ladFilter)) {
                $this->stats['filtered_by_postcode']++;

                return null;
            }
        }

        if (! $this->validateRequiredFields($data)) {
            return null;
        }

        $easting = (int) $this->col($data, 'GRIDGB1E');
        $northing = (int) $this->col($data, 'GRIDGB1N');

        if (! $this->validateCoordinates($easting, $northing)) {
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
            'uprn' => (int) $this->col($data, 'UPRN'),
            'pcds' => trim(strtoupper($this->col($data, 'PCDS') ?? '')),
            'gridgb1e' => $easting,
            'gridgb1n' => $northing,
            'lat' => $wgs84['lat'],
            'lng' => $wgs84['lng'],
            'wd25cd' => $this->col($data, 'WD') ?: null,
            'ced25cd' => $this->col($data, 'CED') ?: null,
            'parncp25cd' => $this->col($data, 'PARNCP') ?: null,
            'lad25cd' => $this->col($data, 'LAD'),
            'pcon24cd' => $this->col($data, 'PCON') ?: null,
            'lsoa21cd' => $this->col($data, 'LSOA') ?: null,
            'msoa21cd' => $this->col($data, 'MSOA') ?: null,
            'rgn25cd' => $this->col($data, 'RGN') ?: null,
            'ruc21ind' => $this->col($data, 'RUC') ?: null,
            'pfa23cd' => $this->col($data, 'PFA') ?: null,
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

        // Handle spatial index: after swap, the staging spatial index was renamed
        // with the table.  Rename it to the canonical name, or create if missing.
        $this->info('Ensuring spatial index on properties...');
        try {
            $hasStagingGeom = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE indexname = 'idx_properties_staging_geom'"
            );
            $hasLiveGeom = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE indexname = 'idx_properties_geom'"
            );

            if ($hasStagingGeom && ! $hasLiveGeom) {
                DB::statement(
                    "ALTER INDEX idx_properties_staging_geom RENAME TO idx_properties_geom"
                );
                $this->info('Renamed staging spatial index.');
            } elseif (! $hasLiveGeom) {
                $original = DB::selectOne("SHOW maintenance_work_mem")->maintenance_work_mem;
                DB::statement("SET maintenance_work_mem = '512MB'");
                DB::statement(
                    'CREATE INDEX idx_properties_geom ON properties USING GIST (ST_SetSRID(ST_MakePoint(lng, lat), 4326))'
                );
                DB::statement("SET maintenance_work_mem = '{$original}'");
                $this->info('Spatial index created.');
            } else {
                $this->info('Spatial index already exists.');
            }
        } catch (\Exception $e) {
            $this->error('Failed to ensure spatial index: ' . $e->getMessage());
        }

        $this->info("Index creation completed!");
    }

    /**
     * Reconcile geography code columns on properties_staging by spatial
     * point-in-polygon lookup against the live boundary_geometries table.
     * This corrects ONSUD assignment errors where properties near boundary
     * edges were given the wrong ward / parish / CED / constituency / etc.
     */
    private function reconcileGeographyCodes(): void
    {
        $this->updateStep('reconcile', 'active', 0, 'Reconciling geography codes...');
        $this->info('Starting geography code reconciliation on staging table...');
        $this->info('This uses PostGIS spatial containment against boundary polygons.');

        // Ensure spatial index exists on staging table for fast queries
        $this->createStagingSpatialIndex();

        $totalCorrected = 0;

        // Map: boundary_type in boundary_geometries => column in properties_staging
        $reconcilers = [
            ['type' => 'wards',              'column' => 'wd25cd',      'label' => 'Ward'],
            ['type' => 'parishes',           'column' => 'parncp25cd',  'label' => 'Parish'],
            ['type' => 'ced',                'column' => 'ced25cd',     'label' => 'County Electoral Division'],
            ['type' => 'constituencies',     'column' => 'pcon24cd',    'label' => 'Parliamentary Constituency'],
            ['type' => 'region',             'column' => 'rgn25cd',     'label' => 'Region'],
            ['type' => 'police_force_areas', 'column' => 'pfa23cd',     'label' => 'Police Force Area'],
            ['type' => 'lad',                'column' => 'lad25cd',     'label' => 'Local Authority'],
        ];

        $totalTypes = count($reconcilers);
        foreach ($reconcilers as $index => $rec) {
            $step = $index + 1;
            $type = $rec['type'];
            $column = $rec['column'];
            $label = $rec['label'];

            $this->info("[{$step}/{$totalTypes}] Reconciling {$label} codes ({$type})...");

            // Check whether we have polygons for this boundary type
            $polygonCount = DB::table('boundary_geometries')
                ->where('boundary_type', $type)
                ->whereNotNull('geom')
                ->count();

            if ($polygonCount === 0) {
                $this->warn("  No polygons found for {$type}, skipping.");
                continue;
            }

            $this->info("  {$polygonCount} polygons available.");

            $start = microtime(true);

            // Run the spatial update.  When a point falls on a boundary edge
            // and intersects multiple polygons, we pick the largest polygon
            // by area as the definitive match.
            $affected = DB::affectingStatement(
                "WITH matches AS (
                    SELECT DISTINCT ON (p.uprn) p.uprn, bg.gss_code
                    FROM properties_staging p
                    JOIN boundary_geometries bg
                        ON bg.boundary_type = ?
                        AND bg.geom IS NOT NULL
                        AND ST_Intersects(
                            ST_SetSRID(ST_MakePoint(p.lng, p.lat), 4326),
                            bg.geom
                        )
                    WHERE p.lat IS NOT NULL
                      AND p.lng IS NOT NULL
                    ORDER BY p.uprn, ST_Area(bg.geom::geography) DESC
                )
                UPDATE properties_staging p
                SET {$column} = m.gss_code
                FROM matches m
                WHERE p.uprn = m.uprn
                  AND p.{$column} IS DISTINCT FROM m.gss_code",
                [$type]
            );

            $elapsed = round(microtime(true) - $start, 1);
            $this->info("  {$affected} rows corrected in {$elapsed}s");
            $totalCorrected += $affected;

            $progress = round(($step / $totalTypes) * 100, 1);
            $this->updateStep('reconcile', 'active', $progress, "{$label}: {$affected} corrected ({$step}/{$totalTypes})");
        }

        $this->updateStep('reconcile', 'completed', 100, number_format($totalCorrected) . ' rows corrected across all types');
        $this->info('Geography code reconciliation complete: ' . number_format($totalCorrected) . ' rows corrected.');
    }

    /**
     * Create a GIST spatial index on properties_staging for fast
     * point-in-polygon reconciliation queries.
     */
    private function createStagingSpatialIndex(): void
    {
        $this->info('Ensuring spatial index on properties_staging...');

        $hasIndex = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'idx_properties_staging_geom'"
        );

        if ($hasIndex) {
            $this->info('  Spatial index already exists.');
            return;
        }

        $original = DB::selectOne("SHOW maintenance_work_mem")->maintenance_work_mem;
        DB::statement("SET maintenance_work_mem = '512MB'");

        DB::statement(
            'CREATE INDEX idx_properties_staging_geom ON properties_staging USING GIST (ST_SetSRID(ST_MakePoint(lng, lat), 4326))'
        );

        DB::statement("SET maintenance_work_mem = '{$original}'");
        $this->info('  Spatial index created.');
    }

    private function createDataVersion(string $csvPath, int $epoch, string $releaseDate): void
    {
        $logFile = $this->option('log-file');
        $dataVersionId = $this->option('data-version-id');

        $steps = [
            ['key' => 'discover',  'label' => 'Discover Latest Release',       'status' => 'completed', 'progress' => 100, 'message' => "Epoch {$epoch} discovered"],
            ['key' => 'download',  'label' => 'Download ONSUD ZIP',           'status' => 'completed', 'progress' => 100, 'message' => 'Download complete'],
            ['key' => 'extract',   'label' => 'Extract ZIP Archive',          'status' => 'completed', 'progress' => 100, 'message' => 'Extraction complete'],
            ['key' => 'import',    'label' => 'Import CSVs to Staging',      'status' => 'active',    'progress' => 0,   'message' => 'Starting import...'],
            ['key' => 'lookups',   'label' => 'Import Geography Lookups',     'status' => 'pending',   'progress' => 0,   'message' => null],
            ['key' => 'reconcile', 'label' => 'Reconcile Geography Codes',    'status' => 'pending',   'progress' => 0,   'message' => null],
            ['key' => 'validate',  'label' => 'Validate Staging Table',       'status' => 'pending',   'progress' => 0,   'message' => null],
            ['key' => 'swap',      'label' => 'Swap Production Table',        'status' => 'pending',   'progress' => 0,   'message' => null],
            ['key' => 'index',     'label' => 'Create Indexes',               'status' => 'pending',   'progress' => 0,   'message' => null],
        ];

        if ($dataVersionId) {
            // Pre-created record from the dashboard — update it with discovered info
            $this->dataVersion = DataVersion::findOrFail($dataVersionId);

            // Remove any existing record with the same (dataset, epoch) to avoid
            // unique-constraint violations when we update the pre-created record.
            DataVersion::where('dataset', 'ONSUD')
                ->where('epoch', $epoch)
                ->where('id', '!=', $this->dataVersion->id)
                ->delete();

            $this->dataVersion->update([
                'epoch' => $epoch,
                'release_date' => $releaseDate,
                'file_hash' => hash_file('sha256', $csvPath),
                'status' => 'importing',
                'progress_percentage' => 0,
                'status_message' => 'Starting import...',
                'current_file' => 1,
                'total_files' => 1,
                'log_file' => $logFile,
                'notes' => "Import started at " . now()->toDateTimeString(),
                'steps' => $steps,
                'current_step' => 'import',
            ]);
            $action = 'Updated pre-created';
        } else {
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
                    'steps' => $steps,
                    'current_step' => 'import',
                ]
            );
            $action = $this->dataVersion->wasRecentlyCreated ? 'Created' : 'Updated existing';
        }

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

    private function updateFileStatus(int $fileIndex, string $status, ?int $processed = null, ?int $total = null): void
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
            if ($total !== null) {
                $files[$fileIndex]['total'] = $total;
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
        if (! $this->dataVersion) {
            return;
        }

        $this->dataVersion->update([
            'progress_percentage' => min(100, $percentage),
            'status_message' => $message,
            'record_count' => $currentRecords ?? $this->dataVersion->record_count,
        ]);

        // Also update the import step
        $this->updateStep('import', 'active', $percentage, $message);
    }

    private function updateDataVersionStatus(string $status, ?string $notes = null): void
    {
        if (! $this->dataVersion) {
            return;
        }

        $this->dataVersion->update([
            'status' => $status,
            'record_count' => $this->stats['successful'],
            'notes' => $notes ?? $this->dataVersion->notes,
        ]);

        $this->info("Updated data version status to: {$status}");
    }

    /**
     * Update a named step in the steps JSON array.
     * Status can be: pending, active, completed, failed.
     */
    private function updateStep(string $key, string $status, ?float $progress = null, ?string $message = null): void
    {
        if (! $this->dataVersion) {
            return;
        }

        $steps = $this->dataVersion->steps ?? [];

        foreach ($steps as &$step) {
            if ($step['key'] === $key) {
                $step['status'] = $status;
                if ($progress !== null) {
                    $step['progress'] = round($progress, 1);
                }
                if ($message !== null) {
                    $step['message'] = $message;
                }
            }
            // Cascade: mark all earlier steps as completed if current is active/completed
            if (in_array($status, ['active', 'completed'], true)) {
                $currentIndex = array_search($key, array_column($steps, 'key'));
                foreach ($steps as $i => &$s) {
                    if ($i < $currentIndex && $s['status'] !== 'completed' && $s['status'] !== 'failed') {
                        $s['status'] = 'completed';
                        $s['progress'] = 100;
                    }
                }
            }
        }

        $update = [
            'steps' => $steps,
        ];
        if ($status === 'active') {
            $update['current_step'] = $key;
        }

        $this->dataVersion->update($update);
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
        $metrics = [
            ['Total Rows', number_format($this->stats['total_rows'])],
            ['Successful', number_format($this->stats['successful'])],
            ['Skipped', number_format($this->stats['skipped'])],
            ['Errors', number_format($this->stats['errors'])],
            ['Coordinate Errors', number_format($this->stats['coordinate_errors'])],
            ['Missing Required Fields', number_format($this->stats['missing_required_fields'])],
        ];

        if ($this->stats['filtered_by_postcode'] > 0) {
            $metrics[] = ['Filtered by Postcode', number_format($this->stats['filtered_by_postcode'])];
        }

        $this->table(['Metric', 'Count'], $metrics);

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

        $this->updateStep('download', 'active', 0, 'Starting download...');

        try {
            // Use Laravel HTTP client with streaming for large files
            $response = Http::timeout(3600)->withOptions([
                'sink' => $destination,
                'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) {
                    if ($downloadTotal > 0) {
                        $percentage = round(($downloadedBytes / $downloadTotal) * 100, 1);
                        $message = sprintf(
                            'Downloaded: %s / %s (%s%%)',
                            $this->formatFileSize($downloadedBytes),
                            $this->formatFileSize($downloadTotal),
                            $percentage
                        );
                        $this->updateStep('download', 'active', $percentage, $message);
                        if ($downloadedBytes > 0 && $downloadedBytes % (10 * 1024 * 1024) === 0) {
                            // Update every 10MB
                            $this->info($message);
                        }
                    }
                },
            ])->get($url);

            if ($response->successful() && File::exists($destination)) {
                $size = filesize($destination);
                $this->updateStep('download', 'completed', 100, "Download complete: " . $this->formatFileSize($size));
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
     * Attempt to download ONSUD file from ArcGIS Online
     */
    private function downloadOnsudFile(string $epoch): ?string
    {
        $this->newLine();
        $this->info("Attempting automatic download of ONSUD epoch {$epoch} from ArcGIS...");

        // If we already discovered this release, use the cached item ID
        if ($this->discoveredRelease && (int) $this->discoveredRelease['epoch'] === (int) $epoch) {
            $itemId = $this->discoveredRelease['item_id'];
            $filename = $this->discoveredRelease['name'];

            return $this->downloadArcGISItem($itemId, $filename, $epoch);
        }

        // Search ArcGIS for this specific epoch
        $searchUrl = 'https://www.arcgis.com/sharing/rest/search';
        $params = [
            'q' => 'type:"CSV Collection" owner:ONSGeography_data ONSUD',
            'sortField' => 'modified',
            'sortOrder' => 'desc',
            'num' => 50,
            'f' => 'json',
        ];

        try {
            $response = Http::timeout(30)->get($searchUrl, $params);

            if (! $response->successful()) {
                $this->error('ArcGIS search failed: ' . $response->status());

                return null;
            }

            $data = $response->json();

            if (empty($data['results'])) {
                $this->error('No ONSUD releases found on ArcGIS');

                return null;
            }

            foreach ($data['results'] as $item) {
                if (str_contains($item['title'], 'User Guide')) {
                    continue;
                }
                if ($item['type'] !== 'CSV Collection') {
                    continue;
                }

                $itemEpoch = $this->parseEpochFromTitle($item['title']);
                if ((int) $itemEpoch === (int) $epoch) {
                    return $this->downloadArcGISItem($item['id'], $item['name'], $epoch);
                }
            }

            $this->error("ONSUD epoch {$epoch} not found on ArcGIS");

            return null;

        } catch (\Exception $e) {
            $this->error('Automatic download failed: ' . $e->getMessage());

            return null;
        }
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
     * Import geography lookup files from the Documents folder into boundary_names.
     */
    private function importLookupFiles(string $extractPath, int $epoch, string $releaseDate): void
    {
        $documentsPath = $extractPath . '/Documents';
        if (! File::isDirectory($documentsPath)) {
            return;
        }

        $csvFiles = File::glob("{$documentsPath}/*.csv");
        if (empty($csvFiles)) {
            $this->info('No lookup CSV files found in Documents folder');

            return;
        }

        // Map filename prefixes to boundary_type
        $typeMap = [
            'BUA' => 'bua',
            'CED' => 'ced',
            'CTRY' => 'country',
            'CTY' => 'county',
            'EER' => 'eer',
            'HLTHAU' => 'hlthau',
            'LEP' => 'lep',
            'LSOA' => 'lsoa',
            'MSOA' => 'msoa',
            'NPARK' => 'npark',
            'PARNCP' => 'parish',
            'PCON' => 'constituency',
            'PFA' => 'police_force_area',
            'RGN' => 'region',
            'RUC21' => 'ruc',
            'SICBL' => 'sicbl',
            'TTWA' => 'ttwa',
            'WD' => 'ward',
            'LAD' => 'lad',
        ];

        // Skip ITL (complex combined lookup) and Scotland-specific files that use different codes
        $skipPatterns = ['/ITL/i', '/SC as at/i', '/_SC_/i'];

        $lookupFiles = [];
        foreach ($csvFiles as $csvPath) {
            $name = basename($csvPath);
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $boundaryType = null;
            foreach ($typeMap as $prefix => $type) {
                if (str_starts_with($name, $prefix)) {
                    $boundaryType = $type;
                    break;
                }
            }

            if ($boundaryType) {
                $lookupFiles[] = [
                    'path' => $csvPath,
                    'name' => $name,
                    'type' => $boundaryType,
                ];
            }
        }

        if (empty($lookupFiles)) {
            $this->info('No matching geography lookup files found');

            return;
        }

        $this->info('Found ' . count($lookupFiles) . ' geography lookup file(s) to import');
        $this->updateStep('lookups', 'active', 0, 'Importing ' . count($lookupFiles) . ' geography lookup file(s)...');

        // Append lookup files to existing file tracking
        $existingFiles = $this->dataVersion->files ?? [];
        $baseIndex = count($existingFiles);
        foreach ($lookupFiles as $lookup) {
            $existingFiles[] = [
                'name' => $lookup['name'],
                'path' => $lookup['path'],
                'total' => null,
                'processed' => 0,
                'status' => 'pending',
                'type' => 'lookup',
                'boundary_type' => $lookup['type'],
            ];
        }

        $this->dataVersion->update([
            'total_files' => $baseIndex + count($lookupFiles),
            'files' => $existingFiles,
        ]);

        $source = 'ONSUD_Lookup';
        $versionDate = $releaseDate;

        foreach ($lookupFiles as $index => $lookup) {
            $fileIndex = $baseIndex + $index;
            $this->updateFileStatus($fileIndex, 'processing');
            $this->info("Importing lookup file " . ($index + 1) . "/" . count($lookupFiles) . ": {$lookup['name']}");

            try {
                $count = $this->importSingleLookupFile($lookup['path'], $lookup['type'], $source, $versionDate);
                $this->updateFileStatus($fileIndex, 'completed', $count, $count);

                $progress = round((($index + 1) / count($lookupFiles)) * 100, 1);
                $message = "Imported {$lookup['type']} lookups ({$count} rows) from {$lookup['name']}";
                $this->updateStep('lookups', 'active', $progress, $message);
                $this->info("  {$count} rows imported");
            } catch (\Throwable $e) {
                $this->updateFileStatus($fileIndex, 'failed');
                $this->error("Failed to import {$lookup['name']}: " . $e->getMessage());
            }
        }

        $this->updateStep('lookups', 'completed', 100, 'Geography lookups imported');
        $this->info('Geography lookup import complete');
    }

    /**
     * Import a single lookup CSV into boundary_names.
     */
    private function importSingleLookupFile(string $csvPath, string $boundaryType, string $source, string $versionDate): int
    {
        $file = fopen($csvPath, 'r');
        if (! $file) {
            throw new \RuntimeException("Cannot open lookup file: {$csvPath}");
        }

        $header = fgetcsv($file);
        if (! $header) {
            fclose($file);
            throw new \RuntimeException("Empty lookup file: {$csvPath}");
        }

        // Heuristic column detection
        $codeColumn = null;
        $nameColumn = null;
        $nameWelshColumn = null;

        foreach ($header as $col) {
            $upper = strtoupper($col);
            if ($codeColumn === null && str_ends_with($upper, 'CD') && ! str_ends_with($upper, 'NMCD')) {
                $codeColumn = $col;
            }
            if ($nameColumn === null && str_ends_with($upper, 'NM') && ! str_ends_with($upper, 'NMW')) {
                $nameColumn = $col;
            }
            if ($nameWelshColumn === null && str_ends_with($upper, 'NMW')) {
                $nameWelshColumn = $col;
            }
        }

        if ($codeColumn === null || $nameColumn === null) {
            fclose($file);
            throw new \RuntimeException("Could not detect code/name columns in {$csvPath}");
        }

        $batch = [];
        $batchSize = 1000;
        $imported = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }

            $gssCode = trim($data[$codeColumn] ?? '');
            $name = trim($data[$nameColumn] ?? '');

            if (empty($gssCode) || empty($name)) {
                continue;
            }

            $batch[] = [
                'boundary_type' => $boundaryType,
                'gss_code' => $gssCode,
                'name' => $name,
                'name_welsh' => $nameWelshColumn ? ($data[$nameWelshColumn] ?? null) : null,
                'source' => $source,
                'version_date' => $versionDate,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertBoundaryNames($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->upsertBoundaryNames($batch);
            $imported += count($batch);
        }

        fclose($file);

        return $imported;
    }

    /**
     * Upsert a batch of boundary names, matching on (boundary_type, gss_code).
     */
    private function upsertBoundaryNames(array $batch): void
    {
        DB::table('boundary_names')->upsert(
            $batch,
            ['boundary_type', 'gss_code'],
            ['name', 'name_welsh', 'source', 'version_date', 'updated_at']
        );
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
     * Delete extracted CSV files and their parent directories after a successful import.
     */
    private function cleanupExtractedFiles(): void
    {
        if (empty($this->cleanupPaths)) {
            return;
        }

        $deletedFiles = 0;
        $deletedDirs = [];

        foreach ($this->cleanupPaths as $csvPath) {
            if (File::exists($csvPath)) {
                File::delete($csvPath);
                $deletedFiles++;
            }

            // Track parent directories for deletion
            $dir = dirname($csvPath);
            while ($dir && str_contains($dir, 'onsud') && ! in_array($dir, $deletedDirs, true)) {
                $deletedDirs[] = $dir;
                $dir = dirname($dir);
            }
        }

        // Delete empty directories (newest/deepest first to avoid non-empty errors)
        rsort($deletedDirs);
        foreach ($deletedDirs as $dir) {
            if (File::isDirectory($dir) && count(File::files($dir)) === 0 && count(File::directories($dir)) === 0) {
                File::deleteDirectory($dir);
            }
        }

        if ($deletedFiles > 0) {
            $this->info("Cleaned up {$deletedFiles} extracted CSV file(s)");
        }
    }

    /**
     * Discover the latest ONSUD release from ArcGIS Online
     */
    private function discoverLatestOnsudRelease(): ?array
    {
        $searchUrl = 'https://www.arcgis.com/sharing/rest/search';
        $params = [
            'q' => 'type:"CSV Collection" owner:ONSGeography_data ONSUD',
            'sortField' => 'modified',
            'sortOrder' => 'desc',
            'num' => 20,
            'f' => 'json',
        ];

        try {
            $response = Http::timeout(30)->get($searchUrl, $params);

            if (! $response->successful()) {
                Log::warning('ArcGIS search failed', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();

            if (empty($data['results'])) {
                Log::warning('ArcGIS search returned no results');

                return null;
            }

            foreach ($data['results'] as $item) {
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
                if (! $releaseDate) {
                    continue;
                }

                return [
                    'item_id' => $item['id'],
                    'name' => $item['name'],
                    'title' => $item['title'],
                    'epoch' => $epoch,
                    'release_date' => $releaseDate,
                    'size' => $item['size'] ?? null,
                ];
            }

            Log::warning('No valid ONSUD release found in ArcGIS search results');

            return null;

        } catch (\Exception $e) {
            Log::error('ONSUD discovery failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Parse epoch number from ONSUD item title
     */
    private function parseEpochFromTitle(string $title): ?int
    {
        if (preg_match('/Epoch\s+(\d+)/i', $title, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Parse release date from ONSUD item title
     */
    private function parseDateFromTitle(string $title): ?string
    {
        if (preg_match('/\(([A-Za-z]+)\s+(\d{4})\)/', $title, $matches)) {
            $monthName = $matches[1];
            $year = $matches[2];

            $monthMap = [
                'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
                'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
                'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12',
            ];

            $monthNum = $monthMap[$monthName] ?? null;
            if ($monthNum) {
                return "{$year}-{$monthNum}-01";
            }
        }

        return null;
    }

    /**
     * Download an item from ArcGIS Online by item ID
     */
    private function downloadArcGISItem(string $itemId, string $filename, string $epoch): ?string
    {
        $downloadUrl = "https://www.arcgis.com/sharing/rest/content/items/{$itemId}/data";
        $destination = storage_path("app/onsud/epoch-{$epoch}-" . date('YmdHis') . '.zip');

        $destinationDir = dirname($destination);
        if (! File::exists($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
        }

        $this->info("Downloading {$filename} from ArcGIS...");
        $this->info("Destination: {$destination}");

        $this->updateStep('download', 'active', 0, 'Starting download from ArcGIS...');

        try {
            $response = Http::timeout(3600)->withOptions([
                'sink' => $destination,
                'progress' => function ($downloadTotal, $downloadedBytes) {
                    if ($downloadTotal > 0) {
                        $percentage = round(($downloadedBytes / $downloadTotal) * 100, 1);
                        $message = sprintf(
                            'Downloaded: %s / %s (%s%%)',
                            $this->formatFileSize($downloadedBytes),
                            $this->formatFileSize($downloadTotal),
                            $percentage
                        );
                        $this->updateStep('download', 'active', $percentage, $message);
                        if ($downloadedBytes > 0 && $downloadedBytes % (50 * 1024 * 1024) === 0) {
                            $this->info($message);
                        }
                    }
                },
            ])->get($downloadUrl);

            if ($response->successful() && File::exists($destination) && filesize($destination) > 0) {
                $size = filesize($destination);
                $this->updateStep('download', 'completed', 100, "Download complete: " . $this->formatFileSize($size));
                $this->info('Download complete! Size: ' . $this->formatFileSize($size));

                return $destination;
            }

            $this->error('Download failed with status: ' . $response->status());
            if (File::exists($destination)) {
                File::delete($destination);
            }

            return null;

        } catch (\Exception $e) {
            $this->error('Download failed: ' . $e->getMessage());
            if (File::exists($destination)) {
                File::delete($destination);
            }

            return null;
        }
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

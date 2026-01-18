<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class UpdatePropertyWardCodesCommand extends Command
{
    protected $signature = 'onsud:update-ward-codes
                            {--batch-size=10000 : Number of records to update per batch}
                            {--limit= : Limit total records to process (for testing)}
                            {--dry-run : Show what would be updated without actually updating}';

    protected $description = 'Update all NULL ward codes (wd25cd) in the properties table by reading from the ONSUD CSV files';

    private array $stats = [
        'total_processed' => 0,
        'total_updated' => 0,
        'total_skipped' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting ward code update process...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Check how many NULL ward codes exist
        $nullCount = DB::table('properties')->whereNull('wd25cd')->count();
        $this->info("Properties with NULL ward codes: " . number_format($nullCount));

        if ($nullCount === 0) {
            $this->info('No NULL ward codes found. Nothing to update.');
            return 0;
        }

        // Find all ONSUD CSV files
        $csvPattern = storage_path('app/onsud/extracted/Data/ONSUD_DEC_2025_*.csv');
        $csvFiles = File::glob($csvPattern);

        if (empty($csvFiles)) {
            $this->error('No ONSUD CSV files found at: ' . $csvPattern);
            return 1;
        }

        sort($csvFiles);
        $this->info('Found ' . count($csvFiles) . ' CSV files to process');
        $this->newLine();

        // Process each CSV file
        foreach ($csvFiles as $index => $csvFile) {
            $this->info('Processing file ' . ($index + 1) . '/' . count($csvFiles) . ': ' . basename($csvFile));

            $this->processCsvFile($csvFile, $batchSize, $limit, $dryRun);

            // Check if limit reached
            if ($limit && $this->stats['total_processed'] >= $limit) {
                $this->warn('Limit of ' . number_format($limit) . ' records reached. Stopping.');
                break;
            }

            $this->newLine();
        }

        // Display final statistics
        $this->displayStatistics($dryRun);

        Log::info('Ward code update process completed', $this->stats);

        return 0;
    }

    private function processCsvFile(string $csvPath, int $batchSize, ?int $limit, bool $dryRun): void
    {
        $file = fopen($csvPath, 'r');
        if (!$file) {
            $this->error("Failed to open file: {$csvPath}");
            $this->stats['errors']++;
            return;
        }

        // Read and validate header
        $header = fgetcsv($file);
        if (!$header) {
            $this->error("Failed to read header from: {$csvPath}");
            fclose($file);
            $this->stats['errors']++;
            return;
        }

        // Find column indexes
        $uprnIndex = array_search('UPRN', $header);
        $wardCodeIndex = array_search('WD25CD', $header);

        if ($uprnIndex === false || $wardCodeIndex === false) {
            $this->error('Required columns (UPRN, WD25CD) not found in CSV header');
            fclose($file);
            $this->stats['errors']++;
            return;
        }

        // Count total rows for progress bar
        $totalRows = 0;
        while (fgetcsv($file) !== false) {
            $totalRows++;
        }
        rewind($file);
        fgetcsv($file); // Skip header again

        $this->info("Total rows in file: " . number_format($totalRows));

        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%% - Updated: %message%');
        $progressBar->setMessage('0');

        $batch = [];
        $processedInFile = 0;

        try {
            while (($row = fgetcsv($file)) !== false) {
                // Check limit
                if ($limit && $this->stats['total_processed'] >= $limit) {
                    break;
                }

                $uprn = $row[$uprnIndex] ?? null;
                $wardCode = $row[$wardCodeIndex] ?? null;

                // Skip invalid rows
                if (!$uprn) {
                    $this->stats['total_skipped']++;
                    $this->stats['total_processed']++;
                    $processedInFile++;
                    $progressBar->advance();
                    continue;
                }

                // Add to batch
                $batch[] = [
                    'uprn' => (int) $uprn,
                    'ward_code' => !empty($wardCode) ? $wardCode : null,
                ];

                $this->stats['total_processed']++;
                $processedInFile++;

                // Process batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    $updated = $this->processBatch($batch, $dryRun);
                    $progressBar->setMessage(number_format($this->stats['total_updated']));
                    $batch = [];
                }

                $progressBar->advance();
            }

            // Process remaining batch
            if (!empty($batch)) {
                $updated = $this->processBatch($batch, $dryRun);
                $progressBar->setMessage(number_format($this->stats['total_updated']));
            }

            $progressBar->finish();
            $this->newLine();
            $this->info("Processed " . number_format($processedInFile) . " rows from this file");

        } finally {
            fclose($file);
        }
    }

    private function processBatch(array $batch, bool $dryRun): int
    {
        if (empty($batch)) {
            return 0;
        }

        if ($dryRun) {
            // In dry-run mode, count how many would be updated
            $uprnList = array_column($batch, 'uprn');
            $wouldUpdate = DB::table('properties')
                ->whereIn('uprn', $uprnList)
                ->whereNull('wd25cd')
                ->count();

            $this->stats['total_updated'] += $wouldUpdate;
            return $wouldUpdate;
        }

        try {
            $updated = 0;

            DB::transaction(function () use ($batch, &$updated) {
                foreach ($batch as $record) {
                    if ($record['ward_code'] === null) {
                        $this->stats['total_skipped']++;
                        continue;
                    }

                    try {
                        $affectedRows = DB::table('properties')
                            ->where('uprn', $record['uprn'])
                            ->whereNull('wd25cd')
                            ->update(['wd25cd' => $record['ward_code']]);

                        if ($affectedRows > 0) {
                            $updated++;
                            $this->stats['total_updated']++;
                        } else {
                            $this->stats['total_skipped']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['errors']++;
                        Log::error("Error updating UPRN {$record['uprn']}: " . $e->getMessage());
                    }
                }
            });

            return $updated;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("Error in batch processing: " . $e->getMessage());
            return 0;
        }
    }

    private function displayStatistics(bool $dryRun): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('DRY RUN SUMMARY - No changes were made');
        } else {
            $this->info('UPDATE SUMMARY');
        }

        $this->info('═══════════════════════════════════════════════════════════════');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', number_format($this->stats['total_processed'])],
                ['Total Updated', number_format($this->stats['total_updated'])],
                ['Total Skipped', number_format($this->stats['total_skipped'])],
                ['Errors', number_format($this->stats['errors'])],
            ]
        );

        if (!$dryRun && $this->stats['total_updated'] > 0) {
            $this->newLine();
            $this->info('Ward codes successfully updated!');
            $this->info('Run this command to verify:');
            $this->line('  SELECT COUNT(*) FROM properties WHERE wd25cd IS NOT NULL;');
        }
    }
}

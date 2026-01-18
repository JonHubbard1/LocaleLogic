<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PopulateWardCodesCommand extends Command
{
    protected $signature = 'onsud:populate-wards
        {--directory=storage/app/onsud/extracted/Data : Directory containing ONSUD CSV files}
        {--batch-size=5000 : Number of records per batch update}';

    protected $description = 'Populate ward codes (wd25cd) in properties table from ONSUD CSV files';

    public function handle(): int
    {
        $directory = base_path($this->option('directory'));
        $batchSize = (int) $this->option('batch-size');

        if (!File::exists($directory)) {
            $this->error("Directory not found: {$directory}");
            return 1;
        }

        $csvFiles = File::glob("{$directory}/ONSUD_*.csv");

        if (empty($csvFiles)) {
            $this->error("No ONSUD CSV files found in {$directory}");
            return 1;
        }

        $this->info("Found " . count($csvFiles) . " CSV files to process");
        $this->newLine();

        // Check current state
        $totalRecords = DB::table('properties')->count();
        $withWardCodes = DB::table('properties')->whereNotNull('wd25cd')->where('wd25cd', '!=', '')->count();

        $this->info("Current state: {$withWardCodes}/{$totalRecords} records have ward codes");
        $this->newLine();

        $totalUpdated = 0;
        $totalProcessed = 0;

        foreach ($csvFiles as $index => $csvFile) {
            $this->info("Processing file " . ($index + 1) . "/" . count($csvFiles) . ": " . basename($csvFile));

            $updated = $this->processFile($csvFile, $batchSize);
            $totalUpdated += $updated;

            $this->info("  Updated {$updated} records");
            $this->newLine();
        }

        $this->info("Complete! Updated {$totalUpdated} records total.");

        // Show final state
        $withWardCodes = DB::table('properties')->whereNotNull('wd25cd')->where('wd25cd', '!=', '')->count();
        $this->info("Final state: {$withWardCodes}/{$totalRecords} records have ward codes");

        return 0;
    }

    private function processFile(string $csvFile, int $batchSize): int
    {
        $file = fopen($csvFile, 'r');
        if (!$file) {
            $this->error("Failed to open: {$csvFile}");
            return 0;
        }

        $header = fgetcsv($file);
        $uprnIndex = array_search('UPRN', $header);
        $wardIndex = array_search('WD25CD', $header);

        if ($uprnIndex === false || $wardIndex === false) {
            $this->error("Required columns not found in header");
            fclose($file);
            return 0;
        }

        $batch = [];
        $totalUpdated = 0;
        $rowCount = 0;

        while (($row = fgetcsv($file)) !== false) {
            $uprn = $row[$uprnIndex] ?? null;
            $wardCode = $row[$wardIndex] ?? null;

            if ($uprn && $wardCode && trim($wardCode) !== '') {
                $batch[(int)$uprn] = trim($wardCode);
            }

            $rowCount++;

            if (count($batch) >= $batchSize) {
                $updated = $this->updateBatch($batch);
                $totalUpdated += $updated;
                $batch = [];

                // Show progress every 50k rows
                if ($rowCount % 50000 === 0) {
                    $this->line("  Processed {$rowCount} rows, updated {$totalUpdated} so far...");
                }
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $updated = $this->updateBatch($batch);
            $totalUpdated += $updated;
        }

        fclose($file);

        return $totalUpdated;
    }

    private function updateBatch(array $batch): int
    {
        if (empty($batch)) {
            return 0;
        }

        $updated = 0;

        // Build a CASE WHEN statement for bulk update
        $cases = [];
        $uprns = [];

        foreach ($batch as $uprn => $wardCode) {
            $uprns[] = $uprn;
            $cases[] = "WHEN {$uprn} THEN '{$wardCode}'";
        }

        $casesStr = implode(' ', $cases);
        $uprnsStr = implode(',', $uprns);

        $sql = "UPDATE properties SET wd25cd = CASE uprn {$casesStr} END WHERE uprn IN ({$uprnsStr}) AND (wd25cd IS NULL OR wd25cd = '')";

        try {
            $updated = DB::update($sql);
        } catch (\Exception $e) {
            $this->error("Batch update failed: " . $e->getMessage());
        }

        return $updated;
    }
}

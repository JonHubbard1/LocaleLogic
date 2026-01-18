<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Fix Ward Data Command
 *
 * This command fixes missing ward codes in the properties table and populates
 * the ward_hierarchy_lookups table. Run this after an ONSUD import if ward
 * data is missing.
 *
 * The issue occurs when the properties_staging table has FK constraints that
 * reject valid ward codes not present in the wards lookup table.
 */
class FixWardDataCommand extends Command
{
    protected $signature = 'onsud:fix-ward-data
        {--directory=storage/app/onsud/extracted/Data : Directory containing ONSUD CSV files}
        {--batch-size=10000 : Batch size for updates}
        {--skip-wards : Skip populating ward codes from CSV}
        {--skip-hierarchy : Skip populating ward hierarchy lookups}';

    protected $description = 'Fix missing ward codes and populate ward hierarchy lookups';

    public function handle(): int
    {
        $this->info('===========================================');
        $this->info('  Ward Data Fix Command');
        $this->info('===========================================');
        $this->newLine();

        // Step 1: Show current state
        $this->showCurrentState();

        // Step 2: Remove any FK constraints from staging table
        $this->removeStakingForeignKeys();

        // Step 3: Populate ward codes from ONSUD CSV (if available)
        if (!$this->option('skip-wards')) {
            $this->populateWardCodes();
        }

        // Step 4: Populate ward hierarchy lookups
        if (!$this->option('skip-hierarchy')) {
            $this->populateWardHierarchy();
        }

        // Step 5: Show final state
        $this->newLine();
        $this->info('===========================================');
        $this->info('  Final State');
        $this->info('===========================================');
        $this->showCurrentState();

        return 0;
    }

    private function showCurrentState(): void
    {
        $totalProperties = DB::table('properties')->count();
        $withWardCodes = DB::table('properties')
            ->whereNotNull('wd25cd')
            ->whereRaw("TRIM(wd25cd) != ''")
            ->count();

        $wardHierarchyCount = DB::table('ward_hierarchy_lookups')->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total properties', number_format($totalProperties)],
                ['Properties with ward codes', number_format($withWardCodes)],
                ['Ward hierarchy lookups', number_format($wardHierarchyCount)],
            ]
        );

        if ($withWardCodes > 0) {
            // Show breakdown by council type
            $breakdown = DB::table('properties')
                ->select(DB::raw("
                    CASE
                        WHEN lad25cd LIKE 'E06%' THEN 'Unitary (E06)'
                        WHEN lad25cd LIKE 'E07%' THEN 'District (E07)'
                        WHEN lad25cd LIKE 'E08%' THEN 'Metropolitan (E08)'
                        WHEN lad25cd LIKE 'E09%' THEN 'London Borough (E09)'
                        WHEN lad25cd LIKE 'W06%' THEN 'Welsh Unitary (W06)'
                        WHEN lad25cd LIKE 'S12%' THEN 'Scottish (S12)'
                        ELSE 'Other'
                    END as council_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN wd25cd IS NOT NULL AND TRIM(wd25cd) != '' THEN 1 ELSE 0 END) as with_ward
                "))
                ->whereNotNull('lad25cd')
                ->groupBy('council_type')
                ->orderByDesc('total')
                ->get();

            $this->newLine();
            $this->info('Properties by council type:');
            $this->table(
                ['Council Type', 'Total', 'With Ward Code', '%'],
                $breakdown->map(fn($row) => [
                    $row->council_type,
                    number_format($row->total),
                    number_format($row->with_ward),
                    $row->total > 0 ? round($row->with_ward / $row->total * 100, 1) . '%' : '0%'
                ])->toArray()
            );
        }
    }

    private function removeStakingForeignKeys(): void
    {
        if (!Schema::hasTable('properties_staging')) {
            return;
        }

        $this->info('Checking properties_staging foreign keys...');

        $fks = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'properties_staging'::regclass
            AND contype = 'f'
        ");

        if (empty($fks)) {
            $this->line('  No foreign keys found on properties_staging');
            return;
        }

        $this->warn('  Removing ' . count($fks) . ' foreign key constraints...');

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE properties_staging DROP CONSTRAINT IF EXISTS {$fk->conname}");
            $this->line("    Dropped: {$fk->conname}");
        }
    }

    private function populateWardCodes(): void
    {
        $directory = base_path($this->option('directory'));
        $batchSize = (int) $this->option('batch-size');

        $this->newLine();
        $this->info('Populating ward codes from ONSUD CSV files...');

        if (!File::exists($directory)) {
            $this->warn("  Directory not found: {$directory}");
            $this->warn("  Skipping ward code population. Run onsud:populate-wards manually.");
            return;
        }

        $csvFiles = File::glob("{$directory}/ONSUD_*.csv");

        if (empty($csvFiles)) {
            $this->warn("  No ONSUD CSV files found in {$directory}");
            $this->warn("  Skipping ward code population.");
            return;
        }

        $this->info("  Found " . count($csvFiles) . " CSV files");

        $totalUpdated = 0;

        foreach ($csvFiles as $index => $csvFile) {
            $filename = basename($csvFile);
            $this->line("  Processing " . ($index + 1) . "/" . count($csvFiles) . ": {$filename}");

            $updated = $this->processOnsudCsv($csvFile, $batchSize);
            $totalUpdated += $updated;

            $this->line("    Updated {$updated} records");
        }

        $this->info("  Total updated: {$totalUpdated} records");
    }

    private function processOnsudCsv(string $csvFile, int $batchSize): int
    {
        $file = fopen($csvFile, 'r');
        if (!$file) {
            return 0;
        }

        $header = fgetcsv($file);
        $uprnIndex = array_search('UPRN', $header);
        $wardIndex = array_search('WD25CD', $header);

        if ($uprnIndex === false || $wardIndex === false) {
            $this->error("    Missing UPRN or WD25CD column in CSV");
            fclose($file);
            return 0;
        }

        // Count total rows for progress
        $totalRows = 0;
        while (fgetcsv($file) !== false) {
            $totalRows++;
        }
        rewind($file);
        fgetcsv($file); // Skip header again

        $this->line("    Total rows: " . number_format($totalRows));

        $batch = [];
        $totalUpdated = 0;
        $rowsProcessed = 0;
        $lastProgressUpdate = 0;

        while (($row = fgetcsv($file)) !== false) {
            $rowsProcessed++;
            $uprn = $row[$uprnIndex] ?? null;
            $wardCode = $row[$wardIndex] ?? null;

            if ($uprn && $wardCode && trim($wardCode) !== '') {
                $batch[(int)$uprn] = trim($wardCode);
            }

            if (count($batch) >= $batchSize) {
                $updated = $this->updateBatch($batch);
                $totalUpdated += $updated;
                $batch = [];

                // Show progress every 50k rows
                if ($rowsProcessed - $lastProgressUpdate >= 50000) {
                    $percent = round($rowsProcessed / $totalRows * 100, 1);
                    $this->line("    Progress: " . number_format($rowsProcessed) . "/" . number_format($totalRows) . " rows ({$percent}%) - Updated: " . number_format($totalUpdated));
                    $lastProgressUpdate = $rowsProcessed;
                }
            }
        }

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

        $cases = [];
        $uprns = [];

        foreach ($batch as $uprn => $wardCode) {
            $uprns[] = $uprn;
            $cases[] = "WHEN {$uprn} THEN '{$wardCode}'";
        }

        $casesStr = implode(' ', $cases);
        $uprnsStr = implode(',', $uprns);

        $sql = "UPDATE properties SET wd25cd = CASE uprn {$casesStr} END WHERE uprn IN ({$uprnsStr}) AND (wd25cd IS NULL OR TRIM(wd25cd) = '')";

        try {
            return DB::update($sql);
        } catch (\Exception $e) {
            $this->error("Batch update failed: " . $e->getMessage());
            return 0;
        }
    }

    private function populateWardHierarchy(): void
    {
        $this->newLine();
        $this->info('Populating ward hierarchy lookups...');

        // Check if we have ward codes in properties
        $wardCount = DB::table('properties')
            ->whereNotNull('wd25cd')
            ->whereRaw("TRIM(wd25cd) != ''")
            ->count();

        if ($wardCount === 0) {
            $this->warn('  No ward codes found in properties table. Skipping hierarchy population.');
            return;
        }

        $this->line("  Properties with ward codes: " . number_format($wardCount));

        // Get unique ward-LAD combinations
        $this->line("  Finding unique ward-LAD pairs...");
        $wardLadPairs = DB::table('properties')
            ->select('wd25cd', 'lad25cd')
            ->whereNotNull('wd25cd')
            ->whereRaw("TRIM(wd25cd) != ''")
            ->distinct()
            ->get();

        $this->info("  Found " . number_format($wardLadPairs->count()) . " unique ward-LAD pairs");

        $inserted = 0;
        $skipped = 0;
        $processed = 0;
        $total = $wardLadPairs->count();

        foreach ($wardLadPairs as $pair) {
            $processed++;
            $wardCode = trim($pair->wd25cd);
            $ladCode = trim($pair->lad25cd);

            // Skip if already exists
            $exists = DB::table('ward_hierarchy_lookups')
                ->where('wd_code', $wardCode)
                ->where('lad_code', $ladCode)
                ->exists();

            if ($exists) {
                $skipped++;
            } else {
                // Get names from boundary_names
                $wardName = DB::table('boundary_names')
                    ->where('gss_code', $wardCode)
                    ->value('name') ?? $wardCode;

                $ladName = DB::table('boundary_names')
                    ->where('gss_code', $ladCode)
                    ->value('name') ?? $ladCode;

                DB::table('ward_hierarchy_lookups')->insert([
                    'wd_code' => $wardCode,
                    'wd_name' => $wardName,
                    'lad_code' => $ladCode,
                    'lad_name' => $ladName,
                    'cty_code' => null,
                    'cty_name' => null,
                    'ced_code' => null,
                    'ced_name' => null,
                    'source' => 'fix_ward_data',
                    'version_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted++;
            }

            // Show progress every 500 pairs
            if ($processed % 500 === 0 || $processed === $total) {
                $percent = round($processed / $total * 100, 1);
                $this->line("  Progress: {$processed}/{$total} ({$percent}%) - Inserted: {$inserted}, Skipped: {$skipped}");
            }
        }

        $this->info("  Complete! Inserted: {$inserted}, Skipped (existing): {$skipped}");
    }
}

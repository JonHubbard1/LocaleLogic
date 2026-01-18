<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateWardHierarchyCommand extends Command
{
    protected $signature = 'geography:populate-ward-hierarchy
        {--truncate : Truncate existing data first}
        {--from-properties : Derive ward-LAD relationships from properties table}';

    protected $description = 'Populate ward_hierarchy_lookups table from boundary_names and properties tables';

    public function handle(): int
    {
        $this->info('Populating ward_hierarchy_lookups table...');
        $this->newLine();

        if ($this->option('truncate')) {
            $this->warn('Truncating ward_hierarchy_lookups table...');
            DB::table('ward_hierarchy_lookups')->truncate();
        }

        // Get count before
        $beforeCount = DB::table('ward_hierarchy_lookups')->count();
        $this->info("Records before: {$beforeCount}");

        if ($this->option('from-properties')) {
            return $this->populateFromProperties();
        }

        return $this->populateFromWardsTables();
    }

    private function populateFromProperties(): int
    {
        $this->info('Deriving ward-LAD relationships from properties table...');
        $this->newLine();

        // Get unique ward-LAD combinations from properties table
        // This captures the actual relationships based on ONSUD data
        $wardLadPairs = DB::table('properties')
            ->select('wd25cd', 'lad25cd')
            ->whereNotNull('wd25cd')
            ->where('wd25cd', '!=', '')
            ->distinct()
            ->get();

        $this->info("Found " . $wardLadPairs->count() . " unique ward-LAD pairs in properties");

        if ($wardLadPairs->isEmpty()) {
            $this->warn("No ward codes found in properties table. Run onsud:populate-wards first.");
            return 1;
        }

        $inserted = 0;
        $skipped = 0;
        $noName = 0;

        $progressBar = $this->output->createProgressBar($wardLadPairs->count());
        $progressBar->start();

        foreach ($wardLadPairs as $pair) {
            $wardCode = trim($pair->wd25cd);
            $ladCode = trim($pair->lad25cd);

            // Skip if already exists
            $exists = DB::table('ward_hierarchy_lookups')
                ->where('wd_code', $wardCode)
                ->where('lad_code', $ladCode)
                ->exists();

            if ($exists) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Get ward name from boundary_names
            $wardName = DB::table('boundary_names')
                ->where('gss_code', $wardCode)
                ->value('name');

            // Get LAD name from boundary_names
            $ladName = DB::table('boundary_names')
                ->where('gss_code', $ladCode)
                ->value('name');

            if (!$wardName || !$ladName) {
                $noName++;
                $progressBar->advance();
                continue;
            }

            // Insert new record
            DB::table('ward_hierarchy_lookups')->insert([
                'wd_code' => $wardCode,
                'wd_name' => $wardName,
                'lad_code' => $ladCode,
                'lad_name' => $ladName,
                'cty_code' => null,
                'cty_name' => null,
                'ced_code' => null,
                'ced_name' => null,
                'source' => 'properties_derived',
                'version_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        return $this->showResults($wardLadPairs->count(), $inserted, $skipped, $noName);
    }

    private function populateFromWardsTables(): int
    {
        // Get all wards from boundary_names that are ward types (E05, S13, W05)
        $wards = DB::table('boundary_names')
            ->where(function ($query) {
                $query->where('gss_code', 'like', 'E05%')
                    ->orWhere('gss_code', 'like', 'S13%')
                    ->orWhere('gss_code', 'like', 'W05%');
            })
            ->select('gss_code', 'name')
            ->get();

        $this->info("Found " . $wards->count() . " wards in boundary_names");

        $inserted = 0;
        $skipped = 0;
        $noLad = 0;

        $progressBar = $this->output->createProgressBar($wards->count());
        $progressBar->start();

        foreach ($wards as $ward) {
            // Try to find LAD from properties table
            $ladCode = DB::table('properties')
                ->where('wd25cd', $ward->gss_code)
                ->whereNotNull('lad25cd')
                ->value('lad25cd');

            if (!$ladCode) {
                $noLad++;
                $progressBar->advance();
                continue;
            }

            $ladCode = trim($ladCode);

            // Skip if already exists
            $exists = DB::table('ward_hierarchy_lookups')
                ->where('wd_code', $ward->gss_code)
                ->where('lad_code', $ladCode)
                ->exists();

            if ($exists) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Get LAD name
            $ladName = DB::table('boundary_names')
                ->where('gss_code', $ladCode)
                ->value('name');

            if (!$ladName) {
                $noLad++;
                $progressBar->advance();
                continue;
            }

            // Insert
            DB::table('ward_hierarchy_lookups')->insert([
                'wd_code' => $ward->gss_code,
                'wd_name' => $ward->name,
                'lad_code' => $ladCode,
                'lad_name' => $ladName,
                'cty_code' => null,
                'cty_name' => null,
                'ced_code' => null,
                'ced_name' => null,
                'source' => 'boundary_names',
                'version_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        return $this->showResults($wards->count(), $inserted, $skipped, $noLad);
    }

    private function showResults(int $processed, int $inserted, int $skipped, int $issues): int
    {
        $afterCount = DB::table('ward_hierarchy_lookups')->count();

        $this->info("Complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Items processed', $processed],
                ['New records inserted', $inserted],
                ['Already existed (skipped)', $skipped],
                ['Missing names/LAD (skipped)', $issues],
                ['Total records now', $afterCount],
            ]
        );

        // Show breakdown by council type
        $this->newLine();
        $this->info('Records by council type:');

        $breakdown = DB::table('ward_hierarchy_lookups')
            ->select(DB::raw("
                CASE
                    WHEN lad_code LIKE 'E06%' THEN 'Unitary (E06)'
                    WHEN lad_code LIKE 'E07%' THEN 'District (E07)'
                    WHEN lad_code LIKE 'E08%' THEN 'Metropolitan (E08)'
                    WHEN lad_code LIKE 'E09%' THEN 'London Borough (E09)'
                    WHEN lad_code LIKE 'W06%' THEN 'Welsh Unitary (W06)'
                    WHEN lad_code LIKE 'S12%' THEN 'Scottish (S12)'
                    ELSE 'Other'
                END as council_type,
                COUNT(*) as count
            "))
            ->groupBy('council_type')
            ->orderByDesc('count')
            ->get();

        $this->table(
            ['Council Type', 'Ward Count'],
            $breakdown->map(fn($row) => [$row->council_type, $row->count])->toArray()
        );

        return 0;
    }
}

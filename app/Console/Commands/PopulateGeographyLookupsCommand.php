<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopulateGeographyLookupsCommand extends Command
{
    protected $signature = 'geography:populate
                            {--table=all : Which table to populate (counties, divisions, parishes, all)}
                            {--truncate : Truncate table before populating}';

    protected $description = 'Populate counties, county_electoral_divisions, and parishes tables from hierarchy lookup tables';

    public function handle(): int
    {
        $table = $this->option('table');
        $truncate = (bool) $this->option('truncate');

        $this->info('Populating geography lookup tables...');
        $this->newLine();

        // Important: Must populate counties first due to foreign key constraints
        if ($table === 'all' || $table === 'counties') {
            $this->populateCounties($truncate);
            $this->newLine();
        }

        if ($table === 'all' || $table === 'divisions') {
            $this->populateCountyElectoralDivisions($truncate);
            $this->newLine();
        }

        if ($table === 'all' || $table === 'parishes') {
            $this->populateParishes($truncate);
            $this->newLine();
        }

        if (!in_array($table, ['all', 'counties', 'divisions', 'parishes'])) {
            $this->error("Invalid table option: {$table}");
            $this->info('Valid options: all, counties, divisions, parishes');
            return 1;
        }

        $this->info('Geography lookup tables populated successfully!');
        return 0;
    }

    private function populateCounties(bool $truncate): void
    {
        $this->info('Populating counties table...');

        if ($truncate) {
            $this->warn('Truncating counties table...');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('counties')->truncate();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }

        // Get distinct county records from ward_hierarchy_lookups
        $countyCount = DB::table('ward_hierarchy_lookups')
            ->whereNotNull('cty_code')
            ->distinct()
            ->count('cty_code');

        $this->info("Found {$countyCount} distinct counties in hierarchy data");

        // Insert into counties using INSERT ... SELECT
        DB::statement("
            INSERT INTO counties (gss_code, cty25cd, cty25nm, year_code, created_at, updated_at)
            SELECT DISTINCT
                cty_code as gss_code,
                cty_code as cty25cd,
                cty_name as cty25nm,
                '25' as year_code,
                NOW() as created_at,
                NOW() as updated_at
            FROM ward_hierarchy_lookups
            WHERE cty_code IS NOT NULL
            ON CONFLICT (gss_code) DO NOTHING
        ");

        $finalCount = DB::table('counties')->count();
        $this->info("✓ Inserted {$finalCount} counties");

        Log::info('Populated counties table', [
            'count' => $finalCount,
            'truncated' => $truncate,
        ]);
    }

    private function populateCountyElectoralDivisions(bool $truncate): void
    {
        $this->info('Populating county_electoral_divisions table...');

        if ($truncate) {
            $this->warn('Truncating county_electoral_divisions table...');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('county_electoral_divisions')->truncate();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }

        // Get distinct CED records from ward_hierarchy_lookups
        $cedCount = DB::table('ward_hierarchy_lookups')
            ->whereNotNull('ced_code')
            ->distinct()
            ->count('ced_code');

        $this->info("Found {$cedCount} distinct county electoral divisions in hierarchy data");

        // Insert into county_electoral_divisions using INSERT ... SELECT
        DB::statement("
            INSERT INTO county_electoral_divisions (gss_code, ced25cd, ced25nm, cty25cd, year_code, created_at, updated_at)
            SELECT DISTINCT
                ced_code as gss_code,
                ced_code as ced25cd,
                ced_name as ced25nm,
                cty_code as cty25cd,
                '25' as year_code,
                NOW() as created_at,
                NOW() as updated_at
            FROM ward_hierarchy_lookups
            WHERE ced_code IS NOT NULL
            ON CONFLICT (gss_code) DO NOTHING
        ");

        $finalCount = DB::table('county_electoral_divisions')->count();
        $this->info("✓ Inserted {$finalCount} county electoral divisions");

        Log::info('Populated county_electoral_divisions table', [
            'count' => $finalCount,
            'truncated' => $truncate,
        ]);
    }

    private function populateParishes(bool $truncate): void
    {
        $this->info('Populating parishes table...');

        if ($truncate) {
            $this->warn('Truncating parishes table...');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('parishes')->truncate();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }

        // Get distinct parish records from parish_lookups
        $parishCount = DB::table('parish_lookups')
            ->whereNotNull('par_code')
            ->distinct()
            ->count('par_code');

        $this->info("Found {$parishCount} distinct parishes in lookup data");

        // Insert into parishes using INSERT ... SELECT
        DB::statement("
            INSERT INTO parishes (gss_code, parncp25cd, parncp25nm, parncp25nmw, lad25cd, year_code, created_at, updated_at)
            SELECT DISTINCT
                par_code as gss_code,
                par_code as parncp25cd,
                par_name as parncp25nm,
                par_name_welsh as parncp25nmw,
                lad_code as lad25cd,
                '25' as year_code,
                NOW() as created_at,
                NOW() as updated_at
            FROM parish_lookups
            WHERE par_code IS NOT NULL
            ON CONFLICT (gss_code) DO NOTHING
        ");

        $finalCount = DB::table('parishes')->count();
        $this->info("✓ Inserted {$finalCount} parishes");

        Log::info('Populated parishes table', [
            'count' => $finalCount,
            'truncated' => $truncate,
        ]);
    }
}

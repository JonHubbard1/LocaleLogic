<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset Geography Data Command
 *
 * Clears all geography lookup tables and boundary imports to allow fresh data upload.
 * DOES NOT touch the properties (UPRN) table - that data is preserved.
 */
class ResetGeographyData extends Command
{
    protected $signature = 'geography:reset
        {--force : Skip confirmation prompt}';

    protected $description = 'Reset all geography data (keeps UPRN/properties data intact)';

    public function handle(): int
    {
        $this->warn('⚠️  WARNING: This will DELETE all geography data!');
        $this->info('The following will be cleared:');
        $this->line('  • All geography lookup tables (regions, counties, LADs, wards, parishes, CEDs, constituencies, police)');
        $this->line('  • Geography version tracking');
        $this->line('  • Boundary imports and cached polygons');
        $this->line('  • Hierarchy lookups (ward/parish relationships)');
        $this->newLine();
        $this->info('✅ PRESERVED: Properties (UPRN) table with all 41M property records');
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed?', false)) {
                $this->info('Reset cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Starting geography data reset...');
        $this->newLine();

        try {
            DB::beginTransaction();

            // Step 1: Drop foreign keys from properties table temporarily
            $this->info('Dropping foreign key constraints...');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_wd25cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_ced25cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_parncp25cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_lad25cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pcon24cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_rgn25cd_foreign');
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pfa23cd_foreign');
            $this->line('  ✓ Foreign keys dropped');

            // Step 2: Truncate geography lookup tables
            $this->info('Clearing geography lookup tables...');
            DB::statement('TRUNCATE TABLE regions RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE counties RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE local_authority_districts RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE wards RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE parishes RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE county_electoral_divisions RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE constituencies RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE police_force_areas RESTART IDENTITY CASCADE');
            $this->line('  ✓ Geography tables cleared');

            // Step 3: Clear hierarchy lookups
            $this->info('Clearing hierarchy lookup tables...');
            DB::statement('TRUNCATE TABLE ward_hierarchy_lookups RESTART IDENTITY');
            DB::statement('TRUNCATE TABLE parish_lookups RESTART IDENTITY');
            $this->line('  ✓ Hierarchy lookups cleared');

            // Step 4: Clear geography versions
            $this->info('Clearing geography version tracking...');
            DB::statement('TRUNCATE TABLE geography_versions RESTART IDENTITY');
            $this->line('  ✓ Version tracking cleared');

            // Step 5: Clear boundary imports and cached data
            $this->info('Clearing boundary imports and caches...');
            DB::statement('TRUNCATE TABLE boundary_imports RESTART IDENTITY');
            DB::statement('TRUNCATE TABLE boundary_caches RESTART IDENTITY');
            DB::statement('TRUNCATE TABLE boundary_names RESTART IDENTITY');
            DB::statement('TRUNCATE TABLE boundary_geometries RESTART IDENTITY');
            $this->line('  ✓ Boundary data cleared');

            // Step 6: Note about foreign keys
            $this->info('Foreign key constraints will be recreated after geography data is imported');

            DB::commit();

            $this->newLine();
            $this->info('✅ Geography data reset complete!');
            $this->newLine();
            $this->line('Next steps:');
            $this->line('1. Go to https://dev.localelogic.uk/admin/boundaries');
            $this->line('2. Upload all geography files in order:');
            $this->line('   • Regions, Counties, LADs, Wards, Parishes, CEDs, Constituencies, Police Force Areas');
            $this->line('   • Ward Hierarchy Lookup, Parish Lookup');
            $this->line('3. Import Names & Codes CSV files: php artisan geography:import --all');
            $this->newLine();
            $this->warn('⚠️  Note: Properties table foreign keys are disabled until geography data is imported');

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to reset geography data: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

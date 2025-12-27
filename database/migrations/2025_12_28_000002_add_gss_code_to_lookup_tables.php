<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add Year-Agnostic GSS Code Columns to Geography Lookup Tables
 *
 * This migration adds gss_code as the new primary key for all geography lookup tables,
 * making them year-agnostic while preserving year-specific columns for property joins.
 *
 * Tables modified: regions, counties, local_authority_districts, wards, parishes,
 * county_electoral_divisions, constituencies, police_force_areas
 */
return new class extends Migration
{
    public function up(): void
    {
        // Process tables in order to avoid foreign key issues (parents before children)
        // Note: PostgreSQL doesn't support FIRST/AFTER column positioning

        // Step 1: Drop all foreign key constraints that reference primary keys we're changing

        // Drop foreign keys from lookup tables to other lookup tables
        DB::statement('ALTER TABLE local_authority_districts DROP CONSTRAINT IF EXISTS local_authority_districts_rgn25cd_foreign');
        DB::statement('ALTER TABLE wards DROP CONSTRAINT IF EXISTS wards_lad25cd_foreign');
        DB::statement('ALTER TABLE parishes DROP CONSTRAINT IF EXISTS parishes_lad25cd_foreign');
        DB::statement('ALTER TABLE county_electoral_divisions DROP CONSTRAINT IF EXISTS county_electoral_divisions_cty25cd_foreign');

        // Drop foreign keys from properties table to lookup tables
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_wd25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_ced25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_parncp25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_lad25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pcon24cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_rgn25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pfa23cd_foreign');

        // Step 2: Add gss_code and year_code columns, populate them, change primary keys

        // 1. regions
        DB::statement('ALTER TABLE regions ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE regions ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE regions SET gss_code = rgn25cd, year_code = '25'");
        DB::statement('ALTER TABLE regions DROP CONSTRAINT regions_pkey');
        DB::statement('ALTER TABLE regions ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_rgn25cd ON regions (rgn25cd)');

        // 2. counties
        DB::statement('ALTER TABLE counties ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE counties ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE counties SET gss_code = cty25cd, year_code = '25'");
        DB::statement('ALTER TABLE counties DROP CONSTRAINT counties_pkey');
        DB::statement('ALTER TABLE counties ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_cty25cd ON counties (cty25cd)');

        // 3. constituencies
        DB::statement('ALTER TABLE constituencies ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE constituencies ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE constituencies SET gss_code = pcon24cd, year_code = '24'");
        DB::statement('ALTER TABLE constituencies DROP CONSTRAINT constituencies_pkey');
        DB::statement('ALTER TABLE constituencies ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_pcon24cd ON constituencies (pcon24cd)');

        // 4. police_force_areas
        DB::statement('ALTER TABLE police_force_areas ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE police_force_areas ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE police_force_areas SET gss_code = pfa23cd, year_code = '23'");
        DB::statement('ALTER TABLE police_force_areas DROP CONSTRAINT police_force_areas_pkey');
        DB::statement('ALTER TABLE police_force_areas ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_pfa23cd ON police_force_areas (pfa23cd)');

        // 5. local_authority_districts
        DB::statement('ALTER TABLE local_authority_districts ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE local_authority_districts ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE local_authority_districts SET gss_code = lad25cd, year_code = '25'");
        DB::statement('ALTER TABLE local_authority_districts DROP CONSTRAINT local_authority_districts_pkey');
        DB::statement('ALTER TABLE local_authority_districts ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_lad25cd ON local_authority_districts (lad25cd)');

        // 6. wards
        DB::statement('ALTER TABLE wards ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE wards ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE wards SET gss_code = wd25cd, year_code = '25'");
        DB::statement('ALTER TABLE wards DROP CONSTRAINT wards_pkey');
        DB::statement('ALTER TABLE wards ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_wd25cd ON wards (wd25cd)');

        // 7. parishes
        DB::statement('ALTER TABLE parishes ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE parishes ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE parishes SET gss_code = parncp25cd, year_code = '25'");
        DB::statement('ALTER TABLE parishes DROP CONSTRAINT parishes_pkey');
        DB::statement('ALTER TABLE parishes ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_parncp25cd ON parishes (parncp25cd)');

        // 8. county_electoral_divisions
        DB::statement('ALTER TABLE county_electoral_divisions ADD COLUMN gss_code CHAR(9)');
        DB::statement('ALTER TABLE county_electoral_divisions ADD COLUMN year_code CHAR(2)');
        DB::statement("UPDATE county_electoral_divisions SET gss_code = ced25cd, year_code = '25'");
        DB::statement('ALTER TABLE county_electoral_divisions DROP CONSTRAINT county_electoral_divisions_pkey');
        DB::statement('ALTER TABLE county_electoral_divisions ADD PRIMARY KEY (gss_code)');
        DB::statement('CREATE UNIQUE INDEX idx_ced25cd ON county_electoral_divisions (ced25cd)');

        // Step 3: Recreate foreign key constraints (still referencing year-specific columns for property joins)

        // Recreate lookup table foreign keys
        DB::statement('ALTER TABLE local_authority_districts ADD CONSTRAINT local_authority_districts_rgn25cd_foreign FOREIGN KEY (rgn25cd) REFERENCES regions(rgn25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE wards ADD CONSTRAINT wards_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE CASCADE');
        DB::statement('ALTER TABLE parishes ADD CONSTRAINT parishes_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE CASCADE');
        DB::statement('ALTER TABLE county_electoral_divisions ADD CONSTRAINT county_electoral_divisions_cty25cd_foreign FOREIGN KEY (cty25cd) REFERENCES counties(cty25cd) ON DELETE CASCADE');

        // Clean up orphaned records before recreating foreign keys (using NOT EXISTS for performance)
        DB::statement('UPDATE properties p SET wd25cd = NULL WHERE p.wd25cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM wards w WHERE w.wd25cd = p.wd25cd)');
        DB::statement('UPDATE properties p SET ced25cd = NULL WHERE p.ced25cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM county_electoral_divisions c WHERE c.ced25cd = p.ced25cd)');
        DB::statement('UPDATE properties p SET parncp25cd = NULL WHERE p.parncp25cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM parishes pa WHERE pa.parncp25cd = p.parncp25cd)');
        DB::statement('UPDATE properties p SET pcon24cd = NULL WHERE p.pcon24cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM constituencies co WHERE co.pcon24cd = p.pcon24cd)');
        DB::statement('UPDATE properties p SET rgn25cd = NULL WHERE p.rgn25cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM regions r WHERE r.rgn25cd = p.rgn25cd)');
        DB::statement('UPDATE properties p SET pfa23cd = NULL WHERE p.pfa23cd IS NOT NULL AND NOT EXISTS (SELECT 1 FROM police_force_areas pf WHERE pf.pfa23cd = p.pfa23cd)');

        // Recreate properties table foreign keys
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_wd25cd_foreign FOREIGN KEY (wd25cd) REFERENCES wards(wd25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_ced25cd_foreign FOREIGN KEY (ced25cd) REFERENCES county_electoral_divisions(ced25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_parncp25cd_foreign FOREIGN KEY (parncp25cd) REFERENCES parishes(parncp25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_pcon24cd_foreign FOREIGN KEY (pcon24cd) REFERENCES constituencies(pcon24cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_rgn25cd_foreign FOREIGN KEY (rgn25cd) REFERENCES regions(rgn25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_pfa23cd_foreign FOREIGN KEY (pfa23cd) REFERENCES police_force_areas(pfa23cd) ON DELETE SET NULL');
    }

    public function down(): void
    {
        // Reverse order to avoid foreign key issues (children before parents)

        // Step 1: Drop foreign key constraints

        // Drop foreign keys from lookup tables to other lookup tables
        DB::statement('ALTER TABLE local_authority_districts DROP CONSTRAINT IF EXISTS local_authority_districts_rgn25cd_foreign');
        DB::statement('ALTER TABLE wards DROP CONSTRAINT IF EXISTS wards_lad25cd_foreign');
        DB::statement('ALTER TABLE parishes DROP CONSTRAINT IF EXISTS parishes_lad25cd_foreign');
        DB::statement('ALTER TABLE county_electoral_divisions DROP CONSTRAINT IF EXISTS county_electoral_divisions_cty25cd_foreign');

        // Drop foreign keys from properties table to lookup tables
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_wd25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_ced25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_parncp25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_lad25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pcon24cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_rgn25cd_foreign');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_pfa23cd_foreign');

        // Step 2: Revert primary keys and drop gss_code columns (reverse order)

        // 8. county_electoral_divisions
        DB::statement('DROP INDEX IF EXISTS idx_ced25cd');
        DB::statement('ALTER TABLE county_electoral_divisions DROP CONSTRAINT county_electoral_divisions_pkey');
        DB::statement('ALTER TABLE county_electoral_divisions ADD PRIMARY KEY (ced25cd)');
        DB::statement('ALTER TABLE county_electoral_divisions DROP COLUMN gss_code');
        DB::statement('ALTER TABLE county_electoral_divisions DROP COLUMN year_code');

        // 7. parishes
        DB::statement('DROP INDEX IF EXISTS idx_parncp25cd');
        DB::statement('ALTER TABLE parishes DROP CONSTRAINT parishes_pkey');
        DB::statement('ALTER TABLE parishes ADD PRIMARY KEY (parncp25cd)');
        DB::statement('ALTER TABLE parishes DROP COLUMN gss_code');
        DB::statement('ALTER TABLE parishes DROP COLUMN year_code');

        // 6. wards
        DB::statement('DROP INDEX IF EXISTS idx_wd25cd');
        DB::statement('ALTER TABLE wards DROP CONSTRAINT wards_pkey');
        DB::statement('ALTER TABLE wards ADD PRIMARY KEY (wd25cd)');
        DB::statement('ALTER TABLE wards DROP COLUMN gss_code');
        DB::statement('ALTER TABLE wards DROP COLUMN year_code');

        // 5. local_authority_districts
        DB::statement('DROP INDEX IF EXISTS idx_lad25cd');
        DB::statement('ALTER TABLE local_authority_districts DROP CONSTRAINT local_authority_districts_pkey');
        DB::statement('ALTER TABLE local_authority_districts ADD PRIMARY KEY (lad25cd)');
        DB::statement('ALTER TABLE local_authority_districts DROP COLUMN gss_code');
        DB::statement('ALTER TABLE local_authority_districts DROP COLUMN year_code');

        // 4. police_force_areas
        DB::statement('DROP INDEX IF EXISTS idx_pfa23cd');
        DB::statement('ALTER TABLE police_force_areas DROP CONSTRAINT police_force_areas_pkey');
        DB::statement('ALTER TABLE police_force_areas ADD PRIMARY KEY (pfa23cd)');
        DB::statement('ALTER TABLE police_force_areas DROP COLUMN gss_code');
        DB::statement('ALTER TABLE police_force_areas DROP COLUMN year_code');

        // 3. constituencies
        DB::statement('DROP INDEX IF EXISTS idx_pcon24cd');
        DB::statement('ALTER TABLE constituencies DROP CONSTRAINT constituencies_pkey');
        DB::statement('ALTER TABLE constituencies ADD PRIMARY KEY (pcon24cd)');
        DB::statement('ALTER TABLE constituencies DROP COLUMN gss_code');
        DB::statement('ALTER TABLE constituencies DROP COLUMN year_code');

        // 2. counties
        DB::statement('DROP INDEX IF EXISTS idx_cty25cd');
        DB::statement('ALTER TABLE counties DROP CONSTRAINT counties_pkey');
        DB::statement('ALTER TABLE counties ADD PRIMARY KEY (cty25cd)');
        DB::statement('ALTER TABLE counties DROP COLUMN gss_code');
        DB::statement('ALTER TABLE counties DROP COLUMN year_code');

        // 1. regions
        DB::statement('DROP INDEX IF EXISTS idx_rgn25cd');
        DB::statement('ALTER TABLE regions DROP CONSTRAINT regions_pkey');
        DB::statement('ALTER TABLE regions ADD PRIMARY KEY (rgn25cd)');
        DB::statement('ALTER TABLE regions DROP COLUMN gss_code');
        DB::statement('ALTER TABLE regions DROP COLUMN year_code');

        // Step 3: Recreate foreign key constraints

        // Recreate lookup table foreign keys
        DB::statement('ALTER TABLE local_authority_districts ADD CONSTRAINT local_authority_districts_rgn25cd_foreign FOREIGN KEY (rgn25cd) REFERENCES regions(rgn25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE wards ADD CONSTRAINT wards_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE CASCADE');
        DB::statement('ALTER TABLE parishes ADD CONSTRAINT parishes_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE CASCADE');
        DB::statement('ALTER TABLE county_electoral_divisions ADD CONSTRAINT county_electoral_divisions_cty25cd_foreign FOREIGN KEY (cty25cd) REFERENCES counties(cty25cd) ON DELETE CASCADE');

        // Recreate properties table foreign keys
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_wd25cd_foreign FOREIGN KEY (wd25cd) REFERENCES wards(wd25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_ced25cd_foreign FOREIGN KEY (ced25cd) REFERENCES county_electoral_divisions(ced25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_parncp25cd_foreign FOREIGN KEY (parncp25cd) REFERENCES parishes(parncp25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_lad25cd_foreign FOREIGN KEY (lad25cd) REFERENCES local_authority_districts(lad25cd) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_pcon24cd_foreign FOREIGN KEY (pcon24cd) REFERENCES constituencies(pcon24cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_rgn25cd_foreign FOREIGN KEY (rgn25cd) REFERENCES regions(rgn25cd) ON DELETE SET NULL');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_pfa23cd_foreign FOREIGN KEY (pfa23cd) REFERENCES police_force_areas(pfa23cd) ON DELETE SET NULL');
    }
};

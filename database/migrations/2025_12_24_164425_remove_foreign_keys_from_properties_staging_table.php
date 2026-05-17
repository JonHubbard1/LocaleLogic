<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove Foreign Keys from Properties Staging Table
 *
 * Staging tables should NOT have foreign key constraints.
 * This allows data to be loaded without requiring all referenced codes to exist first.
 * Only the production 'properties' table should have foreign key constraints.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $constraints = [
            'properties_staging_wd25cd_foreign',
            'properties_staging_ced25cd_foreign',
            'properties_staging_parncp25cd_foreign',
            'properties_staging_lad25cd_foreign',
            'properties_staging_pcon24cd_foreign',
            'properties_staging_rgn25cd_foreign',
            'properties_staging_pfa23cd_foreign',
        ];

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE properties_staging DROP CONSTRAINT IF EXISTS {$constraint}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_staging', function (Blueprint $table) {
            // Re-add foreign key constraints (for rollback only)
            $table->foreign('wd25cd')
                  ->references('wd25cd')
                  ->on('wards')
                  ->onDelete('set null');

            $table->foreign('ced25cd')
                  ->references('ced25cd')
                  ->on('county_electoral_divisions')
                  ->onDelete('set null');

            $table->foreign('parncp25cd')
                  ->references('parncp25cd')
                  ->on('parishes')
                  ->onDelete('set null');

            $table->foreign('lad25cd')
                  ->references('lad25cd')
                  ->on('local_authority_districts')
                  ->onDelete('restrict');

            $table->foreign('pcon24cd')
                  ->references('pcon24cd')
                  ->on('constituencies')
                  ->onDelete('set null');

            $table->foreign('rgn25cd')
                  ->references('rgn25cd')
                  ->on('regions')
                  ->onDelete('set null');

            $table->foreign('pfa23cd')
                  ->references('pfa23cd')
                  ->on('police_force_areas')
                  ->onDelete('set null');
        });
    }
};

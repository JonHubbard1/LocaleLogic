<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('properties_staging', function (Blueprint $table) {
            // Drop all foreign key constraints
            $table->dropForeign(['wd25cd']);
            $table->dropForeign(['ced25cd']);
            $table->dropForeign(['parncp25cd']);
            $table->dropForeign(['lad25cd']);
            $table->dropForeign(['pcon24cd']);
            $table->dropForeign(['rgn25cd']);
            $table->dropForeign(['pfa23cd']);
        });
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

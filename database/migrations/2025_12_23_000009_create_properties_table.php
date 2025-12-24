<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Properties Table Migration
 *
 * Creates the core properties table for storing 41 million UPRN records from ONSUD.
 * Uses UPRN as primary key, stores both OS Grid and WGS84 coordinates,
 * and includes geography code columns linking to lookup tables.
 * NO timestamps for performance optimization on large table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            // Primary key: UPRN (Unique Property Reference Number)
            $table->bigInteger('uprn')->primary()->comment('UPRN - Unique Property Reference Number');

            // Postcode
            $table->string('pcds', 8)->comment('Postcode (normalized uppercase with space)');

            // OS Grid coordinates (British National Grid - EPSG:27700)
            $table->integer('gridgb1e')->comment('OS Grid Easting');
            $table->integer('gridgb1n')->comment('OS Grid Northing');

            // WGS84 coordinates (EPSG:4326)
            $table->decimal('lat', 9, 6)->comment('Latitude (WGS84)');
            $table->decimal('lng', 9, 6)->comment('Longitude (WGS84)');

            // Geography codes (GSS codes) - nullable except lad25cd
            $table->char('wd25cd', 9)->nullable()->comment('Ward GSS code');
            $table->char('ced25cd', 9)->nullable()->comment('County Electoral Division GSS code');
            $table->char('parncp25cd', 9)->nullable()->comment('Parish GSS code');
            $table->char('lad25cd', 9)->comment('Local Authority District GSS code (required)');
            $table->char('pcon24cd', 9)->nullable()->comment('Westminster Constituency GSS code');
            $table->char('lsoa21cd', 9)->nullable()->comment('LSOA GSS code');
            $table->char('msoa21cd', 9)->nullable()->comment('MSOA GSS code');
            $table->char('rgn25cd', 9)->nullable()->comment('Region GSS code');
            $table->char('ruc21ind', 9)->nullable()->comment('Rural/Urban Classification');
            $table->char('pfa23cd', 9)->nullable()->comment('Police Force Area GSS code');

            // NO timestamps - performance optimization for 41M rows
            // NO soft deletes - out of scope per spec

            // Foreign key constraints to lookup tables
            // Optional: Consider performance impact on 41M row table
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

        // NOTE: Indexes are NOT created here for performance reasons
        // They will be created in a separate migration AFTER bulk data import
        // See migration: create_properties_indexes.php (to be run post-import)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

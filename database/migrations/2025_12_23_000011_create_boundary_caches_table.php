<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Boundary Caches Table Migration
 *
 * Creates the boundary_caches table to store GeoJSON polygons fetched from ONS Open Geography Portal API.
 * Supports caching of boundaries for ward, ced, parish, lad, constituency, county, pfa, and region.
 * Unique constraint on (geography_type, geography_code, boundary_resolution) prevents duplicates.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boundary_caches', function (Blueprint $table) {
            $table->id()->comment('Auto-incrementing primary key');
            $table->string('geography_type', 20)->comment('Geography type: ward, ced, parish, lad, constituency, county, pfa, region');
            $table->char('geography_code', 9)->comment('GSS code for the geography');
            $table->string('boundary_resolution', 10)->default('BFC')->comment('Boundary resolution: BFC (Full resolution, Clipped to coastline)');
            $table->text('geojson')->comment('GeoJSON polygon from ONS Open Geography Portal');
            $table->timestamp('fetched_at')->comment('When the boundary was fetched');
            $table->timestamp('expires_at')->nullable()->comment('Cache expiry timestamp');
            $table->string('source_url', 500)->nullable()->comment('Source URL from ONS API');
            $table->timestamps();

            // Unique constraint to prevent duplicate boundary entries
            $table->unique(['geography_type', 'geography_code', 'boundary_resolution'], 'idx_boundary_unique');

            // Index on expires_at for efficient cache expiry queries
            $table->index('expires_at', 'idx_boundary_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_caches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boundary_geometries', function (Blueprint $table) {
            $table->id();
            $table->string('boundary_type', 50)->index(); // ward, parish, lad, etc.
            $table->string('gss_code', 9)->index(); // ONS GSS code
            $table->string('name'); // Boundary name (denormalized for convenience)
            $table->jsonb('geometry'); // GeoJSON geometry (Polygon or MultiPolygon)
            $table->jsonb('properties')->nullable(); // Additional properties from GeoJSON
            $table->decimal('area_hectares', 12, 2)->nullable(); // Area in hectares
            $table->string('bounding_box', 255)->nullable(); // Min/max lat/lng for quick filtering
            $table->string('source_file')->nullable(); // Original filename
            $table->date('version_date')->nullable(); // Data version date
            $table->timestamps();

            // Unique constraint: one geometry per boundary type + code
            $table->unique(['boundary_type', 'gss_code']);

            // Index for spatial queries (can upgrade to PostGIS GIST index later)
            $table->index(['boundary_type', 'gss_code']);
        });

        // Add comment explaining geometry format
        DB::statement("COMMENT ON COLUMN boundary_geometries.geometry IS 'GeoJSON geometry object (Polygon or MultiPolygon). Can be migrated to PostGIS geometry type for advanced spatial queries.'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_geometries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds PostGIS geometry column for spatial queries.
     */
    public function up(): void
    {
        // Add PostGIS geometry column (keeping the JSONB for backwards compatibility)
        DB::statement("ALTER TABLE boundary_geometries ADD COLUMN geom geometry(Geometry, 4326)");

        // Create spatial index for fast point-in-polygon queries
        DB::statement("CREATE INDEX boundary_geometries_geom_idx ON boundary_geometries USING GIST (geom)");

        // Populate geometry column from existing JSONB geometry data
        DB::statement("
            UPDATE boundary_geometries
            SET geom = ST_SetSRID(ST_GeomFromGeoJSON(geometry::text), 4326)
            WHERE geometry IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS boundary_geometries_geom_idx");
        DB::statement("ALTER TABLE boundary_geometries DROP COLUMN IF EXISTS geom");
    }
};

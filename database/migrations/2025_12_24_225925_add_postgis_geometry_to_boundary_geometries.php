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
        $tableExists = DB::select("SELECT to_regclass('public.boundary_geometries') as exists")[0]->exists;

        if (! $tableExists) {
            return;
        }

        // Check whether geom already exists (avoids ownership errors on shared hosting)
        $columnExists = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_name = 'boundary_geometries' AND column_name = 'geom'"
        );

        if (! $columnExists) {
            DB::statement("ALTER TABLE boundary_geometries ADD COLUMN geom geometry(Geometry, 4326)");
        }

        $indexExists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'boundary_geometries_geom_idx'"
        );

        if (! $indexExists) {
            DB::statement("CREATE INDEX boundary_geometries_geom_idx ON boundary_geometries USING GIST (geom)");
        }

        DB::statement("
            UPDATE boundary_geometries
            SET geom = ST_SetSRID(ST_GeomFromGeoJSON(geometry::text), 4326)
            WHERE geometry IS NOT NULL AND geom IS NULL
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

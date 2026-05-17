<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run outside a transaction so CREATE INDEX CONCURRENTLY can be used.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        $existing = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND indexname = 'idx_properties_geom'"
        );
        if ($existing) {
            return;
        }

        $original = DB::selectOne("SHOW maintenance_work_mem")->maintenance_work_mem;
        DB::statement("SET maintenance_work_mem = '512MB'");

        DB::statement(
            'CREATE INDEX CONCURRENTLY idx_properties_geom ON properties USING GIST (ST_SetSRID(ST_MakePoint(lng, lat), 4326))'
        );

        DB::statement("SET maintenance_work_mem = '{$original}'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_properties_geom');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'data_versions'
               AND column_name = 'imported_at'"
        );

        if ($isNullable && $isNullable->is_nullable === 'YES') {
            return;
        }

        DB::statement("ALTER TABLE data_versions ALTER COLUMN imported_at DROP NOT NULL");
    }

    public function down(): void
    {
        // Reverting is unsafe if nulls exist — skip
    }
};

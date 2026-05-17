<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('data_versions')) {
            return;
        }

        $isNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns
             WHERE table_name = 'data_versions' AND column_name = 'imported_at'"
        );

        if ($isNullable && $isNullable->is_nullable === 'YES') {
            return;
        }

        Schema::table('data_versions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('data_versions')) {
            return;
        }

        Schema::table('data_versions', function (\Illuminate\Database\Schema\Blueprint $table) {
            // Reverting nullable to NOT NULL is unsafe if nulls exist;
            // leave as nullable for safety.
        });
    }
};

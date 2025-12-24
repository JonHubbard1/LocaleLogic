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
        // Check if index exists before creating
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'idx_properties_pcds'")) {
            Schema::table('properties', function (Blueprint $table) {
                $table->index('pcds', 'idx_properties_pcds');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('idx_properties_pcds');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasSteps = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'data_versions'
               AND column_name = 'steps'"
        );

        if (! $hasSteps) {
            DB::statement("ALTER TABLE data_versions ADD COLUMN steps JSON NULL");
        }

        $hasCurrentStep = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'data_versions'
               AND column_name = 'current_step'"
        );

        if (! $hasCurrentStep) {
            DB::statement("ALTER TABLE data_versions ADD COLUMN current_step VARCHAR(20) NULL");
        }
    }

    public function down(): void
    {
        // Reverting is unsafe on shared hosting — skip
    }
};

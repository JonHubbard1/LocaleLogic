<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parish_lookups', function (Blueprint $table) {
            // Drop the old unique constraint (par_code + version_date)
            $table->dropUnique(['par_code', 'version_date']);

            // Add new unique constraint including ward code
            // One parish can belong to multiple wards, so we need par_code + wd_code + version_date
            $table->unique(['par_code', 'wd_code', 'version_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parish_lookups', function (Blueprint $table) {
            // Revert back to original constraint
            $table->dropUnique(['par_code', 'wd_code', 'version_date']);
            $table->unique(['par_code', 'version_date']);
        });
    }
};

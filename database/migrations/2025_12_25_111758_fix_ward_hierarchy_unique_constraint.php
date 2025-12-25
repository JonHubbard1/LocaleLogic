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
        Schema::table('ward_hierarchy_lookups', function (Blueprint $table) {
            // Drop the old unique constraint (wd_code + version_date)
            $table->dropUnique(['wd_code', 'version_date']);

            // Add new unique constraint including CED code
            // One ward can belong to multiple CEDs (County Electoral Divisions),
            // so we need wd_code + ced_code + version_date
            // For wards without a CED (metropolitan areas), ced_code will be NULL,
            // and PostgreSQL allows multiple NULLs in unique constraints
            $table->unique(['wd_code', 'ced_code', 'version_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_hierarchy_lookups', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique(['wd_code', 'ced_code', 'version_date']);

            // Restore the original constraint
            $table->unique(['wd_code', 'version_date']);
        });
    }
};

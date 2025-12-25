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
        // Laravel's enum() creates a CHECK constraint in PostgreSQL, not a true enum type
        // We need to drop the old constraint and create a new one with 'lookups' added
        DB::statement("ALTER TABLE boundary_imports DROP CONSTRAINT boundary_imports_data_type_check");
        DB::statement("ALTER TABLE boundary_imports ADD CONSTRAINT boundary_imports_data_type_check CHECK (data_type IN ('names', 'polygons', 'lookups'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original constraint without 'lookups'
        DB::statement("ALTER TABLE boundary_imports DROP CONSTRAINT boundary_imports_data_type_check");
        DB::statement("ALTER TABLE boundary_imports ADD CONSTRAINT boundary_imports_data_type_check CHECK (data_type IN ('names', 'polygons'))");
    }
};

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

        $columns = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'data_versions'
               AND column_name IN ('steps', 'current_step')"
        );

        $existing = array_map(fn ($c) => $c->column_name, $columns);

        Schema::table('data_versions', function (\Illuminate\Database\Schema\Blueprint $table) use ($existing) {
            if (! in_array('steps', $existing, true)) {
                $table->json('steps')->nullable()->after('stats');
            }
            if (! in_array('current_step', $existing, true)) {
                $table->string('current_step', 20)->nullable()->after('steps');
            }
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
            $table->dropColumn(['steps', 'current_step']);
        });
    }
};

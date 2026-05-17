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
        Schema::table('data_versions', function (Blueprint $table) {
            $table->json('steps')->nullable()->after('stats');
            $table->string('current_step', 20)->nullable()->after('steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_versions', function (Blueprint $table) {
            $table->dropColumn(['steps', 'current_step']);
        });
    }
};

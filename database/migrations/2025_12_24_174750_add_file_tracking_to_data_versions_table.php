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
            $table->json('files')->nullable()->after('total_files');
            $table->text('log_file')->nullable()->after('files');
            $table->json('stats')->nullable()->after('log_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_versions', function (Blueprint $table) {
            $table->dropColumn(['files', 'log_file', 'stats']);
        });
    }
};

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
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('status');
            $table->string('status_message', 500)->nullable()->after('progress_percentage');
            $table->integer('current_file')->default(1)->after('status_message');
            $table->integer('total_files')->default(1)->after('current_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_versions', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'status_message', 'current_file', 'total_files']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Data Versions Table Migration
 *
 * Creates the data_versions table to track ONSUD import history and current version.
 * Enables version tracking for 6-weekly ONSUD updates and rollback identification.
 * Unique constraint on (dataset, epoch) prevents duplicate version entries.
 * Status values: importing, current, archived, failed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_versions', function (Blueprint $table) {
            $table->id()->comment('Auto-incrementing primary key');
            $table->string('dataset', 20)->comment('Dataset name: ONSUD, Ward Lookup, etc.');
            $table->integer('epoch')->comment('Version number');
            $table->date('release_date')->comment('Dataset release date');
            $table->timestamp('imported_at')->comment('When the import completed');
            $table->integer('record_count')->nullable()->comment('Number of records imported');
            $table->string('file_hash', 64)->nullable()->comment('SHA-256 hash for verification');
            $table->string('status', 20)->default('current')->comment('Status: importing, current, archived, failed');
            $table->text('notes')->nullable()->comment('Additional notes about this version');
            $table->timestamps();

            // Unique constraint to prevent duplicate dataset version entries
            $table->unique(['dataset', 'epoch'], 'idx_dataset_epoch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_versions');
    }
};

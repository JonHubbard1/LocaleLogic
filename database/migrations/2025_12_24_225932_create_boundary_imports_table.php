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
        Schema::create('boundary_imports', function (Blueprint $table) {
            $table->id();
            $table->string('boundary_type', 50)->index(); // ward, parish, lad, etc.
            $table->enum('data_type', ['names', 'polygons']); // What type of data was imported
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('source', 100); // onsud_csv, downloaded_geojson, url_download, etc.
            $table->string('file_path')->nullable(); // Path to the source file
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->integer('records_total')->default(0); // Total records to process
            $table->integer('records_processed')->default(0); // Successfully processed
            $table->integer('records_failed')->default(0); // Failed to process
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable(); // Error details if failed
            $table->jsonb('metadata')->nullable(); // Additional import metadata
            $table->timestamps();

            // Indexes for querying import history
            $table->index(['boundary_type', 'data_type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_imports');
    }
};

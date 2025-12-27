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
        Schema::create('geography_versions', function (Blueprint $table) {
            $table->id();
            $table->string('geography_type', 50)->comment('Stores geography types: lad, ward, parish, ced, constituency, region, pfa');
            $table->char('year_code', 2)->comment('Year code like 25, 26, 27');
            $table->date('release_date')->comment('ONS release date');
            $table->timestamp('imported_at')->nullable()->comment('When the data was imported');
            $table->integer('record_count')->comment('Number of records imported');
            $table->string('source_file', 255)->nullable()->comment('CSV filename');
            $table->enum('status', ['current', 'archived', 'importing'])->default('importing')->comment('Import status');
            $table->timestamps();

            // Unique constraint on geography_type and year_code combination
            $table->unique(['geography_type', 'year_code'], 'uk_geography_type_year_code');

            // Index on geography_type for faster queries
            $table->index('geography_type', 'idx_geography_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geography_versions');
    }
};

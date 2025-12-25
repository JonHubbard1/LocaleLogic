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
        Schema::create('boundary_names', function (Blueprint $table) {
            $table->id();
            $table->string('boundary_type', 50)->index(); // ward, parish, lad, etc.
            $table->string('gss_code', 9)->index(); // ONS GSS code (e.g., E05001234)
            $table->string('name'); // English name
            $table->string('name_welsh')->nullable(); // Welsh name (for Welsh boundaries)
            $table->string('source', 50); // onsud_csv, geojson, manual, etc.
            $table->date('version_date')->nullable(); // Date of the data version (e.g., May 2025)
            $table->timestamps();

            // Unique constraint: one name per boundary type + code combination
            $table->unique(['boundary_type', 'gss_code']);

            // Composite index for fast lookups
            $table->index(['boundary_type', 'gss_code', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_names');
    }
};

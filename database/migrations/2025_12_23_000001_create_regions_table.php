<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Regions Table Migration
 *
 * Creates the regions lookup table for storing UK geographical regions.
 * Approximately 12 region records for England, Wales, Scotland.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->char('rgn25cd', 9)->primary()->comment('Region GSS code');
            $table->string('rgn25nm', 50)->comment('Region name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};

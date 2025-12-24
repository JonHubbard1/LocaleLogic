<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Constituencies Table Migration
 *
 * Creates the constituencies lookup table for UK Westminster constituencies.
 * Approximately 650 Westminster constituency records from ONS lookup data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('constituencies', function (Blueprint $table) {
            $table->char('pcon24cd', 9)->primary()->comment('Constituency GSS code');
            $table->string('pcon24nm', 100)->comment('Constituency name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('constituencies');
    }
};

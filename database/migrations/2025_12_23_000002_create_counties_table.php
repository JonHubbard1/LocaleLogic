<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Counties Table Migration
 *
 * Creates the counties lookup table for storing UK counties.
 * Approximately 30 county records from ONS lookup data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $table) {
            $table->char('cty25cd', 9)->primary()->comment('County GSS code');
            $table->string('cty25nm', 100)->comment('County name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};

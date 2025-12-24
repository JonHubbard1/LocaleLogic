<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Police Force Areas Table Migration
 *
 * Creates the police force areas lookup table.
 * 44 police force area records from ONS lookup data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('police_force_areas', function (Blueprint $table) {
            $table->char('pfa23cd', 9)->primary()->comment('PFA GSS code');
            $table->string('pfa23nm', 100)->comment('PFA name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('police_force_areas');
    }
};

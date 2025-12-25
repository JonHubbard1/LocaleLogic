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
        Schema::create('parish_lookups', function (Blueprint $table) {
            $table->id();

            // Parish details
            $table->string('par_code', 9)->index();
            $table->string('par_name');
            $table->string('par_name_welsh')->nullable();

            // Ward details
            $table->string('wd_code', 9)->index();
            $table->string('wd_name');
            $table->string('wd_name_welsh')->nullable();

            // Local Authority District details
            $table->string('lad_code', 9)->index();
            $table->string('lad_name');
            $table->string('lad_name_welsh')->nullable();

            // Metadata
            $table->date('version_date')->nullable();
            $table->string('source', 100)->default('ons_lookup');

            $table->timestamps();

            // Composite indexes for lookups
            $table->index(['par_code', 'wd_code']);
            $table->index(['wd_code', 'lad_code']);
            $table->unique(['par_code', 'version_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parish_lookups');
    }
};

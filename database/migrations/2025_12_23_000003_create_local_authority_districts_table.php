<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Local Authority Districts Table Migration
 *
 * Creates the local authority districts lookup table with optional Welsh names.
 * Approximately 350 LAD records from ONS lookup data.
 * References regions table for hierarchical relationship.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('local_authority_districts', function (Blueprint $table) {
            $table->char('lad25cd', 9)->primary()->comment('LAD GSS code');
            $table->string('lad25nm', 100)->comment('LAD name (English)');
            $table->string('lad25nmw', 100)->nullable()->comment('LAD name (Welsh)');
            $table->char('rgn25cd', 9)->nullable()->comment('Region GSS code');
            $table->timestamps();

            // Foreign key constraint to regions table
            $table->foreign('rgn25cd')
                  ->references('rgn25cd')
                  ->on('regions')
                  ->onDelete('set null');

            // Index on foreign key for efficient relationship queries
            $table->index('rgn25cd', 'idx_lad_rgn25cd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_authority_districts');
    }
};

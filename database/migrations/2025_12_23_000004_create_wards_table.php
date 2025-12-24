<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Wards Table Migration
 *
 * Creates the wards lookup table for storing UK electoral wards.
 * Approximately 9,000 ward records from ONS lookup data.
 * References local_authority_districts table for hierarchical relationship.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->char('wd25cd', 9)->primary()->comment('Ward GSS code');
            $table->string('wd25nm', 100)->comment('Ward name');
            $table->char('lad25cd', 9)->comment('LAD GSS code');
            $table->timestamps();

            // Foreign key constraint to local_authority_districts table
            $table->foreign('lad25cd')
                  ->references('lad25cd')
                  ->on('local_authority_districts')
                  ->onDelete('cascade');

            // Index on foreign key for efficient relationship queries
            $table->index('lad25cd', 'idx_wards_lad25cd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};

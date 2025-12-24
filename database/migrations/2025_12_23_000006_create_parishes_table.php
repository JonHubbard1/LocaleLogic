<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Parishes Table Migration
 *
 * Creates the parishes lookup table with optional Welsh names.
 * Approximately 11,000 parish records from ONS lookup data.
 * References local_authority_districts table for hierarchical relationship.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parishes', function (Blueprint $table) {
            $table->char('parncp25cd', 9)->primary()->comment('Parish GSS code');
            $table->string('parncp25nm', 100)->comment('Parish name (English)');
            $table->string('parncp25nmw', 100)->nullable()->comment('Parish name (Welsh)');
            $table->char('lad25cd', 9)->comment('LAD GSS code');
            $table->timestamps();

            // Foreign key constraint to local_authority_districts table
            $table->foreign('lad25cd')
                  ->references('lad25cd')
                  ->on('local_authority_districts')
                  ->onDelete('cascade');

            // Index on foreign key for efficient relationship queries
            $table->index('lad25cd', 'idx_parishes_lad25cd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parishes');
    }
};

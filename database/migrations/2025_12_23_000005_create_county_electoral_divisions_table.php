<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create County Electoral Divisions Table Migration
 *
 * Creates the county electoral divisions lookup table.
 * Approximately 1,400 CED records from ONS lookup data.
 * References counties table for hierarchical relationship.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('county_electoral_divisions', function (Blueprint $table) {
            $table->char('ced25cd', 9)->primary()->comment('CED GSS code');
            $table->string('ced25nm', 100)->comment('CED name');
            $table->char('cty25cd', 9)->comment('County GSS code');
            $table->timestamps();

            // Foreign key constraint to counties table
            $table->foreign('cty25cd')
                  ->references('cty25cd')
                  ->on('counties')
                  ->onDelete('cascade');

            // Index on foreign key for efficient relationship queries
            $table->index('cty25cd', 'idx_ced_cty25cd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('county_electoral_divisions');
    }
};

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
        Schema::create('ward_hierarchy_lookups', function (Blueprint $table) {
            $table->id();

            // Ward details
            $table->string('wd_code', 9)->index();
            $table->string('wd_name');

            // Local Authority District details
            $table->string('lad_code', 9)->index();
            $table->string('lad_name');

            // County details (nullable - not all areas have counties)
            $table->string('cty_code', 9)->nullable()->index();
            $table->string('cty_name')->nullable();

            // County Electoral Division details (nullable - England only)
            $table->string('ced_code', 9)->nullable()->index();
            $table->string('ced_name')->nullable();

            // Metadata
            $table->date('version_date')->nullable();
            $table->string('source', 100)->default('ons_lookup');

            $table->timestamps();

            // Composite index for lookups
            $table->index(['wd_code', 'lad_code']);
            $table->unique(['wd_code', 'version_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_hierarchy_lookups');
    }
};

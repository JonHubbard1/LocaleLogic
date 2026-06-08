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
        Schema::create('councils', function (Blueprint $table) {
            $table->string('gss_code', 9)->primary(); // ONS GSS code (e.g., E06000054)
            $table->string('name'); // English name
            $table->string('name_welsh')->nullable(); // Welsh name
            $table->string('council_type', 30)->index(); // unitary, district, london_borough, county, metropolitan, scottish, welsh, ni
            $table->string('nation', 20)->index(); // england, scotland, wales, northern_ireland
            $table->string('region')->nullable(); // Human-readable region
            $table->boolean('uses_modern_gov')->nullable(); // null = unknown, true = yes, false = no
            $table->string('modern_gov_base_url')->nullable();
            $table->string('democracy_url')->nullable(); // Generic councillor-list page
            $table->string('website_url')->nullable();
            $table->string('source', 50)->nullable(); // How we discovered it
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            // Indexes for common admin filters
            $table->index(['council_type', 'nation']);
            $table->index('uses_modern_gov');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('councils');
    }
};

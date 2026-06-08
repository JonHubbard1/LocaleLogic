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
        Schema::create('councillors', function (Blueprint $table) {
            $table->id();
            $table->string('council_gss_code', 9)->index();
            $table->string('ward_gss_code', 9)->index(); // References ward_hierarchy_lookups.wd_code
            $table->string('name');
            $table->string('party', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('profile_url')->nullable();
            $table->string('source', 50)->nullable(); // democracy_club, modern_gov, manual
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            // Foreign key to councils
            $table->foreign('council_gss_code')
                ->references('gss_code')
                ->on('councils')
                ->onDelete('cascade');

            // Composite index for ward lookups
            $table->index(['ward_gss_code', 'council_gss_code']);

            // Prevent duplicate councillors per ward from the same source
            $table->unique(['ward_gss_code', 'name', 'source'], 'councillors_ward_name_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('councillors');
    }
};

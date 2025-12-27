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
        Schema::create('boundary_update_checks', function (Blueprint $table) {
            $table->id();
            $table->string('boundary_type');
            $table->date('expected_version'); // The version we checked for
            $table->date('checked_at'); // When we checked
            $table->string('checked_by')->nullable(); // User who checked
            $table->text('notes')->nullable(); // Optional notes
            $table->timestamps();

            $table->unique(['boundary_type', 'expected_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_update_checks');
    }
};

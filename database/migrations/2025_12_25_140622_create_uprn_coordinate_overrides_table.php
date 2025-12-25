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
        Schema::create('uprn_coordinate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('uprn', 12)->index();
            $table->decimal('override_lat', 10, 8);
            $table->decimal('override_lng', 11, 8);
            $table->timestamps();

            // Ensure one override per UPRN per user
            $table->unique(['user_id', 'uprn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uprn_coordinate_overrides');
    }
};

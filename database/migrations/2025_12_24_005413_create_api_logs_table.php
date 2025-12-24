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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();

            // Request details
            $table->string('method', 10)->index();
            $table->string('path')->index();
            $table->text('url');
            $table->json('query_params')->nullable();

            // User information
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_email')->nullable();

            // Client information
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();

            // Response details
            $table->unsignedSmallInteger('status_code')->index();
            $table->decimal('duration_ms', 10, 2);

            // Error tracking
            $table->string('error_code', 50)->nullable()->index();
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamp('created_at')->index();

            // Indexes for common queries
            $table->index(['created_at', 'status_code']);
            $table->index(['created_at', 'user_id']);
            $table->index(['path', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};

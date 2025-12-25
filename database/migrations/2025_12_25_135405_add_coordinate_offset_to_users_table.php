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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('coordinate_offset_lat', 10, 8)->default(0)->after('email');
            $table->decimal('coordinate_offset_lng', 11, 8)->default(0)->after('coordinate_offset_lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['coordinate_offset_lat', 'coordinate_offset_lng']);
        });
    }
};

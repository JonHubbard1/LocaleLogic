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
        Schema::table('councils', function (Blueprint $table) {
            $table->boolean('uses_democracy_club')->nullable()->after('uses_modern_gov');
            $table->string('democracy_club_org_id', 50)->nullable()->after('uses_democracy_club');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('councils', function (Blueprint $table) {
            $table->dropColumn('uses_democracy_club');
            $table->dropColumn('democracy_club_org_id');
        });
    }
};

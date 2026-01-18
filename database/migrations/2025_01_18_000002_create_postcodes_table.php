<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates postcodes table from NSPL data with PostGIS point geometry.
     */
    public function up(): void
    {
        Schema::create('postcodes', function (Blueprint $table) {
            $table->string('pcd7', 7)->primary(); // Postcode without space (e.g., "SN127LA")
            $table->string('pcd8', 8)->index(); // Postcode with space in fixed position (e.g., "SN12 7LA")
            $table->string('pcds', 8)->index(); // Postcode with space, variable (e.g., "SN12 7LA")
            $table->string('dointr', 6)->nullable(); // Date of introduction (YYYYMM)
            $table->string('doterm', 6)->nullable(); // Date of termination (YYYYMM, null if live)
            $table->decimal('lat', 9, 6)->nullable(); // WGS84 Latitude
            $table->decimal('lng', 10, 6)->nullable(); // WGS84 Longitude
            $table->integer('east1m')->nullable(); // OS Grid Easting
            $table->integer('north1m')->nullable(); // OS Grid Northing

            // Geographic assignments from NSPL
            $table->string('oa21cd', 9)->nullable()->index(); // Output Area
            $table->string('lsoa21cd', 9)->nullable()->index(); // LSOA
            $table->string('msoa21cd', 9)->nullable()->index(); // MSOA
            $table->string('lad25cd', 9)->nullable()->index(); // Local Authority District
            $table->string('wd25cd', 9)->nullable()->index(); // Ward
            $table->string('ced25cd', 9)->nullable()->index(); // County Electoral Division
            $table->string('parncp25cd', 9)->nullable()->index(); // Parish (not indexed in NSPL but we add it)
            $table->string('pcon24cd', 9)->nullable()->index(); // Westminster Constituency
            $table->string('rgn25cd', 9)->nullable()->index(); // Region
            $table->string('ctry25cd', 9)->nullable()->index(); // Country
            $table->string('pfa23cd', 9)->nullable()->index(); // Police Force Area

            // Additional NSPL fields
            $table->string('ruc21ind', 3)->nullable(); // Rural/Urban Classification
            $table->string('oac11ind', 3)->nullable(); // Output Area Classification
            $table->integer('imd20ind')->nullable(); // Index of Multiple Deprivation rank
        });

        // Add PostGIS point geometry column
        DB::statement("ALTER TABLE postcodes ADD COLUMN geom geometry(Point, 4326)");

        // Create spatial index for fast point-in-polygon queries
        DB::statement("CREATE INDEX postcodes_geom_idx ON postcodes USING GIST (geom)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};

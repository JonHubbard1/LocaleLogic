<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Database Seeder
 *
 * Orchestrates seeding of all lookup tables in dependency order.
 * Ensures parent tables are populated before child tables.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            CountySeeder::class,
            LadSeeder::class,
            WardSeeder::class,
            CedSeeder::class,
            ParishSeeder::class,
            ConstituencySeeder::class,
            PfaSeeder::class,
        ]);
    }
}

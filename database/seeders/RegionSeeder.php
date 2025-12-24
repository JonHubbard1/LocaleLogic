<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Region Seeder
 *
 * Populates the regions table with approximately 12 region records
 * from ONS lookup data for England, Wales, and Scotland.
 */
class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/regions.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("Regions CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $regions = [];
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $regions[] = [
                'rgn25cd' => $data['rgn25cd'] ?? $data['code'],
                'rgn25nm' => $data['rgn25nm'] ?? $data['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($regions) > 0) {
            DB::table('regions')->insert($regions);
            $this->command->info("Inserted " . count($regions) . " region records.");
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ward Seeder
 *
 * Populates the wards table with approximately 9,000 ward records
 * from ONS lookup data, linked to parent LADs.
 */
class WardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/wards.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("Wards CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $wards = [];
        $batchSize = 1000;
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $wards[] = [
                'wd25cd' => $data['wd25cd'] ?? $data['code'],
                'wd25nm' => $data['wd25nm'] ?? $data['name'],
                'lad25cd' => $data['lad25cd'] ?? $data['lad_code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($wards) >= $batchSize) {
                DB::table('wards')->insert($wards);
                $count += count($wards);
                $wards = [];
            }
        }

        if (count($wards) > 0) {
            DB::table('wards')->insert($wards);
            $count += count($wards);
        }

        fclose($file);

        if ($count > 0) {
            $this->command->info("Inserted {$count} ward records.");
        }
    }
}

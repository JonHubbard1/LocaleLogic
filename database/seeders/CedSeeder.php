<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * County Electoral Division Seeder
 *
 * Populates the county_electoral_divisions table with approximately 1,400 CED records
 * from ONS lookup data, linked to parent counties.
 */
class CedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/ceds.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("CEDs CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $ceds = [];
        $batchSize = 1000;
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $ceds[] = [
                'ced25cd' => $data['ced25cd'] ?? $data['code'],
                'ced25nm' => $data['ced25nm'] ?? $data['name'],
                'cty25cd' => $data['cty25cd'] ?? $data['county_code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($ceds) >= $batchSize) {
                DB::table('county_electoral_divisions')->insert($ceds);
                $count += count($ceds);
                $ceds = [];
            }
        }

        if (count($ceds) > 0) {
            DB::table('county_electoral_divisions')->insert($ceds);
            $count += count($ceds);
        }

        fclose($file);

        if ($count > 0) {
            $this->command->info("Inserted {$count} CED records.");
        }
    }
}

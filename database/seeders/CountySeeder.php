<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * County Seeder
 *
 * Populates the counties table with approximately 30 county records
 * from ONS lookup data.
 */
class CountySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/counties.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("Counties CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $counties = [];
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $counties[] = [
                'cty25cd' => $data['cty25cd'] ?? $data['code'],
                'cty25nm' => $data['cty25nm'] ?? $data['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($counties) > 0) {
            DB::table('counties')->insert($counties);
            $this->command->info("Inserted " . count($counties) . " county records.");
        }
    }
}

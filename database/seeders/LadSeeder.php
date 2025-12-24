<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local Authority District Seeder
 *
 * Populates the local_authority_districts table with approximately 350 LAD records
 * from ONS lookup data, including Welsh language names where applicable.
 */
class LadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/lads.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("LADs CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $lads = [];
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $lads[] = [
                'lad25cd' => $data['lad25cd'] ?? $data['code'],
                'lad25nm' => $data['lad25nm'] ?? $data['name'],
                'lad25nmw' => $data['lad25nmw'] ?? $data['name_welsh'] ?? null,
                'rgn25cd' => $data['rgn25cd'] ?? $data['region_code'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($lads) > 0) {
            DB::table('local_authority_districts')->insert($lads);
            $this->command->info("Inserted " . count($lads) . " LAD records.");
        }
    }
}

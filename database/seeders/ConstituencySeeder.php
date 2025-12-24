<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Constituency Seeder
 *
 * Populates the constituencies table with approximately 650 Westminster constituency records
 * from ONS lookup data.
 */
class ConstituencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/constituencies.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("Constituencies CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $constituencies = [];
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $constituencies[] = [
                'pcon24cd' => $data['pcon24cd'] ?? $data['code'],
                'pcon24nm' => $data['pcon24nm'] ?? $data['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($constituencies) > 0) {
            DB::table('constituencies')->insert($constituencies);
            $this->command->info("Inserted " . count($constituencies) . " constituency records.");
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Police Force Area Seeder
 *
 * Populates the police_force_areas table with 44 police force area records
 * from ONS lookup data.
 */
class PfaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/pfas.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("PFAs CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $pfas = [];
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $pfas[] = [
                'pfa23cd' => $data['pfa23cd'] ?? $data['code'],
                'pfa23nm' => $data['pfa23nm'] ?? $data['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        if (count($pfas) > 0) {
            DB::table('police_force_areas')->insert($pfas);
            $this->command->info("Inserted " . count($pfas) . " PFA records.");
        }
    }
}

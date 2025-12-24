<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Parish Seeder
 *
 * Populates the parishes table with approximately 11,000 parish records
 * from ONS lookup data, including Welsh language names where applicable.
 */
class ParishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/data/parishes.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("Parishes CSV file not found at {$csvPath}. Skipping seeder.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $parishes = [];
        $batchSize = 1000;
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $parishes[] = [
                'parncp25cd' => $data['parncp25cd'] ?? $data['code'],
                'parncp25nm' => $data['parncp25nm'] ?? $data['name'],
                'parncp25nmw' => $data['parncp25nmw'] ?? $data['name_welsh'] ?? null,
                'lad25cd' => $data['lad25cd'] ?? $data['lad_code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($parishes) >= $batchSize) {
                DB::table('parishes')->insert($parishes);
                $count += count($parishes);
                $parishes = [];
            }
        }

        if (count($parishes) > 0) {
            DB::table('parishes')->insert($parishes);
            $count += count($parishes);
        }

        fclose($file);

        if ($count > 0) {
            $this->command->info("Inserted {$count} parish records.");
        }
    }
}

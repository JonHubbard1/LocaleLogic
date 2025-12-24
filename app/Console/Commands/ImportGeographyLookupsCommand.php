<?php

namespace App\Console\Commands;

use App\Models\Constituency;
use App\Models\County;
use App\Models\CountyElectoralDivision;
use App\Models\LocalAuthorityDistrict;
use App\Models\Parish;
use App\Models\PoliceForceArea;
use App\Models\Region;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Import ONS Geography Lookup Data
 *
 * This command imports geography lookup data from ONS Names and Codes CSV files.
 * It populates the lookup tables (regions, LADs, wards, parishes, etc.) that are
 * referenced by the ONSUD property data.
 *
 * ONS publishes lookup files at: https://geoportal.statistics.gov.uk/
 *
 * Usage:
 *   php artisan geography:import --file=lookups.csv --type=wards
 *   php artisan geography:import --all --directory=lookups/
 */
class ImportGeographyLookupsCommand extends Command
{
    protected $signature = 'geography:import
        {--file= : Path to CSV file to import}
        {--type= : Geography type (regions|counties|lads|wards|ceds|parishes|constituencies|police)}
        {--all : Import all geography types from directory}
        {--directory=geography : Directory containing CSV files}
        {--truncate : Truncate tables before import}';

    protected $description = 'Import ONS geography lookup data from CSV files';

    protected array $stats = [];

    public function handle(): int
    {
        $this->info('ONS Geography Lookup Import');
        $this->info('============================');
        $this->newLine();

        if ($this->option('all')) {
            return $this->importAll();
        }

        if (!$this->option('file') || !$this->option('type')) {
            $this->error('Either use --all or provide both --file and --type options');
            return self::FAILURE;
        }

        return $this->importSingle(
            $this->option('file'),
            $this->option('type')
        );
    }

    protected function importAll(): int
    {
        $directory = $this->option('directory');
        $this->info("Importing all geography types from: storage/app/{$directory}");
        $this->newLine();

        $imports = [
            'regions' => 'Regions_December_2025_EN_NC.csv',
            'counties' => 'Counties_December_2025_EN_NC.csv',
            'lads' => 'LAD_December_2025_UK_NC.csv',
            'wards' => 'Ward_December_2025_UK_NC.csv',
            'ceds' => 'CED_December_2025_EN_NC.csv',
            'parishes' => 'Parish_December_2025_EW_NC.csv',
            'constituencies' => 'Westminster_Parliamentary_Constituencies_December_2024_UK_NC.csv',
            'police' => 'Police_Force_Areas_December_2025_EN_NC.csv',
        ];

        $results = [];

        foreach ($imports as $type => $filename) {
            $path = "{$directory}/{$filename}";

            if (!Storage::exists($path)) {
                $this->warn("Skipping {$type}: File not found ({$filename})");
                continue;
            }

            $this->info("Importing {$type}...");
            $result = $this->importSingle(Storage::path($path), $type);
            $results[$type] = $result;
            $this->newLine();
        }

        // Summary
        $this->info('Import Summary:');
        $this->table(
            ['Type', 'Status', 'Records'],
            collect($results)->map(fn($result, $type) => [
                $type,
                $result === self::SUCCESS ? '✓ Success' : '✗ Failed',
                $this->stats[$type] ?? 0
            ])
        );

        return in_array(self::FAILURE, $results) ? self::FAILURE : self::SUCCESS;
    }

    protected function importSingle(string $file, string $type): int
    {
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            $this->warn("Truncating {$type} table...");
            $this->truncateTable($type);
        }

        $method = 'import' . ucfirst($type);

        if (!method_exists($this, $method)) {
            $this->error("Unknown geography type: {$type}");
            return self::FAILURE;
        }

        try {
            $count = $this->$method($file);
            $this->stats[$type] = $count;
            $this->info("✓ Imported {$count} {$type} records");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to import {$type}: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function importRegions(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                Region::create([
                    'rgn25cd' => $row[0],
                    'rgn25nm' => $row[1],
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importCounties(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                County::create([
                    'cty25cd' => $row[0],
                    'cty25nm' => $row[1],
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importLads(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                LocalAuthorityDistrict::create([
                    'lad25cd' => $row[0],
                    'lad25nm' => $row[1],
                    'lad25nmw' => $row[2] ?? null,
                    'rgn25cd' => $row[3] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importWards(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    Ward::create([
                        'wd25cd' => $row[0],
                        'wd25nm' => $row[1],
                        'lad25cd' => $row[2],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip if foreign key constraint fails (LAD doesn't exist)
                    $this->warn("Skipped ward {$row[0]}: LAD {$row[2]} not found");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importCeds(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                CountyElectoralDivision::create([
                    'ced25cd' => $row[0],
                    'ced25nm' => $row[1],
                    'cty25cd' => $row[2] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importParishes(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    Parish::create([
                        'parncp25cd' => $row[0],
                        'parncp25nm' => $row[1],
                        'parncp25nmw' => $row[2] ?? null,
                        'lad25cd' => $row[3],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip if foreign key constraint fails (LAD doesn't exist)
                    $this->warn("Skipped parish {$row[0]}: LAD {$row[3]} not found");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importConstituencies(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                Constituency::create([
                    'pcon24cd' => $row[0],
                    'pcon24nm' => $row[1],
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function importPolice(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                PoliceForceArea::create([
                    'pfa23cd' => $row[0],
                    'pfa23nm' => $row[1],
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $count;
    }

    protected function truncateTable(string $type): void
    {
        $tables = [
            'regions' => 'regions',
            'counties' => 'counties',
            'lads' => 'local_authority_districts',
            'wards' => 'wards',
            'ceds' => 'county_electoral_divisions',
            'parishes' => 'parishes',
            'constituencies' => 'constituencies',
            'police' => 'police_force_areas',
        ];

        if (isset($tables[$type])) {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table($tables[$type])->truncate();
        }
    }
}

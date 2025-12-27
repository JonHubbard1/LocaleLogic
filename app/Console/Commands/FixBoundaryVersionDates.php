<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBoundaryVersionDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'boundaries:fix-version-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix boundary version dates by extracting from source filenames';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fixing boundary version dates from source filenames...');
        $this->newLine();

        // Get distinct source files from boundary_geometries
        $geometries = DB::table('boundary_geometries')
            ->select('boundary_type', 'source_file')
            ->groupBy('boundary_type', 'source_file')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($geometries as $geometry) {
            $versionDate = $this->extractVersionFromFilename($geometry->source_file);

            if ($versionDate) {
                // Update boundary_geometries
                $count = DB::table('boundary_geometries')
                    ->where('boundary_type', $geometry->boundary_type)
                    ->where('source_file', $geometry->source_file)
                    ->update(['version_date' => $versionDate]);

                // Update boundary_names
                DB::table('boundary_names')
                    ->where('boundary_type', $geometry->boundary_type)
                    ->where('source', 'geojson')
                    ->update(['version_date' => $versionDate]);

                $this->line("✓ {$geometry->boundary_type}: {$geometry->source_file} → {$versionDate} ({$count} records)");
                $updated += $count;
            } else {
                $this->warn("✗ {$geometry->boundary_type}: {$geometry->source_file} (could not extract date)");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Updated {$updated} geometry records");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} files (could not extract version)");
        }

        return Command::SUCCESS;
    }

    /**
     * Extract ONS version date from filename
     */
    private function extractVersionFromFilename(string $filename): ?string
    {
        // Pattern 1: [Type]_[Month]_[Year]_Boundaries (full month name)
        // Example: Counties_and_Unitary_Authorities_December_2024_Boundaries_UK_BFC.geojson
        if (preg_match('/_([A-Za-z]+)_(\d{4})_Boundaries/i', $filename, $matches)) {
            $month = $matches[1];
            $year = $matches[2];

            $monthNum = $this->monthNameToNumber($month);
            if ($monthNum) {
                return "{$year}-{$monthNum}-01";
            }
        }

        // Pattern 2: [TYPE]_[MONTH]_[YEAR]_[REGION]_[TYPE] (abbreviated month)
        // Example: LAD_MAY_2025_UK_BFC_V2.geojson, WD_MAY_2025_UK_BFC.geojson
        if (preg_match('/^[A-Z]+_([A-Z]{3})_(\d{4})_/i', $filename, $matches)) {
            $month = $matches[1];
            $year = $matches[2];

            $monthNum = $this->monthAbbreviationToNumber($month);
            if ($monthNum) {
                return "{$year}-{$monthNum}-01";
            }
        }

        // Pattern 3: [Type]_[Month]_[Year]_[Region]_[BFC/BUC] (full month, no "Boundaries")
        // Example: Police_Force_Areas_December_2023_EW_BUC.geojson
        if (preg_match('/_([A-Za-z]+)_(\d{4})_[A-Z]{2}_B[UF]C/i', $filename, $matches)) {
            $month = $matches[1];
            $year = $matches[2];

            $monthNum = $this->monthNameToNumber($month);
            if ($monthNum) {
                return "{$year}-{$monthNum}-01";
            }
        }

        return null;
    }

    /**
     * Convert full month name to number
     */
    private function monthNameToNumber(string $month): ?string
    {
        $monthMap = [
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12',
        ];

        return $monthMap[ucfirst(strtolower($month))] ?? null;
    }

    /**
     * Convert abbreviated month name to number
     */
    private function monthAbbreviationToNumber(string $month): ?string
    {
        $monthMap = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
        ];

        return $monthMap[strtoupper($month)] ?? null;
    }
}

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
use App\Services\CsvHeaderDetectorService;
use App\Services\GeographyVersionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

/**
 * Import ONS Geography Lookup Data
 *
 * This command imports geography lookup data from ONS Names and Codes CSV files.
 * It populates the lookup tables (regions, LADs, wards, parishes, etc.) that are
 * referenced by the ONSUD property data.
 *
 * Now supports year-agnostic imports using dynamic field detection.
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

    /**
     * Constructor with service dependencies
     */
    public function __construct(
        protected CsvHeaderDetectorService $headerDetector,
        protected GeographyVersionService $versionService
    ) {
        parent::__construct();
    }

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
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['region'])) {
            throw new \Exception('Could not detect Region year code from CSV headers');
        }

        $this->info("Detected Region year code: {$yearCodes['region']}");
        $this->versionService->validateImport('region', $yearCodes['region']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['region']];

                Region::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['region'],
                    'rgn25cd' => $gssCode,
                    'rgn25nm' => $record[$fieldMapping['region'] . 'NM'] ?? $record['RGN25NM'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('region', $yearCodes['region'], $count, basename($file));

        return $count;
    }

    protected function importCounties(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['county'])) {
            throw new \Exception('Could not detect County year code from CSV headers');
        }

        $this->info("Detected County year code: {$yearCodes['county']}");
        $this->versionService->validateImport('county', $yearCodes['county']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['county']];

                County::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['county'],
                    'cty25cd' => $gssCode,
                    'cty25nm' => $record[$fieldMapping['county'] . 'NM'] ?? $record['CTY25NM'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('county', $yearCodes['county'], $count, basename($file));

        return $count;
    }

    protected function importLads(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        // Detect year codes from CSV headers
        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['lad'])) {
            throw new \Exception('Could not detect LAD year code from CSV headers');
        }

        $this->info("Detected LAD year code: {$yearCodes['lad']}");

        // Validate we're not importing older data
        $this->versionService->validateImport('lad', $yearCodes['lad']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['lad']];

                LocalAuthorityDistrict::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['lad'],
                    'lad25cd' => $gssCode, // Keep for property joins
                    'lad25nm' => $record[$fieldMapping['lad'] . 'NM'] ?? $record['LAD25NM'] ?? null,
                    'lad25nmw' => $record[$fieldMapping['lad'] . 'NMW'] ?? $record['LAD25NMW'] ?? null,
                    'rgn25cd' => $record['RGN25CD'] ?? $record[isset($yearCodes['region']) ? $fieldMapping['region'] : 'RGN25CD'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Record successful import
        $this->versionService->recordImport('lad', $yearCodes['lad'], $count, basename($file));

        return $count;
    }

    protected function importWards(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['ward'])) {
            throw new \Exception('Could not detect Ward year code from CSV headers');
        }

        $this->info("Detected Ward year code: {$yearCodes['ward']}");
        $this->versionService->validateImport('ward', $yearCodes['ward']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                try {
                    $gssCode = $record[$fieldMapping['ward']];

                    Ward::create([
                        'gss_code' => $gssCode,
                        'year_code' => $yearCodes['ward'],
                        'wd25cd' => $gssCode,
                        'wd25nm' => $record[$fieldMapping['ward'] . 'NM'] ?? $record['WD25NM'] ?? null,
                        'lad25cd' => $record['LAD25CD'] ?? $record[isset($yearCodes['lad']) ? $fieldMapping['lad'] : 'LAD25CD'] ?? null,
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip if foreign key constraint fails
                    $wardCode = $record[$fieldMapping['ward']] ?? 'unknown';
                    $this->warn("Skipped ward {$wardCode}: " . $e->getMessage());
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('ward', $yearCodes['ward'], $count, basename($file));

        return $count;
    }

    protected function importCeds(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['ced'])) {
            throw new \Exception('Could not detect CED year code from CSV headers');
        }

        $this->info("Detected CED year code: {$yearCodes['ced']}");
        $this->versionService->validateImport('ced', $yearCodes['ced']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['ced']];

                CountyElectoralDivision::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['ced'],
                    'ced25cd' => $gssCode,
                    'ced25nm' => $record[$fieldMapping['ced'] . 'NM'] ?? $record['CED25NM'] ?? null,
                    'cty25cd' => $record['CTY25CD'] ?? $record[isset($yearCodes['county']) ? $fieldMapping['county'] : 'CTY25CD'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('ced', $yearCodes['ced'], $count, basename($file));

        return $count;
    }

    protected function importParishes(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['parish'])) {
            throw new \Exception('Could not detect Parish year code from CSV headers');
        }

        $this->info("Detected Parish year code: {$yearCodes['parish']}");
        $this->versionService->validateImport('parish', $yearCodes['parish']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                try {
                    $gssCode = $record[$fieldMapping['parish']];

                    Parish::create([
                        'gss_code' => $gssCode,
                        'year_code' => $yearCodes['parish'],
                        'parncp25cd' => $gssCode,
                        'parncp25nm' => $record[$fieldMapping['parish'] . 'NM'] ?? $record['PARNCP25NM'] ?? null,
                        'parncp25nmw' => $record[$fieldMapping['parish'] . 'NMW'] ?? $record['PARNCP25NMW'] ?? null,
                        'lad25cd' => $record['LAD25CD'] ?? $record[isset($yearCodes['lad']) ? $fieldMapping['lad'] : 'LAD25CD'] ?? null,
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $parishCode = $record[$fieldMapping['parish']] ?? 'unknown';
                    $this->warn("Skipped parish {$parishCode}: " . $e->getMessage());
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('parish', $yearCodes['parish'], $count, basename($file));

        return $count;
    }

    protected function importConstituencies(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['constituency'])) {
            throw new \Exception('Could not detect Constituency year code from CSV headers');
        }

        $this->info("Detected Constituency year code: {$yearCodes['constituency']}");
        $this->versionService->validateImport('constituency', $yearCodes['constituency']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['constituency']];

                Constituency::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['constituency'],
                    'pcon24cd' => $gssCode,
                    'pcon24nm' => $record[$fieldMapping['constituency'] . 'NM'] ?? $record['PCON24NM'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('constituency', $yearCodes['constituency'], $count, basename($file));

        return $count;
    }

    protected function importPolice(string $file): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $yearCodes = $this->headerDetector->detectYearCodes($headers);
        $fieldMapping = $this->headerDetector->buildFieldMapping($headers);

        if (!isset($yearCodes['pfa'])) {
            throw new \Exception('Could not detect PFA year code from CSV headers');
        }

        $this->info("Detected PFA year code: {$yearCodes['pfa']}");
        $this->versionService->validateImport('pfa', $yearCodes['pfa']);

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $gssCode = $record[$fieldMapping['pfa']];

                PoliceForceArea::create([
                    'gss_code' => $gssCode,
                    'year_code' => $yearCodes['pfa'],
                    'pfa23cd' => $gssCode,
                    'pfa23nm' => $record[$fieldMapping['pfa'] . 'NM'] ?? $record['PFA23NM'] ?? null,
                ]);
                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->versionService->recordImport('pfa', $yearCodes['pfa'], $count, basename($file));

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

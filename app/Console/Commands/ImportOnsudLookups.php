<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportOnsudLookups extends Command
{
    protected $signature = 'onsud:import-lookups
        {--extract-path= : Path to an already-extracted ONSUD folder (e.g. storage/app/onsud/extracted)}
        {--version-date= : Version date to store in boundary_names (YYYY-MM-DD)}
        {--source=ONSUD_Lookup : Source label for boundary_names}';

    protected $description = 'Import geography lookup files from an extracted ONSUD archive into boundary_names';

    /**
     * Map filename prefixes to boundary_type values stored in boundary_names.
     */
    private array $typeMap = [
        'BUA' => 'bua',
        'CED' => 'ced',
        'CTRY' => 'country',
        'CTY' => 'county',
        'EER' => 'eer',
        'HLTHAU' => 'hlthau',
        'LEP' => 'lep',
        'LSOA' => 'lsoa',
        'MSOA' => 'msoa',
        'NPARK' => 'npark',
        'PARNCP' => 'parish',
        'PCON' => 'constituency',
        'PFA' => 'police_force_area',
        'RGN' => 'region',
        'RUC21' => 'ruc',
        'SICBL' => 'sicbl',
        'TTWA' => 'ttwa',
        'WD' => 'ward',
        'LAD' => 'lad',
    ];

    /**
     * Regex patterns for files that should be skipped.
     */
    private array $skipPatterns = ['/ITL/i', '/SC as at/i', '/_SC_/i'];

    public function handle(): int
    {
        $extractPath = $this->resolveExtractPath();
        if (! $extractPath) {
            $this->error('Could not find an extracted ONSUD folder. Use --extract-path to specify one.');
            $this->info('Expected structure: <extract-path>/Data/ONSUD_*.csv and <extract-path>/Documents/*.csv');

            return 1;
        }

        $documentsPath = $extractPath . '/Documents';
        if (! File::isDirectory($documentsPath)) {
            $this->error("Documents folder not found: {$documentsPath}");

            return 1;
        }

        $lookupFiles = $this->discoverLookupFiles($documentsPath);
        if (empty($lookupFiles)) {
            $this->warn('No matching geography lookup CSV files found in Documents folder.');

            return 0;
        }

        $versionDate = $this->option('version-date') ?: $this->guessVersionDate($extractPath);
        $source = $this->option('source');

        $this->info("Found " . count($lookupFiles) . " lookup file(s) to import");
        $this->info("Version date: {$versionDate}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($lookupFiles));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('');

        $importedTotal = 0;
        $failedTotal = 0;

        foreach ($lookupFiles as $lookup) {
            $progressBar->setMessage($lookup['name']);

            try {
                $count = $this->importSingleLookupFile($lookup['path'], $lookup['type'], $source, $versionDate);
                $importedTotal += $count;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("  Failed: {$lookup['name']} — " . $e->getMessage());
                $failedTotal++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Import complete: " . number_format($importedTotal) . " rows across " . count($lookupFiles) . " file(s)");
        if ($failedTotal > 0) {
            $this->warn("{$failedTotal} file(s) failed");
        }

        return 0;
    }

    /**
     * Resolve the extract path: use --extract-path if given, otherwise auto-discover.
     */
    private function resolveExtractPath(): ?string
    {
        $explicit = $this->option('extract-path');
        if ($explicit) {
            return rtrim($explicit, '/');
        }

        // Auto-discover: look for the most recently modified extracted folder
        $base = storage_path('app/onsud');
        if (! File::isDirectory($base)) {
            return null;
        }

        $candidates = File::directories($base);
        if (empty($candidates)) {
            return null;
        }

        // Prefer directories that contain a Documents/ subfolder
        $withDocs = array_filter($candidates, fn ($d) => File::isDirectory($d . '/Documents'));
        $candidates = $withDocs ?: $candidates;

        // Sort by modification time, newest first
        usort($candidates, function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        return $candidates[0] ?? null;
    }

    /**
     * Guess the version date from the folder name or CSV filenames.
     */
    private function guessVersionDate(string $extractPath): string
    {
        // Try folder name: epoch-123-20260516183139 → 2026-05-16
        if (preg_match('/(\d{4})(\d{2})(\d{2})/', basename($extractPath), $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // Try a Data/ CSV filename: ONSUD_DEC_2025_EE.csv → 2025-12-01
        $csvFiles = File::glob($extractPath . '/Data/*.csv');
        if (! empty($csvFiles)) {
            $name = basename($csvFiles[0]);
            if (preg_match('/_([A-Z]{3})_(\d{4})_/i', $name, $m)) {
                $monthMap = [
                    'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                    'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                    'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
                ];
                $month = $monthMap[strtoupper($m[1])] ?? '01';
                return "{$m[2]}-{$month}-01";
            }
        }

        return now()->toDateString();
    }

    /**
     * Discover all lookup CSV files in the Documents folder.
     *
     * @return array<int, array{path: string, name: string, type: string}>
     */
    private function discoverLookupFiles(string $documentsPath): array
    {
        $csvFiles = File::glob("{$documentsPath}/*.csv");
        if (empty($csvFiles)) {
            return [];
        }

        $lookupFiles = [];
        foreach ($csvFiles as $csvPath) {
            $name = basename($csvPath);

            if ($this->shouldSkipFile($name)) {
                continue;
            }

            $boundaryType = null;
            foreach ($this->typeMap as $prefix => $type) {
                if (str_starts_with($name, $prefix)) {
                    $boundaryType = $type;
                    break;
                }
            }

            if ($boundaryType) {
                $lookupFiles[] = [
                    'path' => $csvPath,
                    'name' => $name,
                    'type' => $boundaryType,
                ];
            }
        }

        return $lookupFiles;
    }

    private function shouldSkipFile(string $name): bool
    {
        foreach ($this->skipPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Import a single lookup CSV into boundary_names.
     */
    private function importSingleLookupFile(string $csvPath, string $boundaryType, string $source, string $versionDate): int
    {
        $raw = file_get_contents($csvPath);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read lookup file: {$csvPath}");
        }

        // Convert from Windows-1252 (common in ONS files) to UTF-8 if needed
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $lines = explode("\n", $raw);
        if (empty($lines)) {
            throw new \RuntimeException("Empty lookup file: {$csvPath}");
        }

        $header = str_getcsv($lines[0]);
        if (empty($header)) {
            throw new \RuntimeException("Empty header in {$csvPath}");
        }

        $codeColumn = null;
        $nameColumn = null;
        $nameWelshColumn = null;

        foreach ($header as $col) {
            $upper = strtoupper($col);
            if ($codeColumn === null && str_ends_with($upper, 'CD') && ! str_ends_with($upper, 'NMCD')) {
                $codeColumn = $col;
            }
            if ($nameColumn === null && str_ends_with($upper, 'NM') && ! str_ends_with($upper, 'NMW')) {
                $nameColumn = $col;
            }
            if ($nameWelshColumn === null && str_ends_with($upper, 'NMW')) {
                $nameWelshColumn = $col;
            }
        }

        if ($codeColumn === null || $nameColumn === null) {
            throw new \RuntimeException("Could not detect code/name columns in {$csvPath}");
        }

        $batch = [];
        $batchSize = 1000;
        $imported = 0;

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line);
            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }

            $gssCode = trim($data[$codeColumn] ?? '');
            $name = trim($data[$nameColumn] ?? '');

            if (empty($gssCode) || empty($name)) {
                continue;
            }

            $batch[] = [
                'boundary_type' => $boundaryType,
                'gss_code' => $gssCode,
                'name' => $name,
                'name_welsh' => $nameWelshColumn ? ($data[$nameWelshColumn] ?? null) : null,
                'source' => $source,
                'version_date' => $versionDate,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertBoundaryNames($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->upsertBoundaryNames($batch);
            $imported += count($batch);
        }

        return $imported;
    }

    /**
     * Upsert a batch of boundary names, matching on (boundary_type, gss_code).
     */
    private function upsertBoundaryNames(array $batch): void
    {
        DB::table('boundary_names')->upsert(
            $batch,
            ['boundary_type', 'gss_code'],
            ['name', 'name_welsh', 'source', 'version_date', 'updated_at']
        );
    }
}

<?php

namespace App\Jobs;

use App\Models\BoundaryImport;
use App\Models\BoundaryName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessBoundaryNamesFromCsv implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600; // 1 hour
    public int $tries = 1;

    // Map boundary types to their ONS CSV column patterns
    private const COLUMN_MAPPINGS = [
        'wards' => ['code' => 'WD25CD', 'name' => 'WD25NM', 'welsh' => 'WD25NMW'],
        'ced' => ['code' => 'CED25CD', 'name' => 'CED25NM', 'welsh' => null],
        'lad' => ['code' => 'LAD25CD', 'name' => 'LAD25NM', 'welsh' => 'LAD25NMW'],
        'lpa' => ['code' => 'LPA23CD', 'name' => 'LPA23NM', 'welsh' => null],
        'parish' => ['code' => 'PARNCP25CD', 'name' => 'PARNCP25NM', 'welsh' => 'PARNCP25NMW'],
        'region' => ['code' => 'RGN25CD', 'name' => 'RGN25NM', 'welsh' => null],
        'counties' => ['code' => 'CTYUA24CD', 'name' => 'CTYUA24NM', 'welsh' => 'CTYUA24NMW'],
        'combined_authorities' => ['code' => 'CAUTH24CD', 'name' => 'CAUTH24NM', 'welsh' => null],
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importId,
        public string $csvPath,
        public string $boundaryType
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = BoundaryImport::findOrFail($this->importId);

        try {
            $import->markAsStarted();

            Log::info('Starting CSV name import', [
                'import_id' => $this->importId,
                'csv_path' => $this->csvPath,
                'boundary_type' => $this->boundaryType,
            ]);

            if (!File::exists($this->csvPath)) {
                throw new \RuntimeException("CSV file not found: {$this->csvPath}");
            }

            // Get column mapping for this boundary type
            $columns = self::COLUMN_MAPPINGS[$this->boundaryType] ?? null;
            if (!$columns) {
                throw new \RuntimeException("No column mapping defined for boundary type: {$this->boundaryType}");
            }

            // Process CSV file
            $this->processCsv($import, $columns);

            $import->markAsCompleted();

            Log::info('CSV name import completed', [
                'import_id' => $this->importId,
                'records_processed' => $import->records_processed,
                'records_failed' => $import->records_failed,
            ]);

        } catch (\Throwable $e) {
            Log::error('CSV name import failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $import->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process CSV file and import boundary names
     */
    private function processCsv(BoundaryImport $import, array $columns): void
    {
        $file = fopen($this->csvPath, 'r');
        if (!$file) {
            throw new \RuntimeException("Failed to open CSV file: {$this->csvPath}");
        }

        try {
            // Read header
            $header = fgetcsv($file);
            if (!$header) {
                throw new \RuntimeException("CSV file is empty or invalid");
            }

            // Find column indices
            $codeIndex = array_search($columns['code'], $header);
            $nameIndex = array_search($columns['name'], $header);
            $welshIndex = $columns['welsh'] ? array_search($columns['welsh'], $header) : false;

            if ($codeIndex === false || $nameIndex === false) {
                throw new \RuntimeException(
                    "Required columns not found. Expected: {$columns['code']}, {$columns['name']}. " .
                    "Found: " . implode(', ', $header)
                );
            }

            Log::info('CSV columns mapped', [
                'code_column' => $header[$codeIndex],
                'name_column' => $header[$nameIndex],
                'welsh_column' => $welshIndex !== false ? $header[$welshIndex] : null,
            ]);

            // Count total rows
            $totalRows = 0;
            while (fgetcsv($file) !== false) {
                $totalRows++;
            }
            rewind($file);
            fgetcsv($file); // Skip header again

            $import->update(['records_total' => $totalRows]);

            Log::info("Processing {$totalRows} rows from CSV");

            // Process rows in batches
            $batch = [];
            $batchSize = 500;
            $processed = 0;

            while (($row = fgetcsv($file)) !== false) {
                try {
                    $gssCode = trim($row[$codeIndex] ?? '');
                    $name = trim($row[$nameIndex] ?? '');
                    $welshName = $welshIndex !== false ? trim($row[$welshIndex] ?? '') : null;

                    if (empty($gssCode) || empty($name)) {
                        $import->incrementFailed();
                        continue;
                    }

                    $batch[] = [
                        'boundary_type' => $this->boundaryType,
                        'gss_code' => $gssCode,
                        'name' => $name,
                        'name_welsh' => $welshName ?: null,
                        'source' => 'onsud_csv',
                        'version_date' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= $batchSize) {
                        $this->insertBatch($batch);
                        $import->incrementProcessed(count($batch));
                        $processed += count($batch);
                        $batch = [];

                        Log::info("Progress: {$processed}/{$totalRows} rows processed");
                    }

                } catch (\Exception $e) {
                    Log::warning('Failed to process CSV row', [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                    $import->incrementFailed();
                }
            }

            // Insert remaining batch
            if (!empty($batch)) {
                $this->insertBatch($batch);
                $import->incrementProcessed(count($batch));
                $processed += count($batch);
            }

            Log::info("CSV processing complete: {$processed}/{$totalRows} rows processed");

        } finally {
            fclose($file);
        }
    }

    /**
     * Insert batch with upsert to handle duplicates
     */
    private function insertBatch(array $batch): void
    {
        DB::table('boundary_names')->upsert(
            $batch,
            ['boundary_type', 'gss_code'], // Unique keys
            ['name', 'name_welsh', 'source', 'version_date', 'updated_at'] // Update these on conflict
        );
    }
}

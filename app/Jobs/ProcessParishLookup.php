<?php

namespace App\Jobs;

use App\Models\BoundaryImport;
use App\Models\ParishLookup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessParishLookup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours (large CSV files with ~10k-40k rows)
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importId,
        public string $csvPath
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

            Log::info('Starting parish lookup import', [
                'import_id' => $this->importId,
                'csv_path' => $this->csvPath,
            ]);

            $fullPath = Storage::path($this->csvPath);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException("CSV file not found: {$fullPath}");
            }

            $this->processCsv($import, $fullPath);

            $import->markAsCompleted();

            Log::info('Parish lookup import completed', [
                'import_id' => $this->importId,
                'records_processed' => $import->records_processed,
            ]);

        } catch (\Throwable $e) {
            Log::error('Parish lookup import failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $import->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function processCsv(BoundaryImport $import, string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);

        // Remove BOM from first header if present
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        // Discover field names dynamically (ONS changes year codes: PAR24CD, PAR25CD, etc.)
        $fields = $this->discoverFields($header);
        if (! $fields['par_code'] || ! $fields['wd_code']) {
            throw new \RuntimeException('Could not discover required parish/ward fields from CSV header: ' . implode(', ', $header));
        }

        Log::info('Discovered parish lookup fields from CSV', ['fields' => $fields]);

        // Count total rows
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // Skip header again

        $import->update(['records_total' => $totalRows]);

        $batch = [];
        $batchSize = 100;
        $processed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);

                $parCode = $data[$fields['par_code']] ?? null;
                if (! $parCode) {
                    $import->incrementFailed();
                    continue;
                }

                $batch[] = [
                    'par_code' => $parCode,
                    'par_name' => $data[$fields['par_name']] ?? null,
                    'par_name_welsh' => !empty($data[$fields['par_name_welsh']]) ? $data[$fields['par_name_welsh']] : null,
                    'wd_code' => $data[$fields['wd_code']] ?? null,
                    'wd_name' => $data[$fields['wd_name']] ?? null,
                    'wd_name_welsh' => !empty($data[$fields['wd_name_welsh']]) ? $data[$fields['wd_name_welsh']] : null,
                    'lad_code' => $data[$fields['lad_code']] ?? null,
                    'lad_name' => $data[$fields['lad_name']] ?? null,
                    'lad_name_welsh' => !empty($data[$fields['lad_name_welsh']]) ? $data[$fields['lad_name_welsh']] : null,
                    'version_date' => now()->toDateString(),
                    'source' => 'ons_lookup',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $import->incrementProcessed(count($batch));
                    $processed += count($batch);
                    $batch = [];

                    Log::info("Progress: {$processed}/{$totalRows} parish lookups processed");
                }

            } catch (\Exception $e) {
                Log::warning('Failed to process parish lookup row', [
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
        }

        fclose($handle);
    }

    /**
     * Discover field names dynamically from CSV header.
     * ONS changes year codes (PAR24CD, PAR25CD, etc.) so we match by pattern.
     */
    private function discoverFields(array $header): array
    {
        $result = [
            'par_code' => null, 'par_name' => null, 'par_name_welsh' => null,
            'wd_code' => null, 'wd_name' => null, 'wd_name_welsh' => null,
            'lad_code' => null, 'lad_name' => null, 'lad_name_welsh' => null,
        ];

        $patterns = [
            'par_code' => '/^PAR\d{2}CD$/i',
            'par_name' => '/^PAR\d{2}NM$/i',
            'par_name_welsh' => '/^PAR\d{2}NMW$/i',
            'wd_code' => '/^WD\d{2}CD$/i',
            'wd_name' => '/^WD\d{2}NM$/i',
            'wd_name_welsh' => '/^WD\d{2}NMW$/i',
            'lad_code' => '/^LAD\d{2}CD$/i',
            'lad_name' => '/^LAD\d{2}NM$/i',
            'lad_name_welsh' => '/^LAD\d{2}NMW$/i',
        ];

        foreach ($header as $fieldName) {
            foreach ($patterns as $key => $pattern) {
                if (! $result[$key] && preg_match($pattern, $fieldName)) {
                    $result[$key] = $fieldName;
                }
            }
        }

        return $result;
    }

    private function insertBatch(array $batch): void
    {
        // Filter out rows with null par_code before upsert
        $batch = array_filter($batch, fn (array $row) => ! empty($row['par_code']));

        if (empty($batch)) {
            return;
        }

        // Deduplicate batch based on unique constraint (par_code + wd_code + version_date)
        $uniqueRows = [];
        foreach ($batch as $row) {
            $key = $row['par_code'] . '|' . ($row['wd_code'] ?? '') . '|' . $row['version_date'];
            $uniqueRows[$key] = $row;
        }

        $dedupedBatch = array_values($uniqueRows);

        if (count($dedupedBatch) < count($batch)) {
            Log::info('Removed duplicate rows from batch', [
                'original_count' => count($batch),
                'deduped_count' => count($dedupedBatch),
                'duplicates_removed' => count($batch) - count($dedupedBatch),
            ]);
        }

        DB::table('parish_lookups')->upsert(
            $dedupedBatch,
            ['par_code', 'wd_code', 'version_date'],
            ['par_name', 'par_name_welsh', 'wd_name', 'wd_name_welsh', 'lad_code', 'lad_name', 'lad_name_welsh', 'updated_at']
        );
    }
}

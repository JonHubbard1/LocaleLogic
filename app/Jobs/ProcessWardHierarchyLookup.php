<?php

namespace App\Jobs;

use App\Models\BoundaryImport;
use App\Models\WardHierarchyLookup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessWardHierarchyLookup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours (large CSV files with ~9k rows)
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

            Log::info('Starting ward hierarchy lookup import', [
                'import_id' => $this->importId,
                'csv_path' => $this->csvPath,
            ]);

            $fullPath = Storage::path($this->csvPath);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException("CSV file not found: {$fullPath}");
            }

            $this->processCsv($import, $fullPath);

            $import->markAsCompleted();

            Log::info('Ward hierarchy lookup import completed', [
                'import_id' => $this->importId,
                'records_processed' => $import->records_processed,
            ]);

        } catch (\Throwable $e) {
            Log::error('Ward hierarchy lookup import failed', [
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

        // Discover field names dynamically (ONS changes year codes: WD25CD, WD24CD, etc.)
        $fields = $this->discoverFields($header);
        if (! $fields['wd_code']) {
            throw new \RuntimeException('Could not discover required ward fields from CSV header: ' . implode(', ', $header));
        }

        Log::info('Discovered ward hierarchy fields from CSV', ['fields' => $fields]);

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

                $wdCode = $data[$fields['wd_code']] ?? null;
                if (! $wdCode) {
                    $import->incrementFailed();
                    continue;
                }

                $batch[] = [
                    'wd_code' => $wdCode,
                    'wd_name' => $data[$fields['wd_name']] ?? null,
                    'lad_code' => $data[$fields['lad_code']] ?? null,
                    'lad_name' => $data[$fields['lad_name']] ?? null,
                    'cty_code' => !empty($data[$fields['cty_code']]) ? $data[$fields['cty_code']] : null,
                    'cty_name' => !empty($data[$fields['cty_name']]) ? $data[$fields['cty_name']] : null,
                    'ced_code' => !empty($data[$fields['ced_code']]) ? $data[$fields['ced_code']] : null,
                    'ced_name' => !empty($data[$fields['ced_name']]) ? $data[$fields['ced_name']] : null,
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

                    Log::info("Progress: {$processed}/{$totalRows} lookups processed");
                }

            } catch (\Exception $e) {
                Log::warning('Failed to process lookup row', [
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
     * ONS changes year codes (WD25CD, WD24CD, etc.) so we match by pattern.
     */
    private function discoverFields(array $header): array
    {
        $result = [
            'wd_code' => null, 'wd_name' => null,
            'lad_code' => null, 'lad_name' => null,
            'cty_code' => null, 'cty_name' => null,
            'ced_code' => null, 'ced_name' => null,
        ];

        $patterns = [
            'wd_code' => '/^WD\d{2}CD$/i',
            'wd_name' => '/^WD\d{2}NM$/i',
            'lad_code' => '/^LAD\d{2}CD$/i',
            'lad_name' => '/^LAD\d{2}NM$/i',
            'cty_code' => '/^CTY\d{2}CD$/i',
            'cty_name' => '/^CTY\d{2}NM$/i',
            'ced_code' => '/^CED\d{2}CD$/i',
            'ced_name' => '/^CED\d{2}NM$/i',
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
        // Filter out rows with null wd_code before upsert
        $batch = array_filter($batch, fn (array $row) => ! empty($row['wd_code']));

        if (empty($batch)) {
            return;
        }

        // Deduplicate batch based on unique constraint (wd_code + ced_code + version_date)
        $uniqueRows = [];
        foreach ($batch as $row) {
            $cedCode = $row['ced_code'] ?? '';
            $wdCode = $row['wd_code'] ?? '';
            $versionDate = $row['version_date'] ?? '';

            $key = $wdCode . '||' . $cedCode . '||' . $versionDate;
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

        try {
            DB::table('ward_hierarchy_lookups')->upsert(
                $dedupedBatch,
                ['wd_code', 'ced_code', 'version_date'],
                ['wd_name', 'lad_code', 'lad_name', 'cty_code', 'cty_name', 'ced_name', 'updated_at']
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // If batch upsert fails with cardinality violation, insert row by row
            if (str_contains($e->getMessage(), 'cardinality violation') || str_contains($e->getMessage(), '21000')) {
                Log::warning('Batch upsert failed with cardinality violation, falling back to row-by-row insert', [
                    'batch_size' => count($dedupedBatch),
                    'error' => $e->getMessage(),
                ]);

                foreach ($dedupedBatch as $row) {
                    try {
                        DB::table('ward_hierarchy_lookups')->upsert(
                            [$row],
                            ['wd_code', 'ced_code', 'version_date'],
                            ['wd_name', 'lad_code', 'lad_name', 'cty_code', 'cty_name', 'ced_name', 'updated_at']
                        );
                    } catch (\Exception $rowError) {
                        Log::error('Failed to insert individual row', [
                            'row' => $row,
                            'error' => $rowError->getMessage(),
                        ]);
                    }
                }
            } else {
                throw $e;
            }
        }
    }
}

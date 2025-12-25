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

        // Count total rows
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // Skip header again

        $import->update(['records_total' => $totalRows]);

        $batch = [];
        $batchSize = 100; // Reduced from 500 to avoid PostgreSQL parameter limit with upsert
        $processed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);

                $batch[] = [
                    'wd_code' => $data['WD25CD'] ?? null,
                    'wd_name' => $data['WD25NM'] ?? null,
                    'lad_code' => $data['LAD25CD'] ?? null,
                    'lad_name' => $data['LAD25NM'] ?? null,
                    'cty_code' => !empty($data['CTY25CD']) ? $data['CTY25CD'] : null,
                    'cty_name' => !empty($data['CTY25NM']) ? $data['CTY25NM'] : null,
                    'ced_code' => !empty($data['CED25CD']) ? $data['CED25CD'] : null,
                    'ced_name' => !empty($data['CED25NM']) ? $data['CED25NM'] : null,
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

    private function insertBatch(array $batch): void
    {
        // Deduplicate batch based on unique constraint (wd_code + ced_code + version_date)
        // ONS CSV files contain duplicate rows that need to be filtered out
        $uniqueRows = [];
        foreach ($batch as $row) {
            $key = $row['wd_code'] . '|' . ($row['ced_code'] ?? 'NULL') . '|' . $row['version_date'];
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

        DB::table('ward_hierarchy_lookups')->upsert(
            $dedupedBatch,
            ['wd_code', 'ced_code', 'version_date'],
            ['wd_name', 'lad_code', 'lad_name', 'cty_code', 'cty_name', 'ced_name', 'updated_at']
        );
    }
}

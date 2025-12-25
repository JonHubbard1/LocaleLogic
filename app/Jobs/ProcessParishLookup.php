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
                    'par_code' => $data['PAR24CD'] ?? null,
                    'par_name' => $data['PAR24NM'] ?? null,
                    'par_name_welsh' => !empty($data['PAR24NMW']) ? $data['PAR24NMW'] : null,
                    'wd_code' => $data['WD24CD'] ?? null,
                    'wd_name' => $data['WD24NM'] ?? null,
                    'wd_name_welsh' => !empty($data['WD24NMW']) ? $data['WD24NMW'] : null,
                    'lad_code' => $data['LAD24CD'] ?? null,
                    'lad_name' => $data['LAD24NM'] ?? null,
                    'lad_name_welsh' => !empty($data['LAD24NMW']) ? $data['LAD24NMW'] : null,
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

    private function insertBatch(array $batch): void
    {
        // Deduplicate batch based on unique constraint (par_code + wd_code + version_date)
        // ONS CSV files contain duplicate rows that need to be filtered out
        $uniqueRows = [];
        foreach ($batch as $row) {
            $key = $row['par_code'] . '|' . $row['wd_code'] . '|' . $row['version_date'];
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

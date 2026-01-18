<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportNsplCommand extends Command
{
    protected $signature = 'nspl:import
        {file : Path to NSPL CSV file}
        {--batch-size=5000 : Number of records per batch}
        {--truncate : Truncate table before import}
        {--update-geometry : Update geometry column from lat/lng after import}';

    protected $description = 'Import NSPL (National Statistics Postcode Lookup) data into postcodes table';

    private array $stats = [
        'total_rows' => 0,
        'successful' => 0,
        'skipped' => 0,
        'errors' => 0,
        'no_coordinates' => 0,
    ];

    // Column mapping: NSPL column name => our database column name
    private array $columnMap = [
        'pcd7' => 'pcd7',
        'pcd8' => 'pcd8',
        'pcds' => 'pcds',
        'dointr' => 'dointr',
        'doterm' => 'doterm',
        'east1m' => 'east1m',
        'north1m' => 'north1m',
        'oa21cd' => 'oa21cd',
        'lsoa21cd' => 'lsoa21cd',
        'msoa21cd' => 'msoa21cd',
        'lad25cd' => 'lad25cd',
        'wd25cd' => 'wd25cd',
        'ced25cd' => 'ced25cd',
        'pcon24cd' => 'pcon24cd',
        'rgn25cd' => 'rgn25cd',
        'ctry25cd' => 'ctry25cd',
        'pfa23cd' => 'pfa23cd',
        'ruc21ind' => 'ruc21ind',
        'oac11ind' => 'oac11ind',
        'lat' => 'lat',
        'long' => 'lng', // Note: NSPL uses 'long', we use 'lng'
        'imd20ind' => 'imd20ind',
    ];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $batchSize = (int) $this->option('batch-size');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing NSPL data from: {$filePath}");
        $this->info("Batch size: {$batchSize}");

        // Truncate if requested
        if ($this->option('truncate')) {
            $this->warn("Truncating postcodes table...");
            DB::table('postcodes')->truncate();
        }

        // Open file and get headers
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Failed to open file");
            return 1;
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->error("Failed to read CSV headers");
            fclose($handle);
            return 1;
        }

        // Clean headers (remove BOM if present)
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        // Map header positions
        $headerPositions = array_flip($headers);

        $this->info("Found " . count($headers) . " columns");

        // Start progress bar (estimate based on file size)
        $fileSize = filesize($filePath);
        $estimatedRows = (int) ($fileSize / 200); // Rough estimate
        $progressBar = $this->output->createProgressBar($estimatedRows);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% | %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $batch = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $this->stats['total_rows']++;

            try {
                $record = $this->parseRow($row, $headerPositions);

                if ($record === null) {
                    $this->stats['skipped']++;
                    continue;
                }

                $batch[] = $record;

                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $batch = [];
                    $progressBar->setMessage("Inserted {$this->stats['successful']} records");
                }

                if ($rowNumber % 1000 === 0) {
                    $progressBar->setProgress(min($rowNumber, $estimatedRows));
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error("NSPL import error at row {$rowNumber}: " . $e->getMessage());
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            $this->insertBatch($batch);
        }

        fclose($handle);

        $progressBar->setProgress($estimatedRows);
        $progressBar->finish();
        $this->newLine(2);

        // Update geometry column
        if ($this->option('update-geometry')) {
            $this->updateGeometry();
        }

        // Print stats
        $this->printStats();

        return 0;
    }

    private function parseRow(array $row, array $headerPositions): ?array
    {
        $record = [];

        foreach ($this->columnMap as $nsplCol => $dbCol) {
            if (!isset($headerPositions[$nsplCol])) {
                continue;
            }

            $position = $headerPositions[$nsplCol];
            $value = $row[$position] ?? null;

            // Clean value
            $value = trim((string) $value);
            $value = $value === '' ? null : $value;

            // Remove quotes if present
            if ($value !== null) {
                $value = trim($value, '"');
            }

            $record[$dbCol] = $value;
        }

        // Validate required fields
        if (empty($record['pcd7']) || empty($record['pcds'])) {
            return null;
        }

        // Convert numeric fields
        if (isset($record['east1m']) && $record['east1m'] !== null) {
            $record['east1m'] = (int) $record['east1m'];
        }
        if (isset($record['north1m']) && $record['north1m'] !== null) {
            $record['north1m'] = (int) $record['north1m'];
        }
        if (isset($record['lat']) && $record['lat'] !== null) {
            $record['lat'] = (float) $record['lat'];
        }
        if (isset($record['lng']) && $record['lng'] !== null) {
            $record['lng'] = (float) $record['lng'];
        }
        if (isset($record['imd20ind']) && $record['imd20ind'] !== null) {
            $record['imd20ind'] = (int) $record['imd20ind'];
        }

        // Track postcodes without coordinates
        if (empty($record['lat']) || empty($record['lng'])) {
            $this->stats['no_coordinates']++;
        }

        return $record;
    }

    private function insertBatch(array $batch): void
    {
        // Get database column names (values of the column map)
        $updateColumns = array_values($this->columnMap);

        try {
            DB::table('postcodes')->upsert(
                $batch,
                ['pcd7'],
                $updateColumns
            );
            $this->stats['successful'] += count($batch);
        } catch (\Exception $e) {
            // Try inserting one by one to identify problem records
            foreach ($batch as $record) {
                try {
                    DB::table('postcodes')->upsert(
                        [$record],
                        ['pcd7'],
                        $updateColumns
                    );
                    $this->stats['successful']++;
                } catch (\Exception $e2) {
                    $this->stats['errors']++;
                    Log::error("Failed to insert postcode {$record['pcd7']}: " . $e2->getMessage());
                }
            }
        }
    }

    private function updateGeometry(): void
    {
        $this->info("Updating geometry column from lat/lng coordinates...");

        $updated = DB::update("
            UPDATE postcodes
            SET geom = ST_SetSRID(ST_MakePoint(lng, lat), 4326)
            WHERE lat IS NOT NULL
              AND lng IS NOT NULL
              AND lat != 0
              AND lng != 0
              AND geom IS NULL
        ");

        $this->info("Updated geometry for {$updated} postcodes");
    }

    private function printStats(): void
    {
        $this->info("Import completed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total rows processed', number_format($this->stats['total_rows'])],
                ['Successfully imported', number_format($this->stats['successful'])],
                ['Skipped (invalid)', number_format($this->stats['skipped'])],
                ['Errors', number_format($this->stats['errors'])],
                ['Without coordinates', number_format($this->stats['no_coordinates'])],
            ]
        );
    }
}

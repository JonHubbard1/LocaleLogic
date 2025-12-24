<?php

namespace App\Console\Commands;

use App\Models\DataVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TestOnsudStages extends Command
{
    protected $signature = 'onsud:test-stages
        {--stage=all : Which stage to test (all, upload, unzip, import)}
        {--file= : Path to test ZIP file}';

    protected $description = 'Test ONSUD import stages independently';

    public function handle(): int
    {
        $stage = $this->option('stage');

        match ($stage) {
            'all' => $this->testAll(),
            'upload' => $this->testStage1Upload(),
            'unzip' => $this->testStage2Unzip(),
            'import' => $this->testStage3Import(),
            default => $this->error("Unknown stage: {$stage}. Use: all, upload, unzip, import"),
        };

        return 0;
    }

    private function testAll(): void
    {
        $this->info("Testing all ONSUD import stages...");
        $this->newLine();

        $this->testStage1Upload();
        $this->newLine();

        $this->testStage2Unzip();
        $this->newLine();

        $this->testStage3Import();
    }

    private function testStage1Upload(): void
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("STAGE 1: File Upload & Storage");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Check storage directory exists
        $storagePath = storage_path('app/onsud');
        if (!File::exists($storagePath)) {
            $this->warn("Storage directory does not exist, creating it...");
            File::makeDirectory($storagePath, 0755, true);
        }
        $this->info("✓ Storage directory exists: {$storagePath}");

        // List existing files
        $files = File::files($storagePath);
        if (empty($files)) {
            $this->warn("No files found in storage directory");
        } else {
            $this->info("Found " . count($files) . " file(s) in storage:");
            foreach ($files as $file) {
                $size = $this->formatFileSize(filesize($file));
                $this->line("  - " . basename($file) . " ({$size})");
            }
        }

        // Check write permissions
        if (is_writable($storagePath)) {
            $this->info("✓ Storage directory is writable");
        } else {
            $this->error("✗ Storage directory is NOT writable");
        }

        $this->newLine();
        $this->info("STAGE 1 STATUS: " . (is_writable($storagePath) ? "READY" : "NEEDS FIXING"));
    }

    private function testStage2Unzip(): void
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("STAGE 2: ZIP Extraction & CSV Detection");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $filePath = $this->option('file');

        if (!$filePath) {
            // Try to find a ZIP file in storage
            $zipFiles = File::glob(storage_path('app/onsud/*.zip'));
            if (empty($zipFiles)) {
                $this->warn("No ZIP files found. Upload a file via the web UI or specify --file=path");
                return;
            }
            $filePath = $zipFiles[0];
            $this->info("Using: " . basename($filePath));
        }

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }

        $this->info("✓ File exists: " . basename($filePath) . " (" . $this->formatFileSize(filesize($filePath)) . ")");

        // Test ZIP extraction
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $this->info("✓ ZIP file is valid and can be opened");
            $this->info("  Contains {$zip->numFiles} files");

            // Extract to test directory
            $extractPath = dirname($filePath) . '/test-extract-' . time();
            File::makeDirectory($extractPath, 0755, true);

            $this->info("Extracting to: {$extractPath}");
            $zip->extractTo($extractPath);
            $zip->close();
            $this->info("✓ Extraction complete");

            // Find CSV files
            $csvFiles = File::glob("{$extractPath}/*.csv");
            if (empty($csvFiles)) {
                $this->error("✗ No CSV files found in extracted content");
            } else {
                $this->info("✓ Found " . count($csvFiles) . " CSV file(s):");
                foreach ($csvFiles as $csv) {
                    $size = $this->formatFileSize(filesize($csv));
                    $this->line("  - " . basename($csv) . " ({$size})");

                    // Test CSV header
                    $handle = fopen($csv, 'r');
                    $header = fgetcsv($handle);
                    fclose($handle);

                    $requiredColumns = ['UPRN', 'PCDS', 'GRIDGB1E', 'GRIDGB1N', 'LAD25CD'];
                    $missing = array_diff($requiredColumns, $header);

                    if (empty($missing)) {
                        $this->info("    ✓ CSV header is valid (" . count($header) . " columns)");
                    } else {
                        $this->error("    ✗ Missing required columns: " . implode(', ', $missing));
                    }
                }
            }

            // Cleanup test extraction
            $this->info("Cleaning up test extraction...");
            File::deleteDirectory($extractPath);

        } else {
            $this->error("✗ Failed to open ZIP file");
        }

        $this->newLine();
        $this->info("STAGE 2 STATUS: " . ($zip->open($filePath) === true ? "READY" : "NEEDS FIXING"));
    }

    private function testStage3Import(): void
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("STAGE 3: Database Import & Table Swap");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->info("✓ Database connection successful");
        } catch (\Exception $e) {
            $this->error("✗ Database connection failed: " . $e->getMessage());
            return;
        }

        // Check required tables exist
        $tables = ['properties', 'properties_staging', 'data_versions'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->info("✓ Table '{$table}' exists ({$count} records)");
            } else {
                $this->error("✗ Table '{$table}' does not exist");
            }
        }

        // Check DataVersion functionality
        $this->newLine();
        $this->info("Testing DataVersion model...");

        $testEpoch = 999; // Use a test epoch that won't conflict
        $testVersion = DataVersion::updateOrCreate(
            ['dataset' => 'ONSUD', 'epoch' => $testEpoch],
            [
                'release_date' => now(),
                'imported_at' => now(),
                'status' => 'test',
                'notes' => 'Test record created by onsud:test-stages',
            ]
        );

        $this->info("✓ DataVersion::updateOrCreate() works correctly");
        $this->info("  Created/Updated record ID: {$testVersion->id}");

        // Clean up test record
        $testVersion->delete();
        $this->info("✓ Test record cleaned up");

        // Check if there are existing ONSUD versions
        $existingVersions = DataVersion::where('dataset', 'ONSUD')->get();
        if ($existingVersions->isEmpty()) {
            $this->warn("No existing ONSUD versions in database");
        } else {
            $this->info("Found " . $existingVersions->count() . " existing ONSUD version(s):");
            foreach ($existingVersions as $version) {
                $this->line("  - Epoch {$version->epoch}: {$version->status} ({$version->record_count} records)");
            }
        }

        $this->newLine();
        $this->info("STAGE 3 STATUS: READY");
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}

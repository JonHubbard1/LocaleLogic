<?php

namespace App\Jobs;

use App\Models\BoundaryImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcessBoundaryImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours
    public int $tries = 1; // Don't retry automatically

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public string $boundaryType,
        public string $source = 'manual_upload'
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting boundary import processing', [
                'file_path' => $this->filePath,
                'boundary_type' => $this->boundaryType,
                'source' => $this->source,
            ]);

            // Get full file path
            $fullPath = Storage::path($this->filePath);

            if (!File::exists($fullPath)) {
                throw new \RuntimeException("File not found: {$this->filePath}");
            }

            // Get file info
            $fileSize = File::size($fullPath);
            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            Log::info('File details', [
                'size' => $fileSize,
                'extension' => $extension,
            ]);

            // Determine file type and process accordingly
            if ($extension === 'zip') {
                $this->handleZipFile($fullPath, $fileSize);
            } elseif ($extension === 'csv') {
                $this->handleCsvFile($fullPath, $fileSize);
            } elseif (in_array($extension, ['json', 'geojson'])) {
                $this->handleGeoJsonFile($fullPath, $fileSize);
            } else {
                throw new \RuntimeException("Unsupported file type: {$extension}");
            }

            Log::info('Boundary import processing completed successfully', [
                'boundary_type' => $this->boundaryType,
            ]);

        } catch (\Throwable $e) {
            Log::error('Boundary import processing failed', [
                'error' => $e->getMessage(),
                'file_path' => $this->filePath,
                'boundary_type' => $this->boundaryType,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle ZIP file extraction and processing
     */
    private function handleZipFile(string $zipPath, int $fileSize): void
    {
        $extractPath = dirname($zipPath) . '/extracted';

        if (!File::exists($extractPath)) {
            File::makeDirectory($extractPath, 0755, true);
        }

        Log::info('Extracting ZIP file', ['path' => $zipPath]);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open ZIP file: {$zipPath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        Log::info('ZIP extraction complete', ['extract_path' => $extractPath]);

        // Find files in extracted directory
        $csvFiles = File::glob("{$extractPath}/*.csv");
        $geoJsonFiles = File::glob("{$extractPath}/*.{json,geojson}", GLOB_BRACE);

        // Process GeoJSON files first (they contain both names and geometry)
        foreach ($geoJsonFiles as $geoJsonFile) {
            Log::info('Found GeoJSON file in ZIP', ['file' => basename($geoJsonFile)]);
            $this->handleGeoJsonFile($geoJsonFile, File::size($geoJsonFile));
        }

        // Then process CSV files for additional name data
        foreach ($csvFiles as $csvFile) {
            Log::info('Found CSV file in ZIP', ['file' => basename($csvFile)]);
            $this->handleCsvFile($csvFile, File::size($csvFile));
        }

        // Clean up extracted files
        File::deleteDirectory($extractPath);
        Log::info('Cleaned up extracted files');
    }

    /**
     * Handle CSV file processing (names or lookups)
     */
    private function handleCsvFile(string $csvPath, int $fileSize): void
    {
        // Determine data type based on boundary type
        $dataType = match($this->boundaryType) {
            'ward_hierarchy_lookup', 'parish_lookup' => 'lookups',
            default => 'names',
        };

        // Create import record
        $import = BoundaryImport::create([
            'boundary_type' => $this->boundaryType,
            'data_type' => $dataType,
            'status' => 'pending',
            'source' => $this->source,
            'file_path' => $this->filePath,
            'file_size' => $fileSize,
        ]);

        Log::info('Created BoundaryImport record for CSV', [
            'import_id' => $import->id,
            'type' => $dataType
        ]);

        // Dispatch appropriate processing job based on boundary type
        // Pass the relative file path, not the full path - child jobs will call Storage::path() themselves
        match($this->boundaryType) {
            'ward_hierarchy_lookup' => ProcessWardHierarchyLookup::dispatch($import->id, $this->filePath),
            'parish_lookup' => ProcessParishLookup::dispatch($import->id, $this->filePath),
            default => ProcessBoundaryNamesFromCsv::dispatch($import->id, $this->filePath, $this->boundaryType),
        };

        Log::info('Dispatched CSV processing job', ['import_id' => $import->id]);
    }

    /**
     * Handle GeoJSON file processing (geometry and names)
     */
    private function handleGeoJsonFile(string $geoJsonPath, int $fileSize): void
    {
        // Create import record
        $import = BoundaryImport::create([
            'boundary_type' => $this->boundaryType,
            'data_type' => 'polygons',
            'status' => 'pending',
            'source' => $this->source,
            'file_path' => $this->filePath,
            'file_size' => $fileSize,
        ]);

        Log::info('Created BoundaryImport record for GeoJSON', ['import_id' => $import->id]);

        // Dispatch GeoJSON processing job
        ProcessBoundaryGeometryFromGeoJson::dispatch($import->id, $geoJsonPath, $this->boundaryType);

        Log::info('Dispatched GeoJSON processing job', ['import_id' => $import->id]);
    }
}

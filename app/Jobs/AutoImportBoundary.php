<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoundaryImport;
use App\Services\ArcGISBoundaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Automatically discovers, downloads, and imports boundary data from ArcGIS.
 */
class AutoImportBoundary implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public string $boundaryType,
        public string $source = 'auto_arcgis',
    ) {
    }

    public function handle(ArcGISBoundaryService $arcGIS): void
    {
        Log::info('Starting auto boundary import', [
            'boundary_type' => $this->boundaryType,
            'source' => $this->source,
        ]);

        try {
            // 1. Discover the latest ArcGIS service
            $service = $arcGIS->discoverService($this->boundaryType);
            if (! $service) {
                Log::error('Could not discover ArcGIS service', [
                    'boundary_type' => $this->boundaryType,
                ]);

                return;
            }

            Log::info('Discovered ArcGIS service', [
                'boundary_type' => $this->boundaryType,
                'service_name' => $service['name'],
                'version_date' => $service['version_date'],
            ]);

            // 2. Download GeoJSON
            $geoJsonPath = $arcGIS->downloadGeoJson(
                $service['url'],
                $this->boundaryType
            );

            if (! $geoJsonPath) {
                Log::error('Failed to download GeoJSON', [
                    'boundary_type' => $this->boundaryType,
                    'service' => $service['name'],
                ]);

                return;
            }

            $fullPath = Storage::path($geoJsonPath);
            $fileSize = filesize($fullPath);

            Log::info('GeoJSON downloaded', [
                'path' => $geoJsonPath,
                'size' => $fileSize,
            ]);

            // 3. Create import record
            $import = BoundaryImport::create([
                'boundary_type' => $this->boundaryType,
                'data_type' => 'polygons',
                'status' => 'pending',
                'source' => $this->source,
                'file_path' => $geoJsonPath,
                'file_size' => $fileSize,
                'metadata' => [
                    'arcgis_service' => $service['name'],
                    'arcgis_url' => $service['url'],
                    'version_date' => $service['version_date'],
                ],
            ]);

            Log::info('Created BoundaryImport record', [
                'import_id' => $import->id,
            ]);

            // 4. Dispatch GeoJSON processing job
            ProcessBoundaryGeometryFromGeoJson::dispatch(
                $import->id,
                $fullPath,
                $this->boundaryType
            );

            Log::info('Dispatched GeoJSON processing job', [
                'import_id' => $import->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Auto boundary import failed', [
                'boundary_type' => $this->boundaryType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

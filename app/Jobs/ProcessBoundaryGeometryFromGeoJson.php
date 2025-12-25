<?php

namespace App\Jobs;

use App\Models\BoundaryGeometry;
use App\Models\BoundaryImport;
use App\Models\BoundaryName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessBoundaryGeometryFromGeoJson implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours for large files
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importId,
        public string $geoJsonPath,
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

            Log::info('Starting GeoJSON import', [
                'import_id' => $this->importId,
                'geojson_path' => $this->geoJsonPath,
                'boundary_type' => $this->boundaryType,
            ]);

            if (!File::exists($this->geoJsonPath)) {
                throw new \RuntimeException("GeoJSON file not found: {$this->geoJsonPath}");
            }

            // Process GeoJSON file
            $this->processGeoJson($import);

            $import->markAsCompleted();

            Log::info('GeoJSON import completed', [
                'import_id' => $this->importId,
                'records_processed' => $import->records_processed,
                'records_failed' => $import->records_failed,
            ]);

        } catch (\Throwable $e) {
            Log::error('GeoJSON import failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $import->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process GeoJSON file and import geometries
     */
    private function processGeoJson(BoundaryImport $import): void
    {
        Log::info('Reading GeoJSON file', ['path' => $this->geoJsonPath]);

        // Read entire GeoJSON file
        $contents = File::get($this->geoJsonPath);
        $geoJson = json_decode($contents, true);

        if (!$geoJson || !isset($geoJson['features'])) {
            throw new \RuntimeException("Invalid GeoJSON format: missing 'features' array");
        }

        $features = $geoJson['features'];
        $totalFeatures = count($features);

        $import->update(['records_total' => $totalFeatures]);

        Log::info("Processing {$totalFeatures} features from GeoJSON");

        $geometryBatch = [];
        $nameBatch = [];
        $batchSize = 100; // Smaller batches for large geometry data
        $processed = 0;

        foreach ($features as $feature) {
            try {
                if (!isset($feature['geometry']) || !isset($feature['properties'])) {
                    Log::warning('Skipping feature without geometry or properties');
                    $import->incrementFailed();
                    continue;
                }

                $properties = $feature['properties'];
                $geometry = $feature['geometry'];

                // Extract GSS code and name from properties
                // ONS GeoJSON files typically use these property names
                $gssCode = $this->extractGssCode($properties);
                $name = $this->extractName($properties);

                if (!$gssCode || !$name) {
                    Log::warning('Skipping feature: missing GSS code or name', [
                        'properties' => $properties,
                    ]);
                    $import->incrementFailed();
                    continue;
                }

                // Calculate bounding box and area
                $boundingBox = $this->calculateBoundingBox($geometry);
                $areaHectares = $this->extractArea($properties);

                // Prepare geometry record
                $geometryBatch[] = [
                    'boundary_type' => $this->boundaryType,
                    'gss_code' => $gssCode,
                    'name' => $name,
                    'geometry' => json_encode($geometry),
                    'properties' => json_encode($properties),
                    'area_hectares' => $areaHectares,
                    'bounding_box' => $boundingBox,
                    'source_file' => basename($this->geoJsonPath),
                    'version_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Prepare name record (from GeoJSON properties)
                $nameBatch[] = [
                    'boundary_type' => $this->boundaryType,
                    'gss_code' => $gssCode,
                    'name' => $name,
                    'name_welsh' => $this->extractWelshName($properties),
                    'source' => 'geojson',
                    'version_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($geometryBatch) >= $batchSize) {
                    $this->insertGeometryBatch($geometryBatch);
                    $this->insertNameBatch($nameBatch);
                    $import->incrementProcessed(count($geometryBatch));
                    $processed += count($geometryBatch);

                    Log::info("Progress: {$processed}/{$totalFeatures} features processed");

                    $geometryBatch = [];
                    $nameBatch = [];
                }

            } catch (\Exception $e) {
                Log::warning('Failed to process GeoJSON feature', [
                    'error' => $e->getMessage(),
                    'properties' => $feature['properties'] ?? null,
                ]);
                $import->incrementFailed();
            }
        }

        // Insert remaining batches
        if (!empty($geometryBatch)) {
            $this->insertGeometryBatch($geometryBatch);
            $this->insertNameBatch($nameBatch);
            $import->incrementProcessed(count($geometryBatch));
            $processed += count($geometryBatch);
        }

        Log::info("GeoJSON processing complete: {$processed}/{$totalFeatures} features processed");
    }

    /**
     * Extract GSS code from properties (handles various naming conventions)
     */
    private function extractGssCode(array $properties): ?string
    {
        // Pattern-based matching for year-agnostic codes
        // Matches: WD25CD, CED26CD, LAD24CD, etc.
        $codePatterns = [
            '/^WD\d{2}CD$/i',       // Ward codes
            '/^CED\d{2}CD$/i',      // County Electoral Division
            '/^LAD\d{2}CD$/i',      // Local Authority District
            '/^LPA\d{2}CD$/i',      // Local Planning Authority
            '/^PARNCP\d{2}CD$/i',   // Parish/Non-Civil Parish
            '/^RGN\d{2}CD$/i',      // Region
            '/^CTYUA\d{2}CD$/i',    // Counties & Unitary Authorities
            '/^CAUTH\d{2}CD$/i',    // Combined Authorities
            '/^PCON\d{2}CD$/i',     // Westminster Parliamentary Constituencies
            '/^SPC\d{2}CD$/i',      // Scottish Parliament Constituencies
            '/^SPR\d{2}CD$/i',      // Scottish Parliament Regions
            '/^SENC\d{2}CD$/i',     // Welsh Senedd Constituencies
            '/^SENER\d{2}CD$/i',    // Welsh Senedd Regions
        ];

        // First try pattern matching
        foreach ($properties as $key => $value) {
            foreach ($codePatterns as $pattern) {
                if (preg_match($pattern, $key) && !empty($value)) {
                    return trim($value);
                }
            }
        }

        // Fallback to generic property names
        $genericKeys = ['code', 'gss_code', 'OBJECTID'];
        foreach ($genericKeys as $key) {
            if (isset($properties[$key]) && !empty($properties[$key])) {
                return trim($properties[$key]);
            }
        }

        return null;
    }

    /**
     * Extract name from properties
     */
    private function extractName(array $properties): ?string
    {
        // Pattern-based matching for year-agnostic names
        // Matches: WD25NM, CED26NM, LAD24NM, etc.
        $namePatterns = [
            '/^WD\d{2}NM$/i',       // Ward names
            '/^CED\d{2}NM$/i',      // County Electoral Division
            '/^LAD\d{2}NM$/i',      // Local Authority District
            '/^LPA\d{2}NM$/i',      // Local Planning Authority
            '/^PARNCP\d{2}NM$/i',   // Parish/Non-Civil Parish
            '/^RGN\d{2}NM$/i',      // Region
            '/^CTYUA\d{2}NM$/i',    // Counties & Unitary Authorities
            '/^CAUTH\d{2}NM$/i',    // Combined Authorities
            '/^PCON\d{2}NM$/i',     // Westminster Parliamentary Constituencies
            '/^SPC\d{2}NM$/i',      // Scottish Parliament Constituencies
            '/^SPR\d{2}NM$/i',      // Scottish Parliament Regions
            '/^SENC\d{2}NM$/i',     // Welsh Senedd Constituencies
            '/^SENER\d{2}NM$/i',    // Welsh Senedd Regions
        ];

        // First try pattern matching
        foreach ($properties as $key => $value) {
            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $key) && !empty($value)) {
                    return trim($value);
                }
            }
        }

        // Fallback to generic property names
        $genericKeys = ['name', 'NAME', 'LONG_NAME'];
        foreach ($genericKeys as $key) {
            if (isset($properties[$key]) && !empty($properties[$key])) {
                return trim($properties[$key]);
            }
        }

        return null;
    }

    /**
     * Extract Welsh name from properties
     */
    private function extractWelshName(array $properties): ?string
    {
        // Pattern-based matching for year-agnostic Welsh names
        // Matches: WD25NMW, LAD26NMW, etc.
        $welshPatterns = [
            '/^WD\d{2}NMW$/i',      // Ward Welsh names
            '/^LAD\d{2}NMW$/i',     // LAD Welsh names
            '/^PARNCP\d{2}NMW$/i',  // Parish Welsh names
            '/^CTYUA\d{2}NMW$/i',   // County Welsh names
            '/^PCON\d{2}NMW$/i',    // Westminster Constituency Welsh names
            '/^SENC\d{2}NMW$/i',    // Welsh Senedd Constituency Welsh names
            '/^SENER\d{2}NMW$/i',   // Welsh Senedd Region Welsh names
        ];

        // First try pattern matching
        foreach ($properties as $key => $value) {
            foreach ($welshPatterns as $pattern) {
                if (preg_match($pattern, $key) && !empty($value)) {
                    return trim($value);
                }
            }
        }

        // Fallback to generic Welsh property names
        $genericKeys = ['name_cy', 'NAME_CY', 'name_welsh', 'NAME_WELSH'];
        foreach ($genericKeys as $key) {
            if (isset($properties[$key]) && !empty($properties[$key])) {
                return trim($properties[$key]);
            }
        }

        return null;
    }

    /**
     * Extract area from properties (typically in hectares or square meters)
     */
    private function extractArea(array $properties): ?float
    {
        $possibleKeys = ['SHAPE_Area', 'Shape_Area', 'area', 'AREA', 'BNG_HECTARES', 'hectares'];

        foreach ($possibleKeys as $key) {
            if (isset($properties[$key]) && is_numeric($properties[$key])) {
                $area = (float) $properties[$key];

                // If area is very large, it's likely in square meters, convert to hectares
                if ($area > 1000000) {
                    $area = $area / 10000; // Convert square meters to hectares
                }

                return round($area, 2);
            }
        }

        return null;
    }

    /**
     * Calculate bounding box from geometry
     */
    private function calculateBoundingBox(array $geometry): string
    {
        $coordinates = $this->extractAllCoordinates($geometry);

        if (empty($coordinates)) {
            return '0,0,0,0';
        }

        $lngs = array_column($coordinates, 0);
        $lats = array_column($coordinates, 1);

        $minLng = min($lngs);
        $maxLng = max($lngs);
        $minLat = min($lats);
        $maxLat = max($lats);

        return "{$minLat},{$minLng},{$maxLat},{$maxLng}";
    }

    /**
     * Recursively extract all coordinates from geometry
     */
    private function extractAllCoordinates(array $geometry): array
    {
        $coords = [];

        if (!isset($geometry['coordinates'])) {
            return $coords;
        }

        $type = $geometry['type'] ?? null;

        if ($type === 'Point') {
            return [$geometry['coordinates']];
        }

        if ($type === 'Polygon') {
            foreach ($geometry['coordinates'] as $ring) {
                foreach ($ring as $coord) {
                    $coords[] = $coord;
                }
            }
        } elseif ($type === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as $coord) {
                        $coords[] = $coord;
                    }
                }
            }
        }

        return $coords;
    }

    /**
     * Insert geometry batch with upsert
     */
    private function insertGeometryBatch(array $batch): void
    {
        DB::table('boundary_geometries')->upsert(
            $batch,
            ['boundary_type', 'gss_code'], // Unique keys
            ['name', 'geometry', 'properties', 'area_hectares', 'bounding_box', 'source_file', 'version_date', 'updated_at']
        );
    }

    /**
     * Insert name batch with upsert
     */
    private function insertNameBatch(array $batch): void
    {
        DB::table('boundary_names')->upsert(
            $batch,
            ['boundary_type', 'gss_code'], // Unique keys
            ['name', 'name_welsh', 'source', 'version_date', 'updated_at']
        );
    }
}

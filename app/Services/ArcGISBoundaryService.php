<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Discovers and downloads boundary data from ONS ArcGIS REST services.
 */
class ArcGISBoundaryService
{
    private const CATALOG_URL = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services';

    private const ITEM_DOWNLOAD_URL = 'https://www.arcgis.com/sharing/rest/content/items';

    /**
     * Map boundary types to their ArcGIS service name patterns.
     * Prefers BGC (Generalised, Clipped 20m) over BFC (Full Resolution)
     * because BFC GeoJSON via REST is impossibly large (e.g. 92 MB for 9 regions).
     * BGC is perfectly adequate for web map visualisation.
     */
    private const BOUNDARY_PATTERNS = [
        'wards' => [
            '/^WD_[A-Z]{3}_\d{4}_UK_BGC$/',
            '/^Wards_.*_Boundaries_UK_BGC$/',
            '/^WD_[A-Z]{3}_\d{4}_UK_BFC$/',
            '/^Wards_.*_Boundaries_UK_BFC$/',
            '/^WD_[A-Z]{3}_\d{4}_UK_BFE$/',
            '/^Wards_.*_Boundaries_UK_BFE$/',
        ],
        'lad' => [
            '/^LAD_[A-Z]{3}_\d{4}_UK_BGC/',
            '/^LAD_.*_Boundaries_UK_BGC$/',
            '/^LAD_[A-Z]{3}_\d{4}_UK_BFC/',
            '/^LAD_.*_Boundaries_UK_BFC$/',
        ],
        'parishes' => [
            '/^PARNCP_[A-Z]{3}_\d{4}_EW_BGC$/',
            '/^Parishes_.*_Boundaries_EW_BGC$/',
            '/^PARNCP_[A-Z]{3}_\d{4}_EW_BFC$/',
            '/^Parishes_.*_Boundaries_EW_BFC$/',
        ],
        'ced' => [
            '/^CED_[A-Z]{3}_\d{4}_EN_BGC$/',
            '/^County_Electoral_Division_.*_Boundaries_EN_BGC$/',
            '/^CED_[A-Z]{3}_\d{4}_EN_BFC$/',
            '/^County_Electoral_Division_.*_Boundaries_EN_BFC$/',
        ],
        'constituencies' => [
            '/^Westminster_Parliamentary_Constituencies_.*_Boundaries_UK_BGC$/',
            '/^PCON_[A-Z]{3}_\d{4}_UK_BGC$/',
            '/^Westminster_Parliamentary_Constituencies_.*_Boundaries_UK_BFC$/',
            '/^PCON_[A-Z]{3}_\d{4}_UK_BFC$/',
        ],
        'region' => [
            '/^Regions_.*_Boundaries_EN_BGC$/',
            '/^RGN_[A-Z]{3}_\d{4}_EN_BGC$/',
            '/^Regions_.*_Boundaries_EN_BFC$/',
            '/^RGN_[A-Z]{3}_\d{4}_EN_BFC$/',
        ],
        'counties' => [
            '/^Counties_and_Unitary_Authorities_.*_Boundaries_UK_BGC$/',
            '/^CTYUA_[A-Z]{3}_\d{4}_UK_BGC$/',
            '/^Counties_and_Unitary_Authorities_.*_Boundaries_UK_BFC$/',
            '/^CTYUA_[A-Z]{3}_\d{4}_UK_BFC$/',
        ],
        'police_force_areas' => [
            '/^Police_Force_Areas_(Dec|December)_\d{4}_EW_BGC/',
            '/^Police_Force_Areas_(Dec|December)_\d{4}_EW_BFC/',
            '/^PFA_DEC_\d{4}_UK_NC$/',
            '/^PFA_DEC_\d{4}_UK_/',  // fallback for other UK variants
        ],
    ];

    /**
     * Regex patterns for discovering field names dynamically from service metadata.
     */
    private const CODE_FIELD_PATTERN = '/^[A-Z]{2,6}\d{2}CD$/i';
    private const NAME_FIELD_PATTERN = '/^[A-Z]{2,6}\d{2}NM$/i';
    private const WELSH_FIELD_PATTERN = '/^[A-Z]{2,6}\d{2}NMW$/i';

    /**
     * Discover the latest ArcGIS feature service for a boundary type.
     */
    public function discoverService(string $boundaryType): ?array
    {
        $patterns = self::BOUNDARY_PATTERNS[$boundaryType] ?? null;
        if (! $patterns) {
            Log::warning('No discovery patterns for boundary type', ['type' => $boundaryType]);

            return null;
        }

        try {
            $response = Http::timeout(30)->get(self::CATALOG_URL, ['f' => 'json']);
            if (! $response->successful()) {
                Log::warning('ArcGIS catalog request failed', [
                    'status' => $response->status(),
                    'type' => $boundaryType,
                ]);

                return null;
            }

            $catalog = $response->json('services', []);
            $matches = [];

            foreach ($catalog as $service) {
                $name = $service['name'] ?? '';
                $type = $service['type'] ?? '';

                // Skip MapServer entries — only FeatureServer supports /0/query
                if ($type !== 'FeatureServer') {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $name)) {
                        // Verify layer 0 actually has geometry (skip names/codes tables)
                        if (! $this->hasGeometry($service['url'])) {
                            Log::info('Skipping name-only service without geometry', [
                                'name' => $name,
                                'url' => $service['url'],
                            ]);
                            break;
                        }

                        $matches[] = [
                            'name' => $name,
                            'url' => $service['url'],
                            'version_date' => $this->extractVersionDate($name),
                        ];
                        break;
                    }
                }
            }

            if (empty($matches)) {
                Log::warning('No ArcGIS services found for boundary type', ['type' => $boundaryType]);

                return null;
            }

            // Sort by version date descending (null = oldest), then prefer BGC over BFC
            usort($matches, function (array $a, array $b): int {
                $dateA = $a['version_date'] ?? null;
                $dateB = $b['version_date'] ?? null;

                if ($dateA === null && $dateB !== null) {
                    return 1; // A is older (null)
                }
                if ($dateB === null && $dateA !== null) {
                    return -1; // B is older (null)
                }
                if ($dateA !== null && $dateB !== null) {
                    $dateCompare = $dateB <=> $dateA;
                    if ($dateCompare !== 0) {
                        return $dateCompare;
                    }
                }

                // Prefer BGC > BFE > BFC when dates match
                $scores = ['_BGC' => 3, '_BFE' => 2, '_BFC' => 1];
                $scoreA = 0;
                $scoreB = 0;
                foreach ($scores as $suffix => $score) {
                    if (str_contains($a['name'], $suffix)) {
                        $scoreA = $score;
                    }
                    if (str_contains($b['name'], $suffix)) {
                        $scoreB = $score;
                    }
                }

                return $scoreB <=> $scoreA;
            });

            $latest = $matches[0];

            Log::info('Discovered ArcGIS service for boundary type', [
                'type' => $boundaryType,
                'service' => $latest['name'],
                'version_date' => $latest['version_date'],
            ]);

            return $latest;

        } catch (\Throwable $e) {
            Log::error('ArcGIS service discovery failed', [
                'type' => $boundaryType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Download all GeoJSON features for a service, paginating past the record cap.
     */
    public function downloadGeoJson(string $serviceUrl, string $boundaryType): ?string
    {
        $fields = $this->discoverFields($serviceUrl);
        if (empty($fields['code']) || empty($fields['name'])) {
            Log::warning('Could not discover required fields from service metadata', [
                'url' => $serviceUrl,
                'type' => $boundaryType,
            ]);

            return null;
        }

        $outFields = implode(',', array_filter([$fields['code'], $fields['name'], $fields['welsh']]));

        $allFeatures = [];
        $offset = 0;
        $pageSize = 2000;
        $totalFeatures = 0;

        try {
            // Get total count first
            $countResponse = Http::timeout(30)->get(
                "{$serviceUrl}/0/query",
                [
                    'where' => '1=1',
                    'returnCountOnly' => 'true',
                    'f' => 'json',
                ]
            );

            if ($countResponse->successful()) {
                $totalFeatures = $countResponse->json('count', 0);
            }

            Log::info("Downloading {$totalFeatures} features for {$boundaryType}", [
                'fields' => $outFields,
            ]);

            while (true) {
                $response = Http::timeout(600)->get(
                    "{$serviceUrl}/0/query",
                    [
                        'where' => '1=1',
                        'outFields' => $outFields,
                        'outSR' => '4326',
                        'returnGeometry' => 'true',
                        'f' => 'geojson',
                        'resultOffset' => $offset,
                        'resultRecordCount' => $pageSize,
                    ]
                );

                if (! $response->successful()) {
                    Log::error('ArcGIS query failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $geoJson = $response->json();
                $features = $geoJson['features'] ?? [];

                if (empty($features)) {
                    break;
                }

                foreach ($features as $feature) {
                    $allFeatures[] = $feature;
                }

                $offset += count($features);

                if (count($features) < $pageSize) {
                    break;
                }
            }

            Log::info("Downloaded {$offset} features for {$boundaryType}");

            // Build complete GeoJSON
            $completeGeoJson = [
                'type' => 'FeatureCollection',
                'crs' => ['type' => 'name', 'properties' => ['name' => 'EPSG:4326']],
                'features' => $allFeatures,
            ];

            $filename = "{$boundaryType}_" . date('YmdHis') . '.geojson';
            $path = "boundaries/{$boundaryType}/{$filename}";
            Storage::put($path, json_encode($completeGeoJson));

            Log::info('GeoJSON saved to storage', ['path' => $path]);

            return $path;

        } catch (\Throwable $e) {
            Log::error('GeoJSON download failed', [
                'type' => $boundaryType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Check whether a FeatureServer layer 0 actually contains geometry.
     * Names/codes tables have geometryType = null, while boundary layers
     * have geometryType = esriGeometryPolygon.
     */
    private function hasGeometry(string $serviceUrl): bool
    {
        try {
            $response = Http::timeout(30)->get("{$serviceUrl}/0", ['f' => 'json']);

            if (! $response->successful()) {
                return false;
            }

            $geometryType = $response->json('geometryType');

            return ! empty($geometryType) && $geometryType !== 'esriGeometryNull';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Discover field names dynamically from the ArcGIS service layer metadata.
     */
    private function discoverFields(string $serviceUrl): array
    {
        try {
            $response = Http::timeout(30)->get("{$serviceUrl}/0", ['f' => 'json']);

            if (! $response->successful()) {
                Log::warning('Failed to fetch service metadata for field discovery', [
                    'url' => $serviceUrl,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $fields = $response->json('fields', []);
            $result = ['code' => null, 'name' => null, 'welsh' => null];

            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? '';

                if (! $result['code'] && preg_match(self::CODE_FIELD_PATTERN, $fieldName)) {
                    $result['code'] = $fieldName;
                }
                if (! $result['name'] && preg_match(self::NAME_FIELD_PATTERN, $fieldName)) {
                    $result['name'] = $fieldName;
                }
                if (! $result['welsh'] && preg_match(self::WELSH_FIELD_PATTERN, $fieldName)) {
                    $result['welsh'] = $fieldName;
                }
            }

            Log::info('Discovered fields from service metadata', [
                'url' => $serviceUrl,
                'fields' => $result,
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Field discovery failed', [
                'url' => $serviceUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract a version date string (YYYY-MM-01) from an ArcGIS service name.
     */
    private function extractVersionDate(string $serviceName): ?string
    {
        // Pattern: _DEC_2025_ or _December_2025_
        if (preg_match('/_([A-Z]{3})_(\d{4})_/', $serviceName, $matches)) {
            $monthAbbr = $matches[1];
            $year = $matches[2];
            $monthMap = [
                'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
            ];
            $month = $monthMap[$monthAbbr] ?? null;
            if ($month) {
                return "{$year}-{$month}-01";
            }
        }

        // Handles both _December_2024_ and _(December_2024)_
        if (preg_match('/_\(?([A-Za-z]+)_?(\d{4})\)?_/', $serviceName, $matches)) {
            $monthName = $matches[1];
            $year = $matches[2];
            $date = \DateTime::createFromFormat('F Y', "{$monthName} {$year}");
            if ($date) {
                return $date->format('Y-m-01');
            }
        }

        return null;
    }
}

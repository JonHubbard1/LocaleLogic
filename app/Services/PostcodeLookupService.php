<?php

namespace App\Services;

use App\Exceptions\PostcodeNotFoundException;
use App\Models\BoundaryName;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Postcode Lookup Service
 *
 * Handles business logic for postcode lookups with geography data aggregation.
 * Validates postcodes, queries properties, and aggregates geography information.
 */
class PostcodeLookupService
{
    // UK postcode regex pattern (5-8 characters with optional space)
    private const POSTCODE_PATTERN = '/^[A-Z]{1,2}[0-9R][0-9A-Z]?\s?[0-9][A-Z]{2}$/i';

    /**
     * Lookup postcode and return aggregated geography data
     *
     * @param string $postcode Raw postcode input (will be normalized)
     * @param bool $includeUprns Whether to include UPRN list
     * @return array Aggregated postcode data with geography
     * @throws PostcodeNotFoundException If postcode has no properties
     * @throws InvalidArgumentException If postcode format is invalid
     */
    public function lookup(string $postcode, bool $includeUprns = false): array
    {
        $normalizedPostcode = $this->normalizePostcode($postcode);
        $this->validatePostcode($normalizedPostcode);

        // Query properties for this postcode with eager-loaded relationships
        $properties = $this->getPropertiesWithGeography($normalizedPostcode);

        if ($properties->isEmpty()) {
            throw new PostcodeNotFoundException($normalizedPostcode);
        }

        // Use first property as representative for geography and coordinates
        $representative = $properties->first();

        return [
            'postcode' => $this->formatPostcode($normalizedPostcode),
            'coordinates' => $this->formatCoordinates($representative),
            'geography' => $this->formatGeography($representative),
            'property_count' => $properties->count(),
            'uprns' => $includeUprns ? $this->formatUprns($properties) : null,
        ];
    }

    /**
     * Normalize postcode to uppercase with standard spacing
     *
     * @param string $postcode Raw postcode
     * @return string Normalized postcode (uppercase with space before last 3 chars)
     */
    public function normalizePostcode(string $postcode): string
    {
        // Remove all whitespace
        $postcode = preg_replace('/\s+/', '', $postcode);

        // Convert to uppercase
        $postcode = strtoupper($postcode);

        // Add space before last 3 characters (standard UK format)
        if (strlen($postcode) >= 5) {
            $postcode = substr($postcode, 0, -3) . ' ' . substr($postcode, -3);
        }

        // Return without padding - database values are stored without trailing spaces
        return $postcode;
    }

    /**
     * Validate postcode format
     *
     * @param string $postcode Normalized postcode
     * @throws InvalidArgumentException If format is invalid
     */
    private function validatePostcode(string $postcode): void
    {
        $trimmed = trim($postcode);

        if (strlen($trimmed) < 5 || strlen($trimmed) > 8) {
            throw new InvalidArgumentException(
                'Invalid postcode format: must be between 5 and 8 characters'
            );
        }

        if (!preg_match(self::POSTCODE_PATTERN, $trimmed)) {
            throw new InvalidArgumentException(
                'Invalid postcode format: does not match UK postcode pattern'
            );
        }
    }

    /**
     * Query properties for the given postcode
     *
     * @param string $postcode Normalized postcode
     * @return Collection<Property>
     */
    private function getPropertiesWithGeography(string $postcode): Collection
    {
        return Property::where('pcds', $postcode)->get();
    }

    /**
     * Format coordinates for API response
     *
     * @param Property $property Representative property
     * @return array Formatted coordinates
     */
    private function formatCoordinates(Property $property): array
    {
        return [
            'wgs84' => [
                'latitude' => (float) $property->lat,
                'longitude' => (float) $property->lng,
            ],
            'os_grid' => [
                'easting' => $property->gridgb1e,
                'northing' => $property->gridgb1n,
            ],
        ];
    }

    /**
     * Format geography data for API response
     *
     * Extracts codes from property record and looks up human-readable names
     * from the boundary_names table.
     *
     * @param Property $property Representative property
     * @return array Formatted geography data
     */
    private function formatGeography(Property $property): array
    {
        return [
            'ward' => $this->formatGeographyCode($property->wd25cd, 'wards'),
            'county_electoral_division' => $this->formatGeographyCode($property->ced25cd, 'ced'),
            'parish' => $this->formatGeographyCode($property->parncp25cd, 'parish'),
            'local_authority_district' => $this->formatGeographyCode($property->lad25cd, 'lad'),
            'constituency' => $this->formatGeographyCode($property->pcon24cd, 'constituencies'),
            'region' => $this->formatGeographyCode($property->rgn25cd, 'region'),
            'police_force_area' => $this->formatGeographyCode($property->pfa23cd),
        ];
    }

    /**
     * Format a geography code with human-readable name lookup
     *
     * @param string|null $code GSS code
     * @param string|null $boundaryType Boundary type for name lookup (e.g., 'wards', 'parish', 'lad')
     * @return array|null Formatted item with code and name (if available)
     */
    private function formatGeographyCode(?string $code, ?string $boundaryType = null): ?array
    {
        if (!$code) {
            return null;
        }

        $code = trim($code);
        $name = null;
        $nameWelsh = null;

        // Look up name from boundary_names table if boundary type is provided
        if ($boundaryType) {
            $cacheKey = "boundary_name:{$boundaryType}:{$code}";

            $boundaryData = Cache::remember($cacheKey, 3600, function () use ($code, $boundaryType) {
                return BoundaryName::where('boundary_type', $boundaryType)
                    ->where('gss_code', $code)
                    ->first(['name', 'name_welsh']);
            });

            if ($boundaryData) {
                $name = $boundaryData->name;
                $nameWelsh = $boundaryData->name_welsh;
            }
        }

        $result = [
            'code' => $code,
            'name' => $name,
        ];

        // Only include Welsh name if it has a non-empty value
        if ($nameWelsh && $nameWelsh !== '') {
            $result['name_welsh'] = $nameWelsh;
        }

        return $result;
    }

    /**
     * Format UPRNs with coordinates for map plotting
     *
     * @param Collection<Property> $properties Properties to format
     * @return array Array of UPRNs with lat/lng coordinates
     */
    private function formatUprns(Collection $properties): array
    {
        return $properties->map(function (Property $property) {
            return [
                'uprn' => $property->uprn,
                'latitude' => (float) $property->lat,
                'longitude' => (float) $property->lng,
            ];
        })->values()->toArray();
    }

    /**
     * Format postcode for display (remove trailing padding)
     *
     * @param string $postcode Normalized postcode
     * @return string Display postcode
     */
    private function formatPostcode(string $postcode): string
    {
        return trim($postcode);
    }
}

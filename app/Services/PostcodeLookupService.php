<?php

namespace App\Services;

use App\Exceptions\PostcodeNotFoundException;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;
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
            'uprns' => $includeUprns ? $properties->pluck('uprn')->toArray() : null,
        ];
    }

    /**
     * Normalize postcode to uppercase with standard spacing
     *
     * @param string $postcode Raw postcode
     * @return string Normalized postcode (uppercase, 8 chars with space)
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

        // Pad to 8 characters (database format)
        return str_pad($postcode, 8, ' ', STR_PAD_RIGHT);
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
     * Query properties with eager-loaded geography relationships
     *
     * Optimized query using eager loading to avoid N+1 problems.
     * Only loads non-null relationships.
     *
     * @param string $postcode Normalized postcode
     * @return Collection<Property>
     */
    private function getPropertiesWithGeography(string $postcode): Collection
    {
        return Property::where('pcds', $postcode)
            ->with([
                'ward',
                'countyElectoralDivision',
                'parish',
                'localAuthorityDistrict',
                'constituency',
                'region',
                'policeForceArea',
            ])
            ->get();
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
     * Extracts codes and names from related geography models.
     * Handles nullable relationships gracefully.
     *
     * @param Property $property Representative property
     * @return array Formatted geography data
     */
    private function formatGeography(Property $property): array
    {
        return [
            'ward' => $this->formatGeographyItem(
                $property->ward,
                'wd25cd',
                'wd25nm'
            ),
            'county_electoral_division' => $this->formatGeographyItem(
                $property->countyElectoralDivision,
                'ced25cd',
                'ced25nm'
            ),
            'parish' => $this->formatGeographyItem(
                $property->parish,
                'parncp25cd',
                'parncp25nm'
            ),
            'local_authority_district' => $this->formatGeographyItem(
                $property->localAuthorityDistrict,
                'lad25cd',
                'lad25nm'
            ),
            'constituency' => $this->formatGeographyItem(
                $property->constituency,
                'pcon24cd',
                'pcon24nm'
            ),
            'region' => $this->formatGeographyItem(
                $property->region,
                'rgn25cd',
                'rgn25nm'
            ),
            'police_force_area' => $this->formatGeographyItem(
                $property->policeForceArea,
                'pfa23cd',
                'pfa23nm'
            ),
        ];
    }

    /**
     * Format a single geography item
     *
     * @param object|null $model Geography model
     * @param string $codeField Code field name
     * @param string $nameField Name field name
     * @return array|null Formatted item or null
     */
    private function formatGeographyItem($model, string $codeField, string $nameField): ?array
    {
        if (!$model) {
            return null;
        }

        return [
            'code' => $model->$codeField ?? null,
            'name' => $model->$nameField ?? null,
        ];
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

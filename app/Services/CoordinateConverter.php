<?php

namespace App\Services;

use InvalidArgumentException;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

/**
 * Converts coordinates between British National Grid (EPSG:27700) and WGS84 (EPSG:4326)
 *
 * This service handles coordinate transformation for UK property data,
 * converting OS Grid eastings/northings to latitude/longitude for API responses.
 */
class CoordinateConverter
{
    private Proj4php $proj4;
    private Proj $epsg27700;
    private Proj $epsg4326;

    // British National Grid valid coordinate ranges
    private const MIN_EASTING = 0;
    private const MAX_EASTING = 700000;
    private const MIN_NORTHING = 0;
    private const MAX_NORTHING = 1300000;

    public function __construct()
    {
        $this->proj4 = new Proj4php();

        // Initialize British National Grid projection (EPSG:27700) with proper datum transformation
        // The +towgs84 parameters provide accurate OSGB36 → WGS84 conversion using Helmert transformation
        $osgb36Definition = '+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 '
            . '+ellps=airy +towgs84=446.448,-125.157,542.060,0.1502,0.2470,0.8421,-20.4894 +units=m +no_defs';

        // Add custom definition using the addDef method
        $this->proj4->addDef('EPSG:27700', $osgb36Definition);
        $this->epsg27700 = new Proj('EPSG:27700', $this->proj4);

        // Initialize WGS84 projection (EPSG:4326) - standard lat/lng
        $this->epsg4326 = new Proj('EPSG:4326', $this->proj4);
    }

    /**
     * Convert a single OS Grid coordinate to WGS84 latitude/longitude
     *
     * @param int $easting OS Grid easting coordinate
     * @param int $northing OS Grid northing coordinate
     * @return array{lat: float, lng: float} Converted coordinates with 6 decimal places precision
     * @throws InvalidArgumentException If coordinates are outside valid British National Grid range
     */
    public function osGridToWgs84(int $easting, int $northing): array
    {
        $this->validateCoordinates($easting, $northing);

        try {
            $point = new Point($easting, $northing, $this->epsg27700);
            $transformedPoint = $this->proj4->transform($this->epsg4326, $point);

            return [
                'lat' => round($transformedPoint->y, 6),
                'lng' => round($transformedPoint->x, 6),
            ];
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Failed to convert coordinates (easting: {$easting}, northing: {$northing}): {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Convert multiple OS Grid coordinates to WGS84 in a single batch operation
     *
     * Optimized for bulk conversions during ONSUD import by reusing projection objects.
     *
     * @param array<int, array{easting: int, northing: int}> $coordinates Array of coordinate pairs
     * @return array<int, array{lat: float, lng: float}> Array of converted coordinates
     * @throws InvalidArgumentException If any coordinates are invalid
     */
    public function batchConvert(array $coordinates): array
    {
        $results = [];

        foreach ($coordinates as $index => $coord) {
            if (!isset($coord['easting']) || !isset($coord['northing'])) {
                throw new InvalidArgumentException(
                    "Invalid coordinate format at index {$index}. Expected 'easting' and 'northing' keys."
                );
            }

            $results[] = $this->osGridToWgs84($coord['easting'], $coord['northing']);
        }

        return $results;
    }

    /**
     * Validate that coordinates are within British National Grid bounds
     *
     * @param int $easting OS Grid easting coordinate
     * @param int $northing OS Grid northing coordinate
     * @throws InvalidArgumentException If coordinates are outside valid range
     */
    private function validateCoordinates(int $easting, int $northing): void
    {
        if ($easting < self::MIN_EASTING || $easting > self::MAX_EASTING) {
            throw new InvalidArgumentException(
                "Invalid easting: {$easting}. Must be between " . self::MIN_EASTING . " and " . self::MAX_EASTING
            );
        }

        if ($northing < self::MIN_NORTHING || $northing > self::MAX_NORTHING) {
            throw new InvalidArgumentException(
                "Invalid northing: {$northing}. Must be between " . self::MIN_NORTHING . " and " . self::MAX_NORTHING
            );
        }
    }
}

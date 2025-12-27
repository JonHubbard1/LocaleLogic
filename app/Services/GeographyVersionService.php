<?php

namespace App\Services;

use App\Exceptions\InvalidImportException;
use App\Models\GeographyVersion;

/**
 * Geography Version Service
 *
 * Manages versioning of geography data imports to prevent importing
 * older data over newer data. Tracks which year codes have been imported
 * for each geography type.
 */
class GeographyVersionService
{
    /**
     * Validate if importing a new geography version is allowed
     *
     * Checks if the new year code is same or newer than the current version.
     * First imports are always allowed.
     *
     * @param string $geoType The geography type (lad, ward, parish, etc.)
     * @param string $newYearCode The year code of the new data (25, 26, 27, etc.)
     * @return bool True if import is allowed
     * @throws InvalidImportException If trying to import older data over newer
     */
    public function validateImport(string $geoType, string $newYearCode): bool
    {
        $current = GeographyVersion::where('geography_type', $geoType)
            ->where('status', 'current')
            ->first();

        // If no current version exists, allow the import (first import)
        if (!$current) {
            return true;
        }

        // Check if new year code is greater than or equal to current
        if ($newYearCode < $current->year_code) {
            throw new InvalidImportException(
                "Cannot import {$geoType} year {$newYearCode} - current version is {$current->year_code}"
            );
        }

        return true;
    }

    /**
     * Record a new geography version import
     *
     * Archives the previous version (if any) and creates a new version record
     * marked as 'current'.
     *
     * @param string $geoType The geography type (lad, ward, parish, etc.)
     * @param string $yearCode The year code of the imported data (25, 26, 27, etc.)
     * @param int $recordCount The number of records imported
     * @param string|null $sourceFile The source CSV filename
     * @return void
     */
    public function recordImport(string $geoType, string $yearCode, int $recordCount, string|null $sourceFile = null): void
    {
        // Archive previous version if exists
        GeographyVersion::where('geography_type', $geoType)
            ->where('status', 'current')
            ->update(['status' => 'archived']);

        // Create new version record
        GeographyVersion::create([
            'geography_type' => $geoType,
            'year_code' => $yearCode,
            'record_count' => $recordCount,
            'source_file' => $sourceFile,
            'status' => 'current',
            'release_date' => now(),
            'imported_at' => now(),
        ]);
    }
}

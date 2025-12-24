<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Zero-downtime table replacement for ONSUD imports
 *
 * This service enables atomic table swaps for the properties table,
 * allowing validation of staging data before production deployment.
 */
class TableSwapService
{
    /**
     * Validate staging table has expected data before swap
     *
     * @param int $expectedCount Expected number of records in staging table
     * @return array{valid: bool, record_count: int, message: string} Validation result with details
     */
    public function validateStagingTable(int $expectedCount): array
    {
        $actualCount = DB::table('properties_staging')->count();

        if ($actualCount === 0) {
            return [
                'valid' => false,
                'record_count' => $actualCount,
                'message' => 'Staging table is empty. Cannot perform swap.',
            ];
        }

        if ($actualCount !== $expectedCount) {
            return [
                'valid' => false,
                'record_count' => $actualCount,
                'message' => "Record count mismatch. Expected {$expectedCount}, found {$actualCount}.",
            ];
        }

        $nullChecks = [
            'uprn' => DB::table('properties_staging')->whereNull('uprn')->count(),
            'lad25cd' => DB::table('properties_staging')->whereNull('lad25cd')->count(),
            'lat' => DB::table('properties_staging')->whereNull('lat')->count(),
            'lng' => DB::table('properties_staging')->whereNull('lng')->count(),
        ];

        foreach ($nullChecks as $column => $nullCount) {
            if ($nullCount > 0) {
                return [
                    'valid' => false,
                    'record_count' => $actualCount,
                    'message' => "Required column '{$column}' has {$nullCount} null values.",
                ];
            }
        }

        return [
            'valid' => true,
            'record_count' => $actualCount,
            'message' => 'Staging table validation passed.',
        ];
    }

    /**
     * Perform atomic table swap from staging to production
     *
     * Renames tables in this order:
     * 1. properties_staging -> properties_new
     * 2. properties -> properties_old
     * 3. properties_new -> properties
     *
     * @param int $expectedCount Expected record count for validation
     * @throws RuntimeException If validation fails or swap operation fails
     */
    public function swapPropertiesTable(int $expectedCount): void
    {
        $validation = $this->validateStagingTable($expectedCount);

        if (!$validation['valid']) {
            throw new RuntimeException(
                "Cannot swap tables: {$validation['message']}"
            );
        }

        try {
            DB::transaction(function () {
                DB::statement('ALTER TABLE properties_staging RENAME TO properties_new');
                DB::statement('ALTER TABLE properties RENAME TO properties_old');
                DB::statement('ALTER TABLE properties_new RENAME TO properties');
            });
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Table swap failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Rollback table swap by reversing rename operations
     *
     * This method restores the original state if swap validation fails
     * after the swap has been initiated.
     *
     * @throws RuntimeException If rollback operation fails
     */
    public function rollbackSwap(): void
    {
        try {
            DB::transaction(function () {
                if (Schema::hasTable('properties_old')) {
                    if (Schema::hasTable('properties')) {
                        DB::statement('ALTER TABLE properties RENAME TO properties_failed');
                    }
                    DB::statement('ALTER TABLE properties_old RENAME TO properties');
                }
            });
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Rollback failed: {$e->getMessage()}. Manual intervention required.",
                0,
                $e
            );
        }
    }

    /**
     * Drop old properties table after successful swap
     *
     * This frees disk space by removing the 41M row table.
     * Only execute after confirming new table is stable.
     *
     * @throws RuntimeException If drop operation fails
     */
    public function dropOldTable(): void
    {
        if (!Schema::hasTable('properties_old')) {
            return;
        }

        try {
            DB::statement('DROP TABLE properties_old');
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to drop old table: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}

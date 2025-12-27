<?php

namespace App\Services;

/**
 * CSV Header Detector Service
 *
 * Detects year codes from ONS CSV headers dynamically to handle
 * year-specific field names (e.g., LAD25CD, LAD26CD, WD25CD, WD26CD).
 *
 * This allows the import system to work with any year code without
 * hardcoding field names.
 */
class CsvHeaderDetectorService
{
    /**
     * Regex patterns for detecting geography type codes and their years
     *
     * @var array<string, string>
     */
    protected array $patterns = [
        'lad' => '/LAD(\d{2})CD/',
        'ward' => '/WD(\d{2})CD/',
        'parish' => '/PARNCP(\d{2})CD/',
        'ced' => '/CED(\d{2})CD/',
        'constituency' => '/PCON(\d{2})CD/',
        'region' => '/RGN(\d{2})CD/',
        'county' => '/CTY(\d{2})CD/',
        'pfa' => '/PFA(\d{2})CD/',
    ];

    /**
     * Detect year codes from CSV headers
     *
     * Scans through CSV headers and extracts the 2-digit year code from
     * each geography type field (e.g., '25' from 'LAD25CD', '26' from 'WD26CD').
     *
     * @param array<int, string> $headers CSV header row
     * @return array<string, string> Geography type => year code (e.g., ['lad' => '26', 'ward' => '26'])
     */
    public function detectYearCodes(array $headers): array
    {
        $result = [];

        foreach ($headers as $header) {
            foreach ($this->patterns as $type => $pattern) {
                if (preg_match($pattern, $header, $matches)) {
                    $result[$type] = $matches[1];
                    break; // Only match first occurrence of each type
                }
            }
        }

        return $result;
    }

    /**
     * Build field mapping from CSV headers
     *
     * Creates a mapping of geography types to their actual header names in the CSV,
     * allowing dynamic field access regardless of year code.
     *
     * @param array<int, string> $headers CSV header row
     * @return array<string, string> Geography type => header name (e.g., ['lad' => 'LAD26CD', 'ward' => 'WD26CD'])
     */
    public function buildFieldMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            foreach ($this->patterns as $type => $pattern) {
                if (preg_match($pattern, $header, $matches)) {
                    $mapping[$type] = $header;
                    break; // Only match first occurrence of each type
                }
            }
        }

        return $mapping;
    }
}

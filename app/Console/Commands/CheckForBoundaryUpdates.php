<?php

namespace App\Console\Commands;

use App\Models\BoundaryImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckForBoundaryUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'boundaries:check-updates
                            {--notify : Send notifications for updates found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check ONS Geoportal for new boundary file versions';

    /**
     * ONS ArcGIS Hub API search queries for each boundary type
     */
    protected array $searchQueries = [
        'wards' => 'BDY_WD',
        'parishes' => 'BDY_PARNCP',
        'lad' => 'BDY_LAD',
        'ced' => 'BDY_CED',
        'constituencies' => 'BDY_PCON',
        'police_force_areas' => 'BDY_PFA',
        'region' => 'BDY_RGN',
        'counties' => 'BDY_CTYUA',
    ];

    /**
     * ArcGIS Hub API base URL
     */
    protected string $apiBaseUrl = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services';

    /**
     * Expected update patterns for each boundary type
     */
    protected array $updateSchedule = [
        'wards' => ['month' => 'December', 'frequency' => 'annual'],
        'parishes' => ['month' => 'April', 'frequency' => 'annual'],
        'lad' => ['month' => 'April', 'frequency' => 'annual'],
        'ced' => ['month' => 'May', 'frequency' => 'annual'],
        'constituencies' => ['month' => 'July', 'frequency' => 'varies'],
        'police_force_areas' => ['month' => 'December', 'frequency' => 'varies'],
        'region' => ['month' => 'May', 'frequency' => 'annual'],
        'counties' => ['month' => 'December', 'frequency' => 'annual'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking ONS Geoportal for boundary updates...');
        $this->newLine();

        $updatesFound = [];

        foreach ($this->searchQueries as $boundaryType => $searchQuery) {
            $this->line("Checking {$boundaryType}...");

            try {
                // Get current version from database
                $currentVersion = $this->getCurrentVersion($boundaryType);

                // Check ONS for latest version
                $latestVersion = $this->checkOnsVersion($boundaryType, $searchQuery);

                if ($latestVersion) {
                    // Compare versions
                    if ($this->isNewerVersion($currentVersion, $latestVersion)) {
                        $searchUrl = "https://geoportal.statistics.gov.uk/search?q={$searchQuery}";
                        $updatesFound[] = [
                            'boundary_type' => $boundaryType,
                            'current_version' => $currentVersion ?? 'Not imported',
                            'latest_version' => $latestVersion,
                            'url' => $searchUrl,
                        ];

                        $this->warn("  ⚠ Update available: {$latestVersion}");
                    } else {
                        $this->info("  ✓ Up to date: {$currentVersion}");
                    }
                } else {
                    $this->error("  ✗ Could not determine latest version");
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error("Failed to check updates for {$boundaryType}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();

        // Display summary
        if (count($updatesFound) > 0) {
            $this->warn('Updates found for ' . count($updatesFound) . ' boundary type(s):');
            $this->newLine();

            $this->table(
                ['Boundary Type', 'Current Version', 'Latest Version', 'URL'],
                array_map(function ($update) {
                    return [
                        $update['boundary_type'],
                        $update['current_version'],
                        $update['latest_version'],
                        $update['url'],
                    ];
                }, $updatesFound)
            );

            // Send notifications if requested
            if ($this->option('notify')) {
                $this->sendNotifications($updatesFound);
            }

            return Command::FAILURE; // Return failure to indicate updates are available
        } else {
            $this->info('✓ All boundary files are up to date');
            return Command::SUCCESS;
        }
    }

    /**
     * Get current version from database
     */
    protected function getCurrentVersion(string $boundaryType): ?string
    {
        // Check boundary_geometries table for the latest version_date
        $geometry = DB::table('boundary_geometries')
            ->where('boundary_type', $boundaryType)
            ->orderBy('version_date', 'desc')
            ->first();

        return $geometry?->version_date;
    }

    /**
     * Check if update is due based on schedule
     * Since ONS portal is JavaScript-rendered, we use a schedule-based approach
     */
    protected function checkOnsVersion(string $boundaryType, string $searchQuery): ?string
    {
        // Get expected update month for this boundary type
        $schedule = $this->updateSchedule[$boundaryType] ?? null;

        if (!$schedule) {
            return null;
        }

        $expectedMonth = $schedule['month'];
        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Convert month name to number
        $monthMap = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
        ];

        $expectedMonthNum = $monthMap[$expectedMonth] ?? null;

        if (!$expectedMonthNum) {
            return null;
        }

        // Determine the expected version based on current date
        // If we're past the update month, expect current year version
        // If we're before the update month, expect last year's version
        if ($currentMonth >= $expectedMonthNum) {
            // We're past the update month, so current year version should exist
            $expectedYear = $currentYear;
        } else {
            // We haven't reached the update month yet, so last year's version is latest
            $expectedYear = $currentYear - 1;
        }

        // Format as YYYY-MM-01
        $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

        // Provide URL for manual checking
        $searchUrl = "https://geoportal.statistics.gov.uk/search?q={$searchQuery}";
        Log::info("Check ONS manually for {$boundaryType}", [
            'expected_version' => $expectedVersion,
            'url' => $searchUrl,
        ]);

        return $expectedVersion;
    }


    /**
     * Normalize date string to consistent format (YYYY-MM-DD)
     */
    protected function normalizeDate(string $dateStr): string
    {
        try {
            // Parse dates like "May 2025" or "December 2024"
            $date = \DateTime::createFromFormat('F Y', trim($dateStr));

            if ($date) {
                // Use the first day of the month
                return $date->format('Y-m-d');
            }

            // Fallback: just return the original string if parsing fails
            return trim($dateStr);

        } catch (\Exception $e) {
            return trim($dateStr);
        }
    }

    /**
     * Compare versions to determine if latest is newer
     */
    protected function isNewerVersion(?string $current, string $latest): bool
    {
        if ($current === null) {
            return true; // No current version, so latest is newer
        }

        // Try to compare as dates
        try {
            $currentDate = new \DateTime($current);
            $latestDate = new \DateTime($latest);

            return $latestDate > $currentDate;
        } catch (\Exception $e) {
            // Fallback to string comparison
            return $latest !== $current;
        }
    }

    /**
     * Send notifications about updates
     */
    protected function sendNotifications(array $updates): void
    {
        $this->info('Sending notifications...');

        // Log the updates
        Log::info('ONS Boundary updates available', [
            'updates' => $updates,
        ]);

        // TODO: Implement email/Slack notifications here
        // Example:
        // Notification::route('mail', config('app.admin_email'))
        //     ->notify(new BoundaryUpdatesAvailable($updates));

        $this->info('Notifications logged (email/Slack integration pending)');
    }
}

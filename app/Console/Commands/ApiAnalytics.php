<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use App\Services\ApiMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApiAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:analytics
                           {--date= : Date to analyze (YYYY-MM-DD), defaults to today}
                           {--days=7 : Number of days to analyze}
                           {--export : Export results to JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API usage analytics and statistics';

    private ApiMetricsService $metricsService;

    public function __construct(ApiMetricsService $metricsService)
    {
        parent::__construct();
        $this->metricsService = $metricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?? now()->format('Y-m-d');
        $days = (int) $this->option('days');

        $this->info("API Analytics Report");
        $this->info("==================\n");

        if ($days > 1) {
            $this->showMultiDayAnalytics($date, $days);
        } else {
            $this->showSingleDayAnalytics($date);
        }

        if ($this->option('export')) {
            $this->exportAnalytics($date, $days);
        }

        return 0;
    }

    /**
     * Show analytics for a single day
     */
    private function showSingleDayAnalytics(string $date): void
    {
        $this->line("Date: {$date}\n");

        // Overall statistics
        $stats = $this->getDayStats($date);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', number_format($stats['total'])],
                ['Successful (2xx)', number_format($stats['successful'])],
                ['Client Errors (4xx)', number_format($stats['client_errors'])],
                ['Server Errors (5xx)', number_format($stats['server_errors'])],
                ['Unique Users', number_format($stats['unique_users'])],
                ['Avg Response Time', $stats['avg_response_time'] . ' ms'],
            ]
        );

        // Top endpoints
        $this->line("\nTop Endpoints:");
        $topEndpoints = $this->getTopEndpoints($date, 10);
        $this->table(
            ['Endpoint', 'Requests', 'Avg Time (ms)', 'Errors'],
            $topEndpoints
        );

        // Error breakdown
        if ($stats['client_errors'] > 0 || $stats['server_errors'] > 0) {
            $this->line("\nError Breakdown:");
            $errors = $this->getErrorBreakdown($date);
            $this->table(
                ['Error Code', 'Count'],
                $errors
            );
        }

        // Hourly distribution
        $this->line("\nHourly Distribution:");
        $hourly = $this->getHourlyDistribution($date);
        $this->displayHourlyChart($hourly);

        // Top users
        $this->line("\nTop Users:");
        $topUsers = $this->getTopUsers($date, 10);
        $this->table(
            ['User Email', 'Requests', 'Errors'],
            $topUsers
        );
    }

    /**
     * Show analytics for multiple days
     */
    private function showMultiDayAnalytics(string $endDate, int $days): void
    {
        $startDate = now()->parse($endDate)->subDays($days - 1)->format('Y-m-d');
        $this->line("Period: {$startDate} to {$endDate} ({$days} days)\n");

        $dailyStats = [];
        $currentDate = now()->parse($startDate);

        for ($i = 0; $i < $days; $i++) {
            $date = $currentDate->format('Y-m-d');
            $stats = $this->getDayStats($date);
            $dailyStats[] = [
                $date,
                number_format($stats['total']),
                number_format($stats['successful']),
                number_format($stats['client_errors'] + $stats['server_errors']),
                $stats['avg_response_time'] . ' ms',
            ];
            $currentDate->addDay();
        }

        $this->table(
            ['Date', 'Total', 'Success', 'Errors', 'Avg Time'],
            $dailyStats
        );

        // Overall period statistics
        $periodStats = $this->getPeriodStats($startDate, $endDate);
        $this->line("\nPeriod Summary:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', number_format($periodStats['total'])],
                ['Daily Average', number_format($periodStats['daily_avg'])],
                ['Success Rate', $periodStats['success_rate'] . '%'],
                ['Error Rate', $periodStats['error_rate'] . '%'],
            ]
        );
    }

    /**
     * Get statistics for a single day
     */
    private function getDayStats(string $date): array
    {
        $logs = ApiLog::forDate($date);

        return [
            'total' => $logs->count(),
            'successful' => $logs->clone()->where('status_code', '<', 400)->count(),
            'client_errors' => $logs->clone()->whereBetween('status_code', [400, 499])->count(),
            'server_errors' => $logs->clone()->where('status_code', '>=', 500)->count(),
            'unique_users' => $logs->clone()->whereNotNull('user_id')->distinct('user_id')->count(),
            'avg_response_time' => round($logs->clone()->avg('duration_ms') ?? 0, 2),
        ];
    }

    /**
     * Get period statistics
     */
    private function getPeriodStats(string $startDate, string $endDate): array
    {
        $logs = ApiLog::betweenDates($startDate, $endDate);

        $total = $logs->count();
        $successful = $logs->clone()->where('status_code', '<', 400)->count();
        $days = now()->parse($startDate)->diffInDays(now()->parse($endDate)) + 1;

        return [
            'total' => $total,
            'daily_avg' => $total > 0 ? round($total / $days) : 0,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'error_rate' => $total > 0 ? round((($total - $successful) / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get top endpoints
     */
    private function getTopEndpoints(string $date, int $limit): array
    {
        $endpoints = ApiLog::forDate($date)
            ->select('path')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(duration_ms) as avg_time')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $endpoints->map(function ($endpoint) {
            return [
                $endpoint->path,
                number_format($endpoint->count),
                round($endpoint->avg_time, 2),
                $endpoint->errors,
            ];
        })->toArray();
    }

    /**
     * Get error breakdown
     */
    private function getErrorBreakdown(string $date): array
    {
        $errors = ApiLog::forDate($date)
            ->whereNotNull('error_code')
            ->select('error_code')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->get();

        return $errors->map(function ($error) {
            return [$error->error_code, number_format($error->count)];
        })->toArray();
    }

    /**
     * Get hourly distribution
     */
    private function getHourlyDistribution(string $date): array
    {
        $hourly = ApiLog::forDate($date)
            ->selectRaw('EXTRACT(HOUR FROM created_at) as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $distribution = [];
        for ($i = 0; $i < 24; $i++) {
            $distribution[$i] = $hourly[$i] ?? 0;
        }

        return $distribution;
    }

    /**
     * Display hourly chart
     */
    private function displayHourlyChart(array $hourly): void
    {
        $max = max($hourly) ?: 1;

        foreach ($hourly as $hour => $count) {
            $hourStr = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
            $bars = round(($count / $max) * 50);
            $bar = str_repeat('█', $bars);
            $this->line("{$hourStr} | {$bar} {$count}");
        }
    }

    /**
     * Get top users
     */
    private function getTopUsers(string $date, int $limit): array
    {
        $users = ApiLog::forDate($date)
            ->whereNotNull('user_email')
            ->select('user_email')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->groupBy('user_email')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $users->map(function ($user) {
            return [$user->user_email, number_format($user->count), $user->errors];
        })->toArray();
    }

    /**
     * Export analytics to JSON file
     */
    private function exportAnalytics(string $date, int $days): void
    {
        $filename = "api_analytics_{$date}_" . now()->format('His') . ".json";
        $path = storage_path("logs/{$filename}");

        $data = [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'date' => $date,
                'days' => $days,
            ],
            'statistics' => $this->getDayStats($date),
            'top_endpoints' => $this->getTopEndpoints($date, 20),
            'error_breakdown' => $this->getErrorBreakdown($date),
            'hourly_distribution' => $this->getHourlyDistribution($date),
        ];

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("\nAnalytics exported to: {$path}");
    }
}

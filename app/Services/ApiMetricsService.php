<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * API Metrics Service
 *
 * Tracks and aggregates API usage metrics including:
 * - Request counts by endpoint
 * - Response times (avg, min, max)
 * - Error rates by type
 * - User activity
 * - Geographic distribution
 */
class ApiMetricsService
{
    private const CACHE_PREFIX = 'api_metrics:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Record API request metrics
     *
     * @param array $data Request data
     */
    public function recordRequest(array $data): void
    {
        $date = now()->format('Y-m-d');
        $hour = now()->format('H');

        // Increment request counters
        $this->incrementCounter("requests:total:{$date}");
        $this->incrementCounter("requests:hourly:{$date}:{$hour}");
        $this->incrementCounter("requests:endpoint:{$data['path']}:{$date}");
        $this->incrementCounter("requests:status:{$data['status_code']}:{$date}");

        // Track user activity
        if (isset($data['user_id'])) {
            $this->incrementCounter("requests:user:{$data['user_id']}:{$date}");
        }

        // Track errors
        if ($data['status_code'] >= 400) {
            $this->incrementCounter("errors:total:{$date}");
            $this->incrementCounter("errors:code:{$data['status_code']}:{$date}");

            if (isset($data['error']['code'])) {
                $this->incrementCounter("errors:type:{$data['error']['code']}:{$date}");
            }
        }

        // Track response times
        $this->recordResponseTime($data['path'], $data['duration_ms']);

        // Track query parameters usage
        if (!empty($data['query_params'])) {
            foreach (array_keys($data['query_params']) as $param) {
                $this->incrementCounter("params:{$param}:{$date}");
            }
        }
    }

    /**
     * Record response time for performance tracking
     *
     * @param string $endpoint
     * @param float $duration
     */
    private function recordResponseTime(string $endpoint, float $duration): void
    {
        $key = self::CACHE_PREFIX . "response_times:{$endpoint}:" . now()->format('Y-m-d');

        $times = Cache::get($key, []);
        $times[] = $duration;

        // Keep only last 1000 measurements to prevent memory issues
        if (count($times) > 1000) {
            array_shift($times);
        }

        Cache::put($key, $times, self::CACHE_TTL);
    }

    /**
     * Increment a metric counter
     *
     * @param string $key
     */
    private function incrementCounter(string $key): void
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $currentValue = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentValue + 1, self::CACHE_TTL);
    }

    /**
     * Get API usage statistics for a date
     *
     * @param string|null $date
     * @return array
     */
    public function getStats(?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');

        return [
            'date' => $date,
            'total_requests' => $this->getCounter("requests:total:{$date}"),
            'successful_requests' => $this->getCounter("requests:status:200:{$date}"),
            'client_errors' => $this->getCounter("errors:code:4*:{$date}"),
            'server_errors' => $this->getCounter("errors:code:5*:{$date}"),
            'unique_users' => $this->getUniqueUserCount($date),
            'top_endpoints' => $this->getTopEndpoints($date, 5),
            'error_breakdown' => $this->getErrorBreakdown($date),
            'avg_response_time' => $this->getAverageResponseTime($date),
        ];
    }

    /**
     * Get hourly request distribution
     *
     * @param string|null $date
     * @return array
     */
    public function getHourlyDistribution(?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $distribution = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hourStr = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $distribution[$hourStr] = $this->getCounter("requests:hourly:{$date}:{$hourStr}");
        }

        return $distribution;
    }

    /**
     * Get top endpoints by request count
     *
     * @param string $date
     * @param int $limit
     * @return array
     */
    private function getTopEndpoints(string $date, int $limit = 10): array
    {
        $pattern = self::CACHE_PREFIX . "requests:endpoint:*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        $endpoints = [];
        foreach ($keys as $key) {
            $endpoint = str_replace([self::CACHE_PREFIX . "requests:endpoint:", ":{$date}"], '', $key);
            $count = Cache::get($key, 0);
            $endpoints[$endpoint] = $count;
        }

        arsort($endpoints);
        return array_slice($endpoints, 0, $limit, true);
    }

    /**
     * Get error breakdown by error code
     *
     * @param string $date
     * @return array
     */
    private function getErrorBreakdown(string $date): array
    {
        $pattern = self::CACHE_PREFIX . "errors:type:*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        $errors = [];
        foreach ($keys as $key) {
            $errorCode = str_replace([self::CACHE_PREFIX . "errors:type:", ":{$date}"], '', $key);
            $count = Cache::get($key, 0);
            $errors[$errorCode] = $count;
        }

        arsort($errors);
        return $errors;
    }

    /**
     * Get average response time for a date
     *
     * @param string $date
     * @return float
     */
    private function getAverageResponseTime(string $date): float
    {
        $pattern = self::CACHE_PREFIX . "response_times:*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        $allTimes = [];
        foreach ($keys as $key) {
            $times = Cache::get($key, []);
            $allTimes = array_merge($allTimes, $times);
        }

        if (empty($allTimes)) {
            return 0;
        }

        return round(array_sum($allTimes) / count($allTimes), 2);
    }

    /**
     * Get unique user count for a date
     *
     * @param string $date
     * @return int
     */
    private function getUniqueUserCount(string $date): int
    {
        $pattern = self::CACHE_PREFIX . "requests:user:*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        return count($keys);
    }

    /**
     * Get counter value
     *
     * @param string $key
     * @return int
     */
    private function getCounter(string $key): int
    {
        // Handle wildcard patterns
        if (strpos($key, '*') !== false) {
            $pattern = self::CACHE_PREFIX . $key;
            $keys = $this->scanCacheKeys($pattern);

            $total = 0;
            foreach ($keys as $cacheKey) {
                $total += Cache::get($cacheKey, 0);
            }

            return $total;
        }

        return Cache::get(self::CACHE_PREFIX . $key, 0);
    }

    /**
     * Scan cache for keys matching pattern
     *
     * Note: This is a simple implementation. For production with Redis,
     * use SCAN instead of KEYS for better performance.
     *
     * @param string $pattern
     * @return array
     */
    private function scanCacheKeys(string $pattern): array
    {
        // This is a simplified version - in production, use Redis SCAN
        // or store metrics in a database for better querying
        try {
            // Attempt to use Redis SCAN if available
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->connection();
                return $redis->keys($pattern);
            }
        } catch (\Exception $e) {
            Log::warning("Cache scan not available: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Clear all metrics for a date
     *
     * @param string $date
     */
    public function clearMetrics(string $date): void
    {
        $pattern = self::CACHE_PREFIX . "*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get performance metrics
     *
     * @param string|null $date
     * @return array
     */
    public function getPerformanceMetrics(?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $pattern = self::CACHE_PREFIX . "response_times:*:{$date}";
        $keys = $this->scanCacheKeys($pattern);

        $metrics = [];
        foreach ($keys as $key) {
            $endpoint = str_replace([self::CACHE_PREFIX . "response_times:", ":{$date}"], '', $key);
            $times = Cache::get($key, []);

            if (!empty($times)) {
                $metrics[$endpoint] = [
                    'avg' => round(array_sum($times) / count($times), 2),
                    'min' => round(min($times), 2),
                    'max' => round(max($times), 2),
                    'count' => count($times),
                ];
            }
        }

        return $metrics;
    }
}

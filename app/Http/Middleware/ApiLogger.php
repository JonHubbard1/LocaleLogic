<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Services\ApiMetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Request/Response Logger Middleware
 *
 * Logs comprehensive details about API requests including:
 * - Request metadata (method, path, IP, user agent)
 * - Authentication details (user ID, token)
 * - Response status and timing
 * - Query parameters and errors
 */
class ApiLogger
{
    private ApiMetricsService $metricsService;

    public function __construct(ApiMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Record start time
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Calculate response time
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        // Build log context
        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'query_params' => $request->query(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add error details for failed requests
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            $decoded = json_decode($content, true);

            $context['error'] = [
                'code' => $decoded['error']['code'] ?? 'UNKNOWN',
                'message' => $decoded['error']['message'] ?? $decoded['message'] ?? 'Unknown error',
            ];
        }

        // Log based on status code
        if ($response->getStatusCode() >= 500) {
            Log::error('API Request Failed (5xx)', $context);
        } elseif ($response->getStatusCode() >= 400) {
            Log::warning('API Request Error (4xx)', $context);
        } else {
            Log::info('API Request Success', $context);
        }

        // Record metrics
        try {
            $this->metricsService->recordRequest($context);
        } catch (\Exception $e) {
            Log::error('Failed to record API metrics: ' . $e->getMessage());
        }

        // Save to database (async to not impact response time)
        try {
            ApiLog::create([
                'method' => $context['method'],
                'path' => $context['path'],
                'url' => $context['url'],
                'query_params' => $context['query_params'],
                'user_id' => $context['user_id'],
                'user_email' => $context['user_email'],
                'ip_address' => $context['ip'],
                'user_agent' => $context['user_agent'],
                'status_code' => $context['status_code'],
                'duration_ms' => $context['duration_ms'],
                'error_code' => $context['error']['code'] ?? null,
                'error_message' => $context['error']['message'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save API log to database: ' . $e->getMessage());
        }

        // Add response headers for debugging
        $response->headers->set('X-Response-Time', $duration . 'ms');
        $response->headers->set('X-API-Version', '1.0');

        return $response;
    }
}

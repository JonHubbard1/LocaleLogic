# API Monitoring & Logging

Complete guide to monitoring, logging, and analytics for the Postcode Lookup API.

## Table of Contents

- [Overview](#overview)
- [Logging System](#logging-system)
- [Metrics Collection](#metrics-collection)
- [Analytics Commands](#analytics-commands)
- [Database Logs](#database-logs)
- [Log Files](#log-files)
- [Performance Monitoring](#performance-monitoring)
- [Alerts & Notifications](#alerts--notifications)

---

## Overview

The API includes comprehensive monitoring and logging capabilities to track:
- Request/response details
- Performance metrics (response times)
- Error rates and types
- User activity
- Endpoint usage patterns

### Architecture

```
API Request
    ↓
ApiLogger Middleware
    ├─→ File Logs (storage/logs/laravel.log)
    ├─→ Database (api_logs table)
    └─→ Metrics Cache (Redis/File cache)
```

---

## Logging System

### What Gets Logged

Every API request logs the following:

| Field | Description |
|-------|-------------|
| **Request** | Method, path, full URL, query parameters |
| **User** | User ID, email (if authenticated) |
| **Client** | IP address, user agent |
| **Response** | Status code, response time (ms) |
| **Errors** | Error code, error message (if failed) |
| **Timestamp** | ISO 8601 timestamp |

### Log Levels

- **INFO**: Successful requests (2xx status codes)
- **WARNING**: Client errors (4xx status codes)
- **ERROR**: Server errors (5xx status codes)

### Example Log Entry

```json
{
  "message": "API Request Success",
  "context": {
    "method": "GET",
    "path": "api/v1/postcodes/SW1A1AA",
    "url": "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA?include=uprns",
    "ip": "203.0.113.42",
    "user_agent": "PostmanRuntime/7.32.0",
    "user_id": 1,
    "user_email": "api@test.com",
    "status_code": 200,
    "duration_ms": 45.23,
    "query_params": {"include": "uprns"},
    "timestamp": "2025-12-24T00:44:18+00:00"
  }
}
```

---

## Metrics Collection

Metrics are collected in real-time and stored in cache for quick access.

### Tracked Metrics

1. **Request Counts**
   - Total requests per day
   - Requests per hour
   - Requests per endpoint
   - Requests per user

2. **Response Times**
   - Average response time
   - Min/max response times
   - Response time per endpoint

3. **Error Rates**
   - Total errors per day
   - Errors by status code
   - Errors by error type
   - Errors by endpoint

4. **User Activity**
   - Unique users per day
   - Requests per user
   - Most active users

### Cache Structure

Metrics use a hierarchical cache key structure:

```
api_metrics:requests:total:2025-12-24
api_metrics:requests:hourly:2025-12-24:14
api_metrics:requests:endpoint:api/v1/postcodes/{postcode}:2025-12-24
api_metrics:requests:user:1:2025-12-24
api_metrics:errors:type:POSTCODE_NOT_FOUND:2025-12-24
api_metrics:response_times:api/v1/postcodes/{postcode}:2025-12-24
```

---

## Analytics Commands

### Generate Analytics Report

```bash
php artisan api:analytics
```

**Options:**
- `--date=YYYY-MM-DD` - Date to analyze (defaults to today)
- `--days=N` - Number of days to analyze (defaults to 7)
- `--export` - Export results to JSON file

### Examples

**Today's analytics:**
```bash
php artisan api:analytics
```

**Specific date:**
```bash
php artisan api:analytics --date=2025-12-20
```

**Last 30 days:**
```bash
php artisan api:analytics --days=30
```

**Export to JSON:**
```bash
php artisan api:analytics --export
# Output: storage/logs/api_analytics_2025-12-24_143022.json
```

### Sample Output

```
API Analytics Report
==================

Date: 2025-12-24

+----------------------+----------+
| Metric               | Value    |
+----------------------+----------+
| Total Requests       | 1,247    |
| Successful (2xx)     | 1,189    |
| Client Errors (4xx)  | 52       |
| Server Errors (5xx)  | 6        |
| Unique Users         | 23       |
| Avg Response Time    | 67.3 ms  |
+----------------------+----------+

Top Endpoints:
+----------------------------------+----------+-----------------+--------+
| Endpoint                         | Requests | Avg Time (ms)   | Errors |
+----------------------------------+----------+-----------------+--------+
| api/v1/postcodes/SW1A1AA         | 342      | 45.2            | 5      |
| api/v1/postcodes/M11AA           | 198      | 52.1            | 2      |
+----------------------------------+----------+-----------------+--------+

Hourly Distribution:
00:00 | ████ 12
01:00 | ██ 5
02:00 | █ 2
...
14:00 | ██████████████████████████████ 156
```

---

## Database Logs

### api_logs Table Schema

```sql
CREATE TABLE api_logs (
    id BIGINT PRIMARY KEY,
    method VARCHAR(10),
    path VARCHAR(255),
    url TEXT,
    query_params JSON,
    user_id BIGINT,
    user_email VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    status_code SMALLINT,
    duration_ms DECIMAL(10,2),
    error_code VARCHAR(50),
    error_message TEXT,
    created_at TIMESTAMP
);
```

### Querying Logs

**Get today's successful requests:**
```php
$logs = ApiLog::forDate(today())
    ->successful()
    ->get();
```

**Get failed requests for a user:**
```php
$logs = ApiLog::where('user_id', 1)
    ->failed()
    ->get();
```

**Get slow requests (>500ms):**
```php
$slowRequests = ApiLog::where('duration_ms', '>', 500)
    ->orderByDesc('duration_ms')
    ->get();
```

**Get error breakdown:**
```php
$errors = ApiLog::whereNotNull('error_code')
    ->select('error_code', DB::raw('count(*) as count'))
    ->groupBy('error_code')
    ->get();
```

---

## Log Files

### Location

All logs are stored in:
```
storage/logs/laravel.log
```

### Log Rotation

Configure log rotation in `.env`:

```env
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14
```

### Viewing Logs

**Tail real-time logs:**
```bash
tail -f storage/logs/laravel.log
```

**Search for errors:**
```bash
grep "API Request Failed" storage/logs/laravel.log
```

**Filter by date:**
```bash
grep "2025-12-24" storage/logs/laravel.log
```

---

## Performance Monitoring

### Response Time Headers

Every API response includes performance headers:

```http
X-Response-Time: 45.23ms
X-API-Version: 1.0
```

### Performance Thresholds

| Category | Threshold | Action |
|----------|-----------|--------|
| Fast | < 100ms | Normal operation |
| Acceptable | 100-500ms | Monitor |
| Slow | 500ms-2s | Investigate |
| Critical | > 2s | Alert required |

### Monitoring Response Times

**Get average response time:**
```bash
php artisan api:analytics --date=2025-12-24 | grep "Avg Response Time"
```

**Export performance data:**
```bash
php artisan api:analytics --export
# Check "top_endpoints" in JSON for per-endpoint performance
```

---

## Alerts & Notifications

### Setting Up Alerts

For production environments, consider setting up alerts for:

1. **Error Rate Threshold**
   - Alert when error rate > 5% over 1 hour

2. **Response Time Degradation**
   - Alert when avg response time > 500ms for 10 minutes

3. **High Traffic**
   - Alert when requests > 1000/hour (unexpected spike)

4. **Server Errors**
   - Immediately alert on any 5xx errors

### Example Alert Implementation

**Laravel Scheduled Task (app/Console/Kernel.php):**

```php
protected function schedule(Schedule $schedule)
{
    // Daily analytics report
    $schedule->command('api:analytics --export')
        ->daily()
        ->at('00:00');

    // Hourly error check
    $schedule->call(function () {
        $errors = ApiLog::where('created_at', '>', now()->subHour())
            ->where('status_code', '>=', 500)
            ->count();

        if ($errors > 10) {
            // Send alert notification
            Mail::to('admin@example.com')->send(new HighErrorRateAlert($errors));
        }
    })->hourly();
}
```

---

## Best Practices

### 1. Regular Monitoring

- Review analytics daily
- Export weekly reports for trend analysis
- Monitor error patterns

### 2. Log Retention

- Keep database logs for 30-90 days
- Archive older logs to object storage
- Set up automatic cleanup:

```php
// Clean logs older than 90 days
ApiLog::where('created_at', '<', now()->subDays(90))->delete();
```

### 3. Performance Optimization

- Create indexes on frequently queried fields
- Use cache for real-time metrics
- Implement log aggregation for high-traffic APIs

### 4. Security & Privacy

- Sanitize sensitive data from logs
- Implement log access controls
- Comply with data retention policies

---

## Troubleshooting

### High Response Times

1. Check database query performance
2. Review endpoint-specific metrics
3. Analyze slow query logs
4. Check for missing indexes

### Missing Logs

1. Verify middleware is registered
2. Check file permissions on `storage/logs`
3. Ensure database migration ran successfully
4. Check cache driver configuration

### Metrics Not Updating

1. Clear cache: `php artisan cache:clear`
2. Verify cache driver is working
3. Check ApiMetricsService integration
4. Review error logs for exceptions

---

## Maintenance

### Daily Tasks

- Review error logs
- Check response time trends
- Monitor user activity

### Weekly Tasks

- Export analytics reports
- Analyze error patterns
- Review top users/endpoints

### Monthly Tasks

- Clean up old logs
- Review and optimize indexes
- Update alert thresholds
- Capacity planning review

---

## Additional Resources

- [Laravel Logging Documentation](https://laravel.com/docs/10.x/logging)
- [Laravel Cache Documentation](https://laravel.com/docs/10.x/cache)
- [Eloquent Query Scopes](https://laravel.com/docs/10.x/eloquent#query-scopes)

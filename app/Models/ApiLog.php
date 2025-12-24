<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API Log Model
 *
 * Stores detailed logs of API requests for monitoring and analytics.
 */
class ApiLog extends Model
{
    public const UPDATED_AT = null; // Only track created_at

    protected $fillable = [
        'method',
        'path',
        'url',
        'query_params',
        'user_id',
        'user_email',
        'ip_address',
        'user_agent',
        'status_code',
        'duration_ms',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'query_params' => 'array',
            'duration_ms' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user who made the request
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful requests
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '<', 400);
    }

    /**
     * Scope for failed requests
     */
    public function scopeFailed($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope for a specific date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('created_at', $date);
    }

    /**
     * Scope for date range
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}

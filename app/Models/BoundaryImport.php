<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundaryImport extends Model
{
    protected $fillable = [
        'boundary_type',
        'data_type',
        'status',
        'source',
        'file_path',
        'file_size',
        'records_total',
        'records_processed',
        'records_failed',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
            'records_total' => 'integer',
            'records_processed' => 'integer',
            'records_failed' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Scope to filter by boundary type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('boundary_type', $type);
    }

    /**
     * Scope to filter by data type.
     */
    public function scopeDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent imports.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Check if the import is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the import failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the import is still processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the import is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->records_total === 0) {
            return 0;
        }

        return round(($this->records_processed / $this->records_total) * 100, 2);
    }

    /**
     * Mark the import as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the import as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the import as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Increment the processed records count.
     */
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('records_processed', $count);
    }

    /**
     * Increment the failed records count.
     */
    public function incrementFailed(int $count = 1): void
    {
        $this->increment('records_failed', $count);
    }
}

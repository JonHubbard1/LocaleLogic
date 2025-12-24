<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DataVersion Model
 *
 * Tracks ONSUD import history and current version for 6-weekly updates.
 * Enables version tracking and rollback identification.
 * Unique constraint prevents duplicate dataset version entries.
 */
class DataVersion extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'data_versions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'dataset',
        'epoch',
        'release_date',
        'imported_at',
        'record_count',
        'file_hash',
        'status',
        'notes',
        'progress_percentage',
        'status_message',
        'current_file',
        'total_files',
        'files',
        'log_file',
        'stats',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'imported_at' => 'datetime',
            'progress_percentage' => 'decimal:2',
            'files' => 'array',
            'stats' => 'array',
        ];
    }
}

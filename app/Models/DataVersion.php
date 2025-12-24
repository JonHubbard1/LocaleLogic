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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'release_date' => 'date',
        'imported_at' => 'datetime',
    ];
}

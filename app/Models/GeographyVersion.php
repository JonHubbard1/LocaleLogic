<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Geography Version Model
 *
 * Tracks which year codes have been imported for different geography types.
 * Used to prevent importing older data over newer data.
 *
 * @property int $id
 * @property string $geography_type Geography type (lad, ward, parish, etc.)
 * @property string $year_code Two-digit year code (25, 26, 27, etc.)
 * @property \Illuminate\Support\Carbon $release_date ONS release date
 * @property \Illuminate\Support\Carbon $imported_at When we imported this data
 * @property int $record_count Number of records imported
 * @property string|null $source_file CSV filename
 * @property string $status Import status (current, archived, importing)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GeographyVersion extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'geography_versions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'geography_type',
        'year_code',
        'release_date',
        'imported_at',
        'record_count',
        'source_file',
        'status',
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
            'record_count' => 'integer',
        ];
    }
}

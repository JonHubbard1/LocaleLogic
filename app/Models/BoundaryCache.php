<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BoundaryCache Model
 *
 * Stores GeoJSON polygons fetched from ONS Open Geography Portal API.
 * Supports caching of boundaries for various geography types with different resolutions.
 * Unique constraint prevents duplicate entries for same geography type, code, and resolution.
 */
class BoundaryCache extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'boundary_caches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'geography_type',
        'geography_code',
        'boundary_resolution',
        'geojson',
        'fetched_at',
        'expires_at',
        'source_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fetched_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}

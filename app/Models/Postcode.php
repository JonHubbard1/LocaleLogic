<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Postcode extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'pcd7';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pcd7',
        'pcd8',
        'pcds',
        'dointr',
        'doterm',
        'lat',
        'lng',
        'east1m',
        'north1m',
        'oa21cd',
        'lsoa21cd',
        'msoa21cd',
        'lad25cd',
        'wd25cd',
        'ced25cd',
        'parncp25cd',
        'pcon24cd',
        'rgn25cd',
        'ctry25cd',
        'pfa23cd',
        'ruc21ind',
        'oac11ind',
        'imd20ind',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'east1m' => 'integer',
        'north1m' => 'integer',
        'imd20ind' => 'integer',
    ];

    /**
     * Check if this postcode is within a given boundary geometry.
     */
    public function isWithinBoundary(string $boundaryType, string $gssCode): bool
    {
        return DB::selectOne("
            SELECT ST_Contains(bg.geom, p.geom) as is_within
            FROM postcodes p
            JOIN boundary_geometries bg ON bg.boundary_type = ? AND bg.gss_code = ?
            WHERE p.pcd7 = ?
        ", [$boundaryType, $gssCode, $this->pcd7])?->is_within ?? false;
    }

    /**
     * Get all postcodes within a boundary using point-in-polygon.
     */
    public static function withinBoundary(string $boundaryType, string $gssCode)
    {
        return static::whereRaw("
            ST_Contains(
                (SELECT geom FROM boundary_geometries WHERE boundary_type = ? AND gss_code = ?),
                geom
            )
        ", [$boundaryType, $gssCode]);
    }

    /**
     * Scope to only include live (non-terminated) postcodes.
     */
    public function scopeLive($query)
    {
        return $query->whereNull('doterm');
    }

    /**
     * Scope to only include postcodes with valid coordinates.
     */
    public function scopeWithCoordinates($query)
    {
        return $query->whereNotNull('lat')
                     ->whereNotNull('lng')
                     ->where('lat', '!=', 0)
                     ->where('lng', '!=', 0);
    }
}

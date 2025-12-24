<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Property Model
 *
 * Represents UK properties from the ONS UPRN Directory (ONSUD).
 * Stores 41 million property records with geography codes and coordinates.
 */
class Property extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'properties';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uprn';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uprn',
        'pcds',
        'gridgb1e',
        'gridgb1n',
        'lat',
        'lng',
        'wd25cd',
        'ced25cd',
        'parncp25cd',
        'lad25cd',
        'pcon24cd',
        'lsoa21cd',
        'msoa21cd',
        'rgn25cd',
        'ruc21ind',
        'pfa23cd',
    ];

    /**
     * Get the ward that owns the property.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class, 'wd25cd', 'wd25cd');
    }

    /**
     * Get the county electoral division that owns the property.
     */
    public function countyElectoralDivision()
    {
        return $this->belongsTo(CountyElectoralDivision::class, 'ced25cd', 'ced25cd');
    }

    /**
     * Get the parish that owns the property.
     */
    public function parish()
    {
        return $this->belongsTo(Parish::class, 'parncp25cd', 'parncp25cd');
    }

    /**
     * Get the local authority district that owns the property.
     */
    public function localAuthorityDistrict()
    {
        return $this->belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the constituency that owns the property.
     */
    public function constituency()
    {
        return $this->belongsTo(Constituency::class, 'pcon24cd', 'pcon24cd');
    }

    /**
     * Get the region that owns the property.
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'rgn25cd', 'rgn25cd');
    }

    /**
     * Get the police force area that owns the property.
     */
    public function policeForceArea()
    {
        return $this->belongsTo(PoliceForceArea::class, 'pfa23cd', 'pfa23cd');
    }
}

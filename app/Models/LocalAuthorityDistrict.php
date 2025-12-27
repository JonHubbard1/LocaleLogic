<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local Authority District Model
 *
 * Represents UK local authority districts with optional Welsh language names.
 * Stores approximately 350 LAD records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'lad25cd' is retained for backward compatibility and property joins.
 */
class LocalAuthorityDistrict extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'local_authority_districts';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'gss_code';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'gss_code',
        'year_code',
        'lad25cd',
        'lad25nm',
        'lad25nmw',
        'rgn25cd',
    ];

    /**
     * Find a local authority district by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the region that owns the LAD.
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'rgn25cd', 'rgn25cd');
    }

    /**
     * Get the wards in this LAD.
     */
    public function wards()
    {
        return $this->hasMany(Ward::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the parishes in this LAD.
     */
    public function parishes()
    {
        return $this->hasMany(Parish::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the properties in this LAD.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'lad25cd', 'lad25cd');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ward Model
 *
 * Represents UK electoral wards.
 * Stores approximately 9,000 ward records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'wd25cd' is retained for backward compatibility and property joins.
 */
class Ward extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wards';

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
        'wd25cd',
        'wd25nm',
        'lad25cd',
    ];

    /**
     * Find a ward by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the local authority district that owns the ward.
     */
    public function localAuthorityDistrict()
    {
        return $this->belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the properties in this ward.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'wd25cd', 'wd25cd');
    }
}

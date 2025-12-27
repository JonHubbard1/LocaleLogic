<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Region Model
 *
 * Represents UK geographical regions (England, Wales, Scotland).
 * Stores approximately 12 region records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'rgn25cd' is retained for backward compatibility and joins.
 */
class Region extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'regions';

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
        'rgn25cd',
        'rgn25nm',
    ];

    /**
     * Find a region by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the local authority districts in this region.
     */
    public function localAuthorityDistricts()
    {
        return $this->hasMany(LocalAuthorityDistrict::class, 'rgn25cd', 'rgn25cd');
    }

    /**
     * Get the properties in this region.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'rgn25cd', 'rgn25cd');
    }
}

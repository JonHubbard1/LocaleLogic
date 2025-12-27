<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parish Model
 *
 * Represents UK parishes with optional Welsh language names.
 * Stores approximately 11,000 parish records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'parncp25cd' is retained for backward compatibility and property joins.
 */
class Parish extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'parishes';

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
        'parncp25cd',
        'parncp25nm',
        'parncp25nmw',
        'lad25cd',
    ];

    /**
     * Find a parish by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the local authority district that owns the parish.
     */
    public function localAuthorityDistrict()
    {
        return $this->belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the properties in this parish.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'parncp25cd', 'parncp25cd');
    }
}

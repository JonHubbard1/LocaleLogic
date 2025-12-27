<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Constituency Model
 *
 * Represents UK Westminster parliamentary constituencies.
 * Stores approximately 650 Westminster constituency records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'pcon24cd' is retained for backward compatibility and property joins.
 */
class Constituency extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'constituencies';

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
        'pcon24cd',
        'pcon24nm',
    ];

    /**
     * Find a constituency by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the properties in this constituency.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'pcon24cd', 'pcon24cd');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * County Model
 *
 * Represents UK counties.
 * Stores approximately 30 county records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'cty25cd' is retained for backward compatibility and joins.
 */
class County extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'counties';

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
        'cty25cd',
        'cty25nm',
    ];

    /**
     * Find a county by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the county electoral divisions in this county.
     */
    public function countyElectoralDivisions()
    {
        return $this->hasMany(CountyElectoralDivision::class, 'cty25cd', 'cty25cd');
    }
}

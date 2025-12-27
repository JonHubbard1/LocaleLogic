<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * County Electoral Division Model
 *
 * Represents UK county electoral divisions.
 * Stores approximately 1,400 CED records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'ced25cd' is retained for backward compatibility and property joins.
 */
class CountyElectoralDivision extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'county_electoral_divisions';

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
        'ced25cd',
        'ced25nm',
        'cty25cd',
    ];

    /**
     * Find a county electoral division by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the county that owns the CED.
     */
    public function county()
    {
        return $this->belongsTo(County::class, 'cty25cd', 'cty25cd');
    }

    /**
     * Get the properties in this CED.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'ced25cd', 'ced25cd');
    }
}

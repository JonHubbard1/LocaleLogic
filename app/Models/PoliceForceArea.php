<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Police Force Area Model
 *
 * Represents UK police force areas.
 * Stores 44 police force area records from ONS lookup data.
 *
 * Primary key is now 'gss_code' (year-agnostic) for consistent identification.
 * 'pfa23cd' is retained for backward compatibility and property joins.
 */
class PoliceForceArea extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'police_force_areas';

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
        'pfa23cd',
        'pfa23nm',
    ];

    /**
     * Find a police force area by GSS code.
     *
     * @param string $code GSS code to search for
     * @return static|null
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Get the properties in this police force area.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'pfa23cd', 'pfa23cd');
    }
}

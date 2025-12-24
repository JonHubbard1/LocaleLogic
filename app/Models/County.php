<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * County Model
 *
 * Represents UK counties.
 * Stores approximately 30 county records from ONS lookup data.
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
    protected $primaryKey = 'cty25cd';

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
        'cty25cd',
        'cty25nm',
    ];

    /**
     * Get the county electoral divisions in this county.
     */
    public function countyElectoralDivisions()
    {
        return $this->hasMany(CountyElectoralDivision::class, 'cty25cd', 'cty25cd');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Region Model
 *
 * Represents UK geographical regions (England, Wales, Scotland).
 * Stores approximately 12 region records from ONS lookup data.
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
    protected $primaryKey = 'rgn25cd';

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
        'rgn25cd',
        'rgn25nm',
    ];

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

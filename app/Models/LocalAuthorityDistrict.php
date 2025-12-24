<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local Authority District Model
 *
 * Represents UK local authority districts with optional Welsh language names.
 * Stores approximately 350 LAD records from ONS lookup data.
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
    protected $primaryKey = 'lad25cd';

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
        'lad25cd',
        'lad25nm',
        'lad25nmw',
        'rgn25cd',
    ];

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

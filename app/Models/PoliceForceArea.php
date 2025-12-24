<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Police Force Area Model
 *
 * Represents UK police force areas.
 * Stores 44 police force area records from ONS lookup data.
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
    protected $primaryKey = 'pfa23cd';

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
        'pfa23cd',
        'pfa23nm',
    ];

    /**
     * Get the properties in this police force area.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'pfa23cd', 'pfa23cd');
    }
}

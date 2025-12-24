<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ward Model
 *
 * Represents UK electoral wards.
 * Stores approximately 9,000 ward records from ONS lookup data.
 */
class Ward extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wards';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'wd25cd';

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
        'wd25cd',
        'wd25nm',
        'lad25cd',
    ];

    /**
     * Get the local authority district that owns the ward.
     */
    public function localAuthorityDistrict()
    {
        return $this->belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd');
    }

    /**
     * Get the properties in this ward.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'wd25cd', 'wd25cd');
    }
}

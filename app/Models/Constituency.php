<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Constituency Model
 *
 * Represents UK Westminster parliamentary constituencies.
 * Stores approximately 650 Westminster constituency records from ONS lookup data.
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
    protected $primaryKey = 'pcon24cd';

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
        'pcon24cd',
        'pcon24nm',
    ];

    /**
     * Get the properties in this constituency.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'pcon24cd', 'pcon24cd');
    }
}

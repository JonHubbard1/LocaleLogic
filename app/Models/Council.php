<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Council extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     */
    protected $table = 'councils';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'gss_code';

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'gss_code',
        'name',
        'name_welsh',
        'council_type',
        'nation',
        'region',
        'uses_modern_gov',
        'uses_democracy_club',
        'democracy_club_org_id',
        'modern_gov_base_url',
        'democracy_url',
        'website_url',
        'source',
        'scraped_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uses_modern_gov' => 'boolean',
            'uses_democracy_club' => 'boolean',
            'scraped_at' => 'datetime',
        ];
    }

    /**
     * Get the councillors for this council.
     */
    public function councillors(): HasMany
    {
        return $this->hasMany(Councillor::class, 'council_gss_code', 'gss_code');
    }

    /**
     * Get the wards for this council via the lookup table.
     */
    public function wards()
    {
        return WardHierarchyLookup::where('lad_code', $this->gss_code)
            ->select('wd_code', 'wd_name')
            ->distinct()
            ->get();
    }

    /**
     * Find a council by GSS code.
     */
    public static function findByGssCode(string $code): ?static
    {
        return static::where('gss_code', $code)->first();
    }

    /**
     * Infer council type from GSS code prefix.
     */
    public static function inferTypeFromGssCode(string $gssCode): string
    {
        return match (substr($gssCode, 0, 3)) {
            'E06' => 'unitary',
            'E07' => 'district',
            'E08' => 'metropolitan',
            'E09' => 'london_borough',
            'E10' => 'county',
            'S12' => 'scottish',
            'W06' => 'welsh',
            'N09' => 'ni',
            default => 'unknown',
        };
    }

    /**
     * Infer nation from GSS code prefix.
     */
    public static function inferNationFromGssCode(string $gssCode): string
    {
        return match (substr($gssCode, 0, 1)) {
            'E' => 'england',
            'S' => 'scotland',
            'W' => 'wales',
            'N' => 'northern_ireland',
            default => 'unknown',
        };
    }
}

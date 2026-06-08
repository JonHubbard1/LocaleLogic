<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Councillor extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     */
    protected $table = 'councillors';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'council_gss_code',
        'ward_gss_code',
        'name',
        'party',
        'email',
        'phone',
        'photo_url',
        'profile_url',
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
            'scraped_at' => 'datetime',
        ];
    }

    /**
     * Get the council that owns the councillor.
     */
    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class, 'council_gss_code', 'gss_code');
    }

    /**
     * Get the ward name from the hierarchy lookup table.
     */
    public function wardName(): ?string
    {
        $lookup = WardHierarchyLookup::where('wd_code', $this->ward_gss_code)
            ->first();

        return $lookup?->wd_name;
    }
}

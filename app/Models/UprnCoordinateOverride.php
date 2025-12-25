<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UprnCoordinateOverride extends Model
{
    protected $fillable = [
        'user_id',
        'uprn',
        'override_lat',
        'override_lng',
    ];

    protected function casts(): array
    {
        return [
            'override_lat' => 'decimal:8',
            'override_lng' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

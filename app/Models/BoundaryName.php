<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundaryName extends Model
{
    protected $fillable = [
        'boundary_type',
        'gss_code',
        'name',
        'name_welsh',
        'source',
        'version_date',
    ];

    protected function casts(): array
    {
        return [
            'version_date' => 'date',
        ];
    }

    /**
     * Get the boundary geometry associated with this name.
     */
    public function geometry()
    {
        return $this->hasOne(BoundaryGeometry::class, 'gss_code', 'gss_code')
            ->where('boundary_type', $this->boundary_type);
    }

    /**
     * Get import records for this boundary type.
     */
    public function imports()
    {
        return $this->hasMany(BoundaryImport::class, 'boundary_type', 'boundary_type')
            ->where('data_type', 'names');
    }

    /**
     * Scope to filter by boundary type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('boundary_type', $type);
    }

    /**
     * Scope to filter by GSS code.
     */
    public function scopeByGssCode($query, string $code)
    {
        return $query->where('gss_code', $code);
    }
}

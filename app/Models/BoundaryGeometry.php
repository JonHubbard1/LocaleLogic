<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundaryGeometry extends Model
{
    protected $fillable = [
        'boundary_type',
        'gss_code',
        'name',
        'geometry',
        'properties',
        'area_hectares',
        'bounding_box',
        'source_file',
        'version_date',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'properties' => 'array',
            'area_hectares' => 'decimal:2',
            'version_date' => 'date',
        ];
    }

    /**
     * Get the boundary name associated with this geometry.
     */
    public function boundaryName()
    {
        return $this->hasOne(BoundaryName::class, 'gss_code', 'gss_code')
            ->where('boundary_type', $this->boundary_type);
    }

    /**
     * Get import records for this boundary type.
     */
    public function imports()
    {
        return $this->hasMany(BoundaryImport::class, 'boundary_type', 'boundary_type')
            ->where('data_type', 'polygons');
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

    /**
     * Get the bounding box as an array.
     */
    public function getBoundingBoxArray(): ?array
    {
        if (!$this->bounding_box) {
            return null;
        }

        // Expected format: "minLat,minLng,maxLat,maxLng"
        $parts = explode(',', $this->bounding_box);

        if (count($parts) !== 4) {
            return null;
        }

        return [
            'min_lat' => (float) $parts[0],
            'min_lng' => (float) $parts[1],
            'max_lat' => (float) $parts[2],
            'max_lng' => (float) $parts[3],
        ];
    }

    /**
     * Set the bounding box from an array.
     */
    public function setBoundingBoxFromArray(array $bbox): void
    {
        // Expected array keys: min_lat, min_lng, max_lat, max_lng
        $this->bounding_box = implode(',', [
            $bbox['min_lat'],
            $bbox['min_lng'],
            $bbox['max_lat'],
            $bbox['max_lng'],
        ]);
    }
}

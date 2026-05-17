<?php

namespace App\Livewire\Tools;

use App\Models\BoundaryGeometry;
use App\Models\BoundaryName;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Boundary Viewer')]
class BoundaryViewer extends Component
{
    public string $boundaryType = '';
    public string $searchQuery = '';
    public string $gssCode = '';

    public array $searchResults = [];
    public ?BoundaryGeometry $boundary = null;
    public ?string $error = null;

    public bool $showPostcodeCentres = false;
    public bool $showPropertyEndpoints = false;

    public array $postcodeCentres = [];
    public array $propertyEndpoints = [];

    public array $typeOptions = [
        'wards' => 'Wards',
        'parishes' => 'Parishes',
        'lad' => 'Local Authority Districts',
        'ced' => 'County Electoral Divisions',
        'constituencies' => 'Parliamentary Constituencies',
        'police_force_areas' => 'Police Force Areas',
        'region' => 'Regions',
    ];

    /**
     * Map boundary_type values to the corresponding column in the properties table.
     */
    private array $boundaryTypeToColumn = [
        'wards' => 'wd25cd',
        'parishes' => 'parncp25cd',
        'lad' => 'lad25cd',
        'ced' => 'ced25cd',
        'constituencies' => 'pcon24cd',
        'police_force_areas' => 'pfa23cd',
        'region' => 'rgn25cd',
    ];

    public function updatedBoundaryType(): void
    {
        $this->searchResults = [];
        $this->searchQuery = '';
        $this->gssCode = '';
        $this->postcodeCentres = [];
        $this->propertyEndpoints = [];
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = [];
        $this->gssCode = '';
        $this->error = null;

        if (strlen($this->searchQuery) < 2 || empty($this->boundaryType)) {
            return;
        }

        $this->searchResults = BoundaryName::where('boundary_type', $this->boundaryType)
            ->where('name', 'ilike', '%' . $this->searchQuery . '%')
            ->limit(20)
            ->get(['gss_code', 'name', 'name_welsh'])
            ->toArray();
    }

    public function updatedShowPostcodeCentres(): void
    {
        if ($this->boundary) {
            $this->loadPropertyData();
        }
    }

    public function updatedShowPropertyEndpoints(): void
    {
        if ($this->boundary) {
            $this->loadPropertyData();
        }
    }

    public function selectBoundary(string $gssCode, string $name): void
    {
        $this->gssCode = $gssCode;
        $this->searchQuery = $name;
        $this->searchResults = [];
        $this->loadBoundary();
    }

    public function loadBoundary(): void
    {
        $this->validate([
            'boundaryType' => 'required|string',
            'gssCode' => 'required|string|min:9|max:9',
        ]);

        $this->error = null;
        $this->boundary = null;
        $this->postcodeCentres = [];
        $this->propertyEndpoints = [];

        $boundary = BoundaryGeometry::where('boundary_type', $this->boundaryType)
            ->where('gss_code', $this->gssCode)
            ->first();

        if (! $boundary) {
            $this->error = "Boundary not found for type '{$this->typeOptions[$this->boundaryType]}' and code '{$this->gssCode}'";

            return;
        }

        $this->boundary = $boundary;

        // Look up the name if not already set
        $nameRecord = BoundaryName::where('boundary_type', $this->boundaryType)
            ->where('gss_code', $this->gssCode)
            ->first();

        if ($nameRecord) {
            $this->searchQuery = $nameRecord->name;
        }

        // Build GeoJSON Feature object for Leaflet
        $geoJson = [
            'type' => 'Feature',
            'geometry' => $boundary->geometry,
            'properties' => array_merge($boundary->properties ?? [], [
                'name' => $boundary->name,
                'gss_code' => $boundary->gss_code,
                'boundary_type' => $boundary->boundary_type,
                'area_hectares' => $boundary->area_hectares,
            ]),
        ];

        $bbox = $boundary->getBoundingBoxArray();

        $this->dispatch('loadBoundaryGeoJson',
            geoJson: $geoJson,
            boundingBox: $bbox,
            name: $boundary->name,
            gssCode: $boundary->gss_code,
            areaHectares: $boundary->area_hectares,
        );

        $this->loadPropertyData();
    }

    /**
     * Determine whether the current boundary type supports property/postcode lookups.
     */
    public function supportsPropertyData(): bool
    {
        return array_key_exists($this->boundaryType, $this->boundaryTypeToColumn);
    }

    /**
     * Query postcode centres and/or property endpoints for the current boundary.
     * When the boundary polygon has a PostGIS geom, we use pure spatial containment
     * (ST_Intersects) against every property's lat/lng point. This catches
     * properties that ONSUD assigned to the wrong ward but that physically fall
     * inside the selected area.
     */
    private function loadPropertyData(): void
    {
        if (! $this->boundary) {
            return;
        }

        $gssCode = $this->boundary->gss_code;

        // Check whether the boundary has a PostGIS geom column populated
        $polygonWkt = DB::table('boundary_geometries')
            ->where('boundary_type', $this->boundaryType)
            ->where('gss_code', $gssCode)
            ->whereNotNull('geom')
            ->value(DB::raw('ST_AsText(geom)'));

        if ($polygonWkt) {
            $this->runSpatialPropertyQueries($polygonWkt);
            return;
        }

        // Fallback: no PostGIS geometry available — use the code column
        $column = $this->boundaryTypeToColumn[$this->boundaryType] ?? null;
        if (! $column) {
            return;
        }

        if ($this->showPostcodeCentres) {
            $this->postcodeCentres = DB::table('properties')
                ->select('pcds', DB::raw('AVG(lat) as lat'), DB::raw('AVG(lng) as lng'), DB::raw('COUNT(*) as count'))
                ->where($column, $gssCode)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->groupBy('pcds')
                ->orderByDesc('count')
                ->limit(10000)
                ->get()
                ->map(fn ($r) => [
                    'pcds' => $r->pcds,
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                    'count' => (int) $r->count,
                ])
                ->toArray();

            $this->dispatch('loadBoundaryCentroids', centroids: $this->postcodeCentres);
        } else {
            $this->dispatch('clearBoundaryCentroids');
        }

        if ($this->showPropertyEndpoints) {
            $this->propertyEndpoints = DB::table('properties')
                ->select('uprn', 'pcds', 'lat', 'lng')
                ->where($column, $gssCode)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderBy('uprn')
                ->limit(50000)
                ->get()
                ->map(fn ($r) => [
                    'uprn' => $r->uprn,
                    'pcds' => $r->pcds,
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                ])
                ->toArray();

            $this->dispatch('loadBoundaryEndpoints', endpoints: $this->propertyEndpoints);
        } else {
            $this->dispatch('clearBoundaryEndpoints');
        }
    }

    /**
     * Run postcode-centre and endpoint queries using pure PostGIS spatial
     * containment against the given polygon WKT.
     */
    private function runSpatialPropertyQueries(string $polygonWkt): void
    {
        if ($this->showPostcodeCentres) {
            $this->postcodeCentres = DB::table('properties')
                ->select('pcds', DB::raw('AVG(lat) as lat'), DB::raw('AVG(lng) as lng'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereRaw(
                    'ST_Intersects(ST_SetSRID(ST_MakePoint(lng, lat), 4326), ST_GeomFromText(?, 4326))',
                    [$polygonWkt]
                )
                ->groupBy('pcds')
                ->orderByDesc('count')
                ->limit(10000)
                ->get()
                ->map(fn ($r) => [
                    'pcds' => $r->pcds,
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                    'count' => (int) $r->count,
                ])
                ->toArray();

            $this->dispatch('loadBoundaryCentroids', centroids: $this->postcodeCentres);
        } else {
            $this->dispatch('clearBoundaryCentroids');
        }

        if ($this->showPropertyEndpoints) {
            $this->propertyEndpoints = DB::table('properties')
                ->select('uprn', 'pcds', 'lat', 'lng')
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereRaw(
                    'ST_Intersects(ST_SetSRID(ST_MakePoint(lng, lat), 4326), ST_GeomFromText(?, 4326))',
                    [$polygonWkt]
                )
                ->orderBy('uprn')
                ->limit(50000)
                ->get()
                ->map(fn ($r) => [
                    'uprn' => $r->uprn,
                    'pcds' => $r->pcds,
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                ])
                ->toArray();

            $this->dispatch('loadBoundaryEndpoints', endpoints: $this->propertyEndpoints);
        } else {
            $this->dispatch('clearBoundaryEndpoints');
        }
    }

    public function clear(): void
    {
        $this->reset(['boundaryType', 'searchQuery', 'gssCode', 'searchResults', 'boundary', 'error', 'showPostcodeCentres', 'showPropertyEndpoints', 'postcodeCentres', 'propertyEndpoints']);
        $this->dispatch('clearBoundaryMap');
    }

    public function render()
    {
        return view('livewire.tools.boundary-viewer');
    }
}

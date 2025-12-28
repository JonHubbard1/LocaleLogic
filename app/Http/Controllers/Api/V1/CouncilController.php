<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BoundaryName;
use App\Models\CountyElectoralDivision;
use App\Models\LocalAuthorityDistrict;
use App\Models\Parish;
use App\Models\Property;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;

class CouncilController extends Controller
{
    /**
     * Get list of all councils (county, unitary, or district)
     * Optional query parameter: ?type=county|unitary|district
     */
    public function index(): JsonResponse
    {
        $type = request()->query('type');

        // Build the base query for councils
        $query = BoundaryName::query()
            ->select('gss_code', 'name', 'name_welsh')
            ->where(function($q) {
                $q->where('gss_code', 'like', 'E06%')  // Unitary authorities
                  ->orWhere('gss_code', 'like', 'E07%')  // Districts
                  ->orWhere('gss_code', 'like', 'E09%')  // London boroughs
                  ->orWhere('gss_code', 'like', 'E10%')  // Counties
                  ->orWhere('gss_code', 'like', 'W06%')  // Welsh unitary
                  ->orWhere('gss_code', 'like', 'S12%'); // Scottish unitary
            })
            ->groupBy('gss_code', 'name', 'name_welsh')  // Remove duplicates
            ->orderBy('name');

        // Filter by council type if specified
        if ($type) {
            if ($type === 'unitary') {
                // Unitary authorities are E06% (unitary) and E09% (London boroughs)
                $query->where(function($q) {
                    $q->where('gss_code', 'like', 'E06%')
                      ->orWhere('gss_code', 'like', 'E09%');
                });
            } else {
                $query->where('gss_code', 'like', $this->getGssCodePattern($type));
            }
        }

        $councils = $query->get()->map(function ($council) {
            return [
                'gss_code' => $council->gss_code,
                'name' => $council->name,
                'name_welsh' => $council->name_welsh,
                'type' => $this->getCouncilType($council->gss_code),
            ];
        });

        return response()->json([
            'data' => $councils,
            'meta' => [
                'count' => $councils->count(),
                'type_filter' => $type ?? 'all',
            ],
        ]);
    }

    /**
     * Get all district councils within a county council area
     */
    public function districts(string $countyCode): JsonResponse
    {
        // Verify the county code exists and is actually a county
        $county = LocalAuthorityDistrict::where('gss_code', $countyCode)->first();

        if (!$county) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$countyCode}' not found",
                ],
            ], 404);
        }

        if (!str_starts_with($countyCode, 'E10')) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_COUNCIL_TYPE',
                    'message' => "Council '{$countyCode}' is not a county council",
                ],
            ], 422);
        }

        // Get all district councils (E07xxx codes)
        // Note: This returns ALL districts as there's no direct county->district relationship in the data
        // Users will need to filter based on their knowledge of geography or we'd need additional lookup data
        $districts = LocalAuthorityDistrict::query()
            ->where('gss_code', 'like', 'E07%')
            ->orderBy('lad25nm')
            ->get()
            ->map(function ($district) use ($countyCode) {
                return [
                    'gss_code' => $district->gss_code,
                    'name' => $district->lad25nm,
                    'name_welsh' => $district->lad25nmw,
                    'county_code' => $countyCode,
                ];
            });

        return response()->json([
            'data' => $districts,
            'meta' => [
                'county_code' => $countyCode,
                'county_name' => $county->lad25nm,
                'count' => $districts->count(),
                'note' => 'Returns all district councils. Filter based on your geographic knowledge as direct county->district relationships are not in the data.',
            ],
        ]);
    }

    /**
     * Get all electoral divisions in a county with postcodes
     */
    public function divisions(string $councilCode): JsonResponse
    {
        // Verify the council exists in boundary_names
        $council = BoundaryName::where('gss_code', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all CEDs (County Electoral Divisions) for this council from boundary_names
        // CEDs have GSS codes starting with E58
        $divisions = BoundaryName::where('gss_code', 'like', 'E58%')
            ->where(function($query) use ($councilCode) {
                // Filter CEDs that belong to this council area
                // We check if properties exist with both this council code and the CED code
                $query->whereExists(function($subquery) use ($councilCode) {
                    $subquery->select('uprn')
                        ->from('properties')
                        ->where('lad25cd', $councilCode)
                        ->whereColumn('ced25cd', 'boundary_names.gss_code');
                });
            })
            ->groupBy('gss_code', 'name', 'name_welsh')
            ->orderBy('name')
            ->get()
            ->map(function ($division) {
                // Get all unique postcodes in this division
                $postcodes = Property::where('ced25cd', $division->gss_code)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $division->gss_code,
                    'name' => $division->name,
                    'name_welsh' => $division->name_welsh,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $divisions,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->name,
                'division_count' => $divisions->count(),
            ],
        ]);
    }

    /**
     * Get all electoral wards in a unitary/district council with postcodes
     */
    public function wards(string $councilCode): JsonResponse
    {
        // Verify the council exists in boundary_names
        $council = BoundaryName::where('gss_code', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all wards for this council from boundary_names
        // Wards have GSS codes starting with E05
        $wards = BoundaryName::where('gss_code', 'like', 'E05%')
            ->where(function($query) use ($councilCode) {
                // Filter wards that belong to this council area
                $query->whereExists(function($subquery) use ($councilCode) {
                    $subquery->select('uprn')
                        ->from('properties')
                        ->where('lad25cd', $councilCode)
                        ->whereColumn('wd25cd', 'boundary_names.gss_code');
                });
            })
            ->groupBy('gss_code', 'name', 'name_welsh')
            ->orderBy('name')
            ->get()
            ->map(function ($ward) {
                // Get all unique postcodes in this ward
                $postcodes = Property::where('wd25cd', $ward->gss_code)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $ward->gss_code,
                    'name' => $ward->name,
                    'name_welsh' => $ward->name_welsh,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $wards,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->name,
                'council_type' => $this->getCouncilType($councilCode),
                'ward_count' => $wards->count(),
            ],
        ]);
    }

    /**
     * Get all parishes in a county/unitary/district area
     */
    public function parishes(string $councilCode): JsonResponse
    {
        // Verify the council exists in boundary_names
        $council = BoundaryName::where('gss_code', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all parishes for this council from boundary_names
        // Parishes have GSS codes starting with E04 or E43
        $parishes = BoundaryName::where(function($query) {
                $query->where('gss_code', 'like', 'E04%')
                      ->orWhere('gss_code', 'like', 'E43%');
            })
            ->where(function($query) use ($councilCode) {
                // Filter parishes that belong to this council area
                $query->whereExists(function($subquery) use ($councilCode) {
                    $subquery->select('uprn')
                        ->from('properties')
                        ->where('lad25cd', $councilCode)
                        ->whereColumn('parncp25cd', 'boundary_names.gss_code');
                });
            })
            ->groupBy('gss_code', 'name', 'name_welsh')
            ->orderBy('name')
            ->get()
            ->map(function ($parish) {
                // Get all unique postcodes in this parish
                $postcodes = Property::where('parncp25cd', $parish->gss_code)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $parish->gss_code,
                    'name' => $parish->name,
                    'name_welsh' => $parish->name_welsh,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $parishes,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->name,
                'council_type' => $this->getCouncilType($councilCode),
                'parish_count' => $parishes->count(),
            ],
        ]);
    }

    /**
     * Helper: Determine council type from GSS code
     */
    private function getCouncilType(string $gssCode): string
    {
        if (str_starts_with($gssCode, 'E10')) {
            return 'county';
        }

        if (str_starts_with($gssCode, 'E06') || str_starts_with($gssCode, 'E09')) {
            return 'unitary';
        }

        if (str_starts_with($gssCode, 'E07')) {
            return 'district';
        }

        return 'unknown';
    }

    /**
     * Helper: Get GSS code pattern for council type filter
     */
    private function getGssCodePattern(string $type): string
    {
        return match($type) {
            'county' => 'E10%',
            'unitary' => 'E0[69]%',
            'district' => 'E07%',
            default => '%',
        };
    }
}

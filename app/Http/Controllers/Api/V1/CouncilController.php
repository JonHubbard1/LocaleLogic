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
use Illuminate\Support\Facades\DB;

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

        // Get all CEDs (County Electoral Divisions) for this council from ward_hierarchy_lookups
        // This gives us the official ONS mapping of CEDs to counties
        $divisions = DB::table('ward_hierarchy_lookups')
            ->select('ced_code as gss_code', 'ced_name as name')
            ->where('cty_code', $councilCode)
            ->whereNotNull('ced_code')
            ->distinct()
            ->orderBy('ced_name')
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

        // Get all wards for this council from ward_hierarchy_lookups
        // This gives us the official ONS mapping of wards to councils
        $wards = DB::table('ward_hierarchy_lookups')
            ->select('wd_code as gss_code', 'wd_name as name')
            ->where('lad_code', $councilCode)
            ->distinct()
            ->orderBy('wd_name')
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

        // Get all parishes for this council from parish_lookups
        // This gives us the official ONS mapping of parishes to councils
        $parishes = DB::table('parish_lookups')
            ->select('par_code as gss_code', 'par_name as name', 'par_name_welsh as name_welsh')
            ->where('lad_code', $councilCode)
            ->distinct()
            ->orderBy('par_name')
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
     * Get postcodes for a specific ward
     */
    public function wardPostcodes(string $wardCode): JsonResponse
    {
        // Verify the ward exists
        $ward = DB::table('ward_hierarchy_lookups')
            ->select('wd_code', 'wd_name', 'lad_code', 'lad_name')
            ->where('wd_code', $wardCode)
            ->first();

        if (!$ward) {
            return response()->json([
                'error' => [
                    'code' => 'WARD_NOT_FOUND',
                    'message' => "Ward with code '{$wardCode}' not found",
                ],
            ], 404);
        }

        // Get all unique postcodes in this ward
        $postcodes = Property::where('wd25cd', $wardCode)
            ->distinct()
            ->pluck('pcds')
            ->sort()
            ->values();

        return response()->json([
            'data' => $postcodes,
            'meta' => [
                'ward_code' => $wardCode,
                'ward_name' => $ward->wd_name,
                'council_code' => $ward->lad_code,
                'council_name' => $ward->lad_name,
                'count' => $postcodes->count(),
            ],
        ]);
    }

    /**
     * Get postcodes for a specific division
     */
    public function divisionPostcodes(string $divisionCode): JsonResponse
    {
        // Verify the division exists
        $division = DB::table('ward_hierarchy_lookups')
            ->select('ced_code', 'ced_name', 'cty_code', 'cty_name')
            ->where('ced_code', $divisionCode)
            ->first();

        if (!$division) {
            return response()->json([
                'error' => [
                    'code' => 'DIVISION_NOT_FOUND',
                    'message' => "Division with code '{$divisionCode}' not found",
                ],
            ], 404);
        }

        // Get all unique postcodes in this division
        $postcodes = Property::where('ced25cd', $divisionCode)
            ->distinct()
            ->pluck('pcds')
            ->sort()
            ->values();

        return response()->json([
            'data' => $postcodes,
            'meta' => [
                'division_code' => $divisionCode,
                'division_name' => $division->ced_name,
                'county_code' => $division->cty_code,
                'county_name' => $division->cty_name,
                'count' => $postcodes->count(),
            ],
        ]);
    }

    /**
     * Get postcodes for a specific parish
     */
    public function parishPostcodes(string $parishCode): JsonResponse
    {
        // Verify the parish exists
        $parish = DB::table('parish_lookups')
            ->select('par_code', 'par_name', 'par_name_welsh', 'lad_code', 'lad_name')
            ->where('par_code', $parishCode)
            ->first();

        if (!$parish) {
            return response()->json([
                'error' => [
                    'code' => 'PARISH_NOT_FOUND',
                    'message' => "Parish with code '{$parishCode}' not found",
                ],
            ], 404);
        }

        // Get all unique postcodes in this parish
        $postcodes = Property::where('parncp25cd', $parishCode)
            ->distinct()
            ->pluck('pcds')
            ->sort()
            ->values();

        return response()->json([
            'data' => $postcodes,
            'meta' => [
                'parish_code' => $parishCode,
                'parish_name' => $parish->par_name,
                'parish_name_welsh' => $parish->par_name_welsh,
                'council_code' => $parish->lad_code,
                'council_name' => $parish->lad_name,
                'count' => $postcodes->count(),
            ],
        ]);
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

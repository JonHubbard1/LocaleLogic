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

        // Get all unique LAD codes from properties with their names from boundary_names
        $query = BoundaryName::query()
            ->select('gss_code', 'name', 'name_welsh')
            ->whereIn('gss_code', function($subquery) {
                $subquery->select('lad25cd')
                    ->from('properties')
                    ->whereNotNull('lad25cd')
                    ->distinct();
            })
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
        $county = LocalAuthorityDistrict::where('lad25cd', $countyCode)->first();

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
            ->where('lad25cd', 'like', 'E07%')
            ->orderBy('lad25nm')
            ->get()
            ->map(function ($district) use ($countyCode) {
                return [
                    'gss_code' => $district->lad25cd,
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
        // Verify the council exists
        $council = LocalAuthorityDistrict::where('lad25cd', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all CEDs where properties exist with this LAD code
        // We find CEDs by looking at properties that have both this LAD and a CED code
        $cedCodes = Property::where('lad25cd', $councilCode)
            ->whereNotNull('ced25cd')
            ->distinct()
            ->pluck('ced25cd');

        $divisions = CountyElectoralDivision::whereIn('ced25cd', $cedCodes)
            ->orderBy('ced25nm')
            ->get()
            ->map(function ($division) {
                // Get all unique postcodes in this division
                $postcodes = Property::where('ced25cd', $division->ced25cd)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $division->ced25cd,
                    'name' => $division->ced25nm,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $divisions,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->lad25nm,
                'division_count' => $divisions->count(),
            ],
        ]);
    }

    /**
     * Get all electoral wards in a unitary/district council with postcodes
     */
    public function wards(string $councilCode): JsonResponse
    {
        // Verify the council exists
        $council = LocalAuthorityDistrict::where('lad25cd', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all wards in this council with their postcodes
        $wards = Ward::where('lad25cd', $councilCode)
            ->orderBy('wd25nm')
            ->get()
            ->map(function ($ward) {
                // Get all unique postcodes in this ward
                $postcodes = Property::where('wd25cd', $ward->wd25cd)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $ward->wd25cd,
                    'name' => $ward->wd25nm,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $wards,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->lad25nm,
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
        // Verify the council exists
        $council = LocalAuthorityDistrict::where('lad25cd', $councilCode)->first();

        if (!$council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$councilCode}' not found",
                ],
            ], 404);
        }

        // Get all parishes in this council area with their postcodes
        $parishes = Parish::where('lad25cd', $councilCode)
            ->orderBy('parncp25nm')
            ->get()
            ->map(function ($parish) {
                // Get all unique postcodes in this parish
                $postcodes = Property::where('parncp25cd', $parish->parncp25cd)
                    ->distinct()
                    ->pluck('pcds')
                    ->sort()
                    ->values();

                return [
                    'gss_code' => $parish->parncp25cd,
                    'name' => $parish->parncp25nm,
                    'name_welsh' => $parish->parncp25nmw,
                    'postcode_count' => $postcodes->count(),
                    'postcodes' => $postcodes,
                ];
            });

        return response()->json([
            'data' => $parishes,
            'meta' => [
                'council_code' => $councilCode,
                'council_name' => $council->lad25nm,
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

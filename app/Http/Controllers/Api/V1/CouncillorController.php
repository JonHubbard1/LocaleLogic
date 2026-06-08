<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Council;
use App\Models\Councillor;
use App\Models\WardHierarchyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouncillorController extends Controller
{
    /**
     * Get list of all councils with metadata.
     */
    public function councils(Request $request): JsonResponse
    {
        $query = Council::query()
            ->orderBy('name');

        if ($request->has('nation')) {
            $query->where('nation', $request->input('nation'));
        }

        if ($request->has('type')) {
            $query->where('council_type', $request->input('type'));
        }

        if ($request->has('modern_gov')) {
            $query->where('uses_modern_gov', $request->boolean('modern_gov'));
        }

        $councils = $query->get()
            ->map(function (Council $council) {
                return [
                    'gss_code' => $council->gss_code,
                    'name' => $council->name,
                    'name_welsh' => $council->name_welsh,
                    'type' => $council->council_type,
                    'nation' => $council->nation,
                    'region' => $council->region,
                    'uses_modern_gov' => $council->uses_modern_gov,
                    'modern_gov_base_url' => $council->modern_gov_base_url,
                    'democracy_url' => $council->democracy_url,
                    'website_url' => $council->website_url,
                ];
            });

        return response()->json([
            'data' => $councils,
            'meta' => [
                'count' => $councils->count(),
            ],
        ]);
    }

    /**
     * Get a single council by GSS code.
     */
    public function showCouncil(string $gssCode): JsonResponse
    {
        $council = Council::findByGssCode($gssCode);

        if (! $council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$gssCode}' not found",
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'gss_code' => $council->gss_code,
                'name' => $council->name,
                'name_welsh' => $council->name_welsh,
                'type' => $council->council_type,
                'nation' => $council->nation,
                'region' => $council->region,
                'uses_modern_gov' => $council->uses_modern_gov,
                'modern_gov_base_url' => $council->modern_gov_base_url,
                'democracy_url' => $council->democracy_url,
                'website_url' => $council->website_url,
                'councillor_count' => $council->councillors()->count(),
            ],
        ]);
    }

    /**
     * Get councillors for a specific council.
     */
    public function councillorsByCouncil(string $gssCode): JsonResponse
    {
        $council = Council::findByGssCode($gssCode);

        if (! $council) {
            return response()->json([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                    'message' => "Council with code '{$gssCode}' not found",
                ],
            ], 404);
        }

        $councillors = $council->councillors()
            ->get()
            ->map(function (Councillor $councillor) {
                return [
                    'name' => $councillor->name,
                    'party' => $councillor->party,
                    'ward' => [
                        'gss_code' => $councillor->ward_gss_code,
                        'name' => $councillor->wardName(),
                    ],
                    'email' => $councillor->email,
                    'phone' => $councillor->phone,
                    'photo_url' => $councillor->photo_url,
                    'profile_url' => $councillor->profile_url,
                    'source' => $councillor->source,
                ];
            });

        return response()->json([
            'data' => $councillors,
            'meta' => [
                'council_code' => $gssCode,
                'council_name' => $council->name,
                'count' => $councillors->count(),
            ],
        ]);
    }

    /**
     * Get councillors for a specific ward.
     */
    public function councillorsByWard(string $wardCode): JsonResponse
    {
        $ward = WardHierarchyLookup::where('wd_code', $wardCode)->first();

        if (! $ward) {
            return response()->json([
                'error' => [
                    'code' => 'WARD_NOT_FOUND',
                    'message' => "Ward with code '{$wardCode}' not found",
                ],
            ], 404);
        }

        $councillors = Councillor::where('ward_gss_code', $wardCode)
            ->get()
            ->map(function (Councillor $councillor) {
                return [
                    'name' => $councillor->name,
                    'party' => $councillor->party,
                    'email' => $councillor->email,
                    'phone' => $councillor->phone,
                    'photo_url' => $councillor->photo_url,
                    'profile_url' => $councillor->profile_url,
                    'source' => $councillor->source,
                    'council' => [
                        'gss_code' => $councillor->council_gss_code,
                        'name' => $councillor->council?->name,
                    ],
                ];
            });

        return response()->json([
            'data' => $councillors,
            'meta' => [
                'ward_code' => $wardCode,
                'ward_name' => $ward->wd_name,
                'council_code' => $ward->lad_code,
                'council_name' => $ward->lad_name,
                'count' => $councillors->count(),
            ],
        ]);
    }
}

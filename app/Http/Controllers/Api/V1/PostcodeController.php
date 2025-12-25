<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PostcodeNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PostcodeLookupRequest;
use App\Http\Resources\Api\V1\PostcodeResource;
use App\Services\PostcodeLookupService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Postcode Lookup API Controller
 *
 * Handles API requests for postcode lookups with geography data.
 */
class PostcodeController extends Controller
{
    private PostcodeLookupService $postcodeService;

    public function __construct(PostcodeLookupService $postcodeService)
    {
        $this->postcodeService = $postcodeService;
    }

    /**
     * Lookup a postcode and return geography data
     *
     * @param PostcodeLookupRequest $request Validated request
     * @param string $postcode Postcode from URL
     * @return PostcodeResource|JsonResponse
     */
    public function show(PostcodeLookupRequest $request, string $postcode): PostcodeResource|JsonResponse
    {
        try {
            // Check if UPRNs should be included
            $includeUprns = $request->query('include') === 'uprns';

            // Perform lookup
            $data = $this->postcodeService->lookup($postcode, $includeUprns);

            // Add user's coordinate offset to response
            $data['coordinate_offset'] = [
                'latitude' => (float) $request->user()->coordinate_offset_lat,
                'longitude' => (float) $request->user()->coordinate_offset_lng,
            ];

            // Return formatted response
            return new PostcodeResource($data);

        } catch (InvalidArgumentException $e) {
            // Invalid postcode format (422)
            return response()->json([
                'error' => [
                    'code' => 'INVALID_POSTCODE',
                    'message' => $e->getMessage(),
                ],
            ], 422);

        } catch (PostcodeNotFoundException $e) {
            // Postcode not found (404) - exception handles its own render()
            return $e->render();

        } catch (\Exception $e) {
            // Unexpected error (500)
            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred',
                ],
            ], 500);
        }
    }
}

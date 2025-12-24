<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Postcode API Resource
 *
 * Transforms postcode lookup data into consistent API response format.
 */
class PostcodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'postcode' => $this->resource['postcode'],
                'coordinates' => $this->resource['coordinates'],
                'geography' => $this->resource['geography'],
                'property_count' => $this->resource['property_count'],
                'uprns' => $this->when(
                    $this->resource['uprns'] !== null,
                    $this->resource['uprns']
                ),
            ],
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}

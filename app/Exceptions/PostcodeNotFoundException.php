<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when a postcode is not found in the database
 */
class PostcodeNotFoundException extends Exception
{
    protected $code = 404;
    protected $message = 'Postcode not found';
    private string $postcode;

    public function __construct(string $postcode)
    {
        parent::__construct("No properties found for postcode {$postcode}");
        $this->postcode = $postcode;
    }

    /**
     * Render the exception as an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'POSTCODE_NOT_FOUND',
                'message' => $this->getMessage(),
            ],
        ], 404);
    }
}

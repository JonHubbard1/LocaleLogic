<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Postcode Lookup Request Validation
 *
 * Validates query parameters for postcode lookup endpoint.
 */
class PostcodeLookupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by Sanctum middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'include' => 'sometimes|string|in:uprns',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'include.in' => 'The include parameter only accepts: uprns',
        ];
    }

    /**
     * Handle a failed validation attempt (return JSON for API)
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors(),
                ],
            ], 422)
        );
    }
}

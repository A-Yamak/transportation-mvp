<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * =============================================================================
 * API V1 Base Form Request
 * =============================================================================
 *
 * All V1 API Form Requests should extend this class.
 * Provides consistent JSON error responses for validation failures.
 *
 * Version: 1.0
 *
 * =============================================================================
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Override in child classes for specific authorization logic.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     * Returns JSON response instead of redirecting.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     * Returns JSON response instead of default behavior.
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'You are not authorized to perform this action.',
            ], 403)
        );
    }
}

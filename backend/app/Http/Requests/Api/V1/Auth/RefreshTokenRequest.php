<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =============================================================================
 * Refresh Token Request
 * =============================================================================
 * Validates token refresh data.
 *
 * @group Authentication
 * =============================================================================
 */
class RefreshTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refresh_token.required' => 'Please provide a refresh token.',
        ];
    }

    /**
     * Get the validated refresh token.
     */
    public function getRefreshToken(): string
    {
        return $this->validated('refresh_token');
    }
}

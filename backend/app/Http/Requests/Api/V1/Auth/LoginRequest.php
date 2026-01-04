<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =============================================================================
 * Login Request
 * =============================================================================
 * Validates user login data.
 *
 * @group Authentication
 * =============================================================================
 */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
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
            'email.required' => 'Please provide your email address.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Please provide your password.',
        ];
    }

    /**
     * Get the validated email.
     */
    public function getEmail(): string
    {
        return $this->validated('email');
    }

    /**
     * Get the validated password.
     */
    public function getPassword(): string
    {
        return $this->validated('password');
    }

    /**
     * Get credentials for authentication.
     *
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'email' => $this->getEmail(),
            'password' => $this->getPassword(),
        ];
    }
}

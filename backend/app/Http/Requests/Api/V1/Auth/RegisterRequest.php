<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * =============================================================================
 * Register Request
 * =============================================================================
 * Validates user registration data.
 *
 * WHY USE FORM REQUESTS INSTEAD OF INLINE VALIDATION?
 *
 * 1. SEPARATION OF CONCERNS
 *    - Controller handles flow, Request handles validation
 *    - Easier to test validation rules in isolation
 *
 * 2. TYPED ACCESSOR METHODS
 *    - $request->getName() returns string (type-safe)
 *    - $request->input('name') returns mixed (not type-safe)
 *    - IDE autocomplete works better with typed methods
 *
 * 3. REUSABILITY
 *    - Same request class can be used in multiple controllers
 *    - Validation rules defined once, used everywhere
 *
 * 4. CUSTOM ERROR MESSAGES
 *    - messages() method provides user-friendly errors
 *    - Centralized message management
 *
 * WHEN TO USE INLINE VALIDATION INSTEAD:
 * - Simple one-off validation (1-2 fields)
 * - Internal/admin endpoints with minimal validation
 * - Prototyping (convert to FormRequest later)
 *
 * @group Authentication
 * =============================================================================
 */
class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
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
            'name.required' => 'Please provide your name.',
            'email.required' => 'Please provide your email address.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Please provide a password.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }

    /**
     * Get the validated name.
     */
    public function getName(): string
    {
        return $this->validated('name');
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
}

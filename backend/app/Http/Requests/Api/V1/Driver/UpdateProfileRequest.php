<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Driver Profile Request
 *
 * Validates profile update data for drivers.
 * Note: license_number is admin-only and cannot be updated by drivers.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'regex:/^\+?[0-9]{10,15}$/', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number format is invalid.',
        ];
    }

    /**
     * Get validated data for driver model.
     *
     * @return array<string, mixed>
     */
    public function driverData(): array
    {
        return $this->only(['phone']);
    }

    /**
     * Get validated data for user model.
     *
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        return $this->only(['name']);
    }
}

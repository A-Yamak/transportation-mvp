<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\External\V1\Shop;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Shop Request
 *
 * Validates individual shop updates from external business systems.
 */
class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Already authenticated via API key middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'track_waste' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,inactive'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric' => 'Latitude must be a number',
            'longitude.numeric' => 'Longitude must be a number',
            'status.in' => 'Status must be either active or inactive',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\External\V1\Shop;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sync Shops Request
 *
 * Validates bulk shop synchronization from external business systems.
 * Used to create/update multiple shops in a single request.
 */
class SyncShopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Already authenticated via API key middleware
    }

    public function rules(): array
    {
        return [
            'shops' => ['required', 'array', 'min:1', 'max:1000'],
            'shops.*.id' => ['required', 'string', 'max:255'],
            'shops.*.name' => ['required', 'string', 'max:255'],
            'shops.*.address' => ['nullable', 'string', 'max:500'],
            'shops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'shops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'shops.*.contact_name' => ['nullable', 'string', 'max:255'],
            'shops.*.contact_number' => ['nullable', 'string', 'max:20'],
            'shops.*.track_waste' => ['nullable', 'boolean'],
            'shops.*.status' => ['nullable', 'in:active,inactive'],
            'shops.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'shops.required' => 'Shops data is required',
            'shops.array' => 'Shops must be an array',
            'shops.min' => 'At least one shop is required',
            'shops.max' => 'Maximum 1000 shops per request',
            'shops.*.id.required' => 'Shop ID is required for each shop',
            'shops.*.name.required' => 'Shop name is required for each shop',
            'shops.*.latitude.numeric' => 'Latitude must be a number',
            'shops.*.longitude.numeric' => 'Longitude must be a number',
        ];
    }

    /**
     * Get shops data for processing.
     */
    public function getShops(): array
    {
        return $this->input('shops', []);
    }
}

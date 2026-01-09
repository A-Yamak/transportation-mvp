<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\External\V1\Waste;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Set Expected Waste Request
 *
 * Validates expected waste date updates for shops.
 * Used by external systems to indicate when shops have expected waste collections.
 */
class SetExpectedWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Already authenticated via API key middleware
    }

    public function rules(): array
    {
        return [
            'shops' => ['required', 'array', 'min:1', 'max:1000'],
            'shops.*.external_shop_id' => ['required', 'string', 'max:255'],
            'shops.*.expected_waste_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'shops.required' => 'Shops data is required',
            'shops.array' => 'Shops must be an array',
            'shops.min' => 'At least one shop is required',
            'shops.max' => 'Maximum 1000 shops per request',
            'shops.*.external_shop_id.required' => 'Shop ID is required',
            'shops.*.expected_waste_date.required' => 'Expected waste date is required',
            'shops.*.expected_waste_date.date_format' => 'Expected waste date must be in YYYY-MM-DD format',
            'shops.*.expected_waste_date.after_or_equal' => 'Expected waste date must be today or in the future',
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

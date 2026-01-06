<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Complete Trip Request
 *
 * Validates data when driver completes a trip.
 * Requires total_km from GPS tracking.
 */
class CompleteTripRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_km' => ['required', 'numeric', 'min:0', 'max:1000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_km.required' => 'Total kilometers driven is required.',
            'total_km.min' => 'Total kilometers cannot be negative.',
            'total_km.max' => 'Total kilometers cannot exceed 1000km.',
            'lat.between' => 'Latitude must be between -90 and 90 degrees.',
            'lng.between' => 'Longitude must be between -180 and 180 degrees.',
        ];
    }

    public function getTotalKm(): float
    {
        return (float) $this->validated('total_km');
    }

    public function getLat(): ?float
    {
        return $this->validated('lat') ? (float) $this->validated('lat') : null;
    }

    public function getLng(): ?float
    {
        return $this->validated('lng') ? (float) $this->validated('lng') : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Arrive At Destination Request
 *
 * Validates data when driver arrives at a destination.
 * Optional GPS coordinates for arrival location verification.
 */
class ArriveAtDestinationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'lat.between' => 'Latitude must be between -90 and 90 degrees.',
            'lng.between' => 'Longitude must be between -180 and 180 degrees.',
        ];
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

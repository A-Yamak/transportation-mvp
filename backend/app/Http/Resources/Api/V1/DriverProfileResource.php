<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Driver Profile Resource
 *
 * Transforms Driver model data for profile API responses.
 * Includes user info, vehicle details, and profile photo.
 */
class DriverProfileResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'price_per_km' => $this->price_per_km ? (float) $this->price_per_km : null,
            'profile_photo_url' => $this->profile_photo_url,
            'is_active' => $this->is_active,

            // Vehicle information
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle->id,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'year' => $this->vehicle->year,
                'full_name' => $this->vehicle->full_name,
                'license_plate' => $this->vehicle->license_plate,
                'acquisition_date' => $this->vehicle->acquisition_date?->toDateString(),
                'acquisition_km' => (float) $this->vehicle->acquisition_km,
                'total_km_driven' => (float) $this->vehicle->total_km_driven,
                'monthly_km_app' => (float) $this->vehicle->monthly_km_app,
                'app_tracked_km' => (float) $this->vehicle->app_tracked_km,
                // Fuel efficiency data
                'tank_capacity_liters' => $this->vehicle->tank_capacity_liters
                    ? (float) $this->vehicle->tank_capacity_liters : null,
                'full_tank_range_km' => $this->vehicle->full_tank_range_km
                    ? (float) $this->vehicle->full_tank_range_km : null,
                'km_per_liter' => $this->vehicle->km_per_liter,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

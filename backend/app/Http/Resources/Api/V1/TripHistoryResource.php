<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\DestinationStatus;
use Illuminate\Http\Request;

/**
 * Trip History Resource
 *
 * Transforms Trip model data for history/listing API responses.
 * Lighter than TripResource, focused on summary info.
 */
class TripHistoryResource extends ApiResource
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
            'status' => $this->status->value,
            'date' => $this->created_at?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration_minutes' => $this->getDurationMinutes(),
            'actual_km' => $this->actual_km ? (float) $this->actual_km : null,

            // Summary info from delivery request
            'destinations_count' => $this->whenLoaded('deliveryRequest', function () {
                return $this->deliveryRequest->destinations->count();
            }),
            'destinations_completed' => $this->whenLoaded('deliveryRequest', function () {
                return $this->deliveryRequest->destinations
                    ->where('status', DestinationStatus::Completed)
                    ->count();
            }),

            // Business info
            'business_name' => $this->whenLoaded('deliveryRequest', function () {
                return $this->deliveryRequest->business?->name;
            }),

            // Vehicle info
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'license_plate' => $this->vehicle->license_plate,
            ]),
        ];
    }

    /**
     * Calculate trip duration in minutes.
     */
    private function getDurationMinutes(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->completed_at);
    }
}

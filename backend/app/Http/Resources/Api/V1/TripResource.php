<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Trip Resource
 *
 * Transforms Trip model data for driver API responses.
 * Includes delivery request, destinations, and progress summary.
 */
class TripResource extends ApiResource
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
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'actual_km' => $this->actual_km ? (float) $this->actual_km : null,

            // Delivery request info
            'delivery_request' => $this->whenLoaded('deliveryRequest', fn () => [
                'id' => $this->deliveryRequest->id,
                'total_km' => (float) $this->deliveryRequest->total_km,
                'estimated_cost' => (float) $this->deliveryRequest->estimated_cost,
                'polyline' => $this->deliveryRequest->optimized_route['polyline'] ?? null,
            ]),

            // Destinations in optimized order
            'destinations' => $this->whenLoaded('deliveryRequest', function () {
                return DestinationResource::collection(
                    $this->deliveryRequest->destinations->sortBy('sequence_order')
                );
            }),

            // Vehicle info
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle->id,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'license_plate' => $this->vehicle->license_plate,
            ]),

            // Progress summary
            'progress' => $this->whenLoaded('deliveryRequest', function () {
                $destinations = $this->deliveryRequest->destinations;
                return [
                    'total_destinations' => $destinations->count(),
                    'completed' => $destinations->where('status', \App\Enums\DestinationStatus::Completed)->count(),
                    'pending' => $destinations->whereIn('status', [
                        \App\Enums\DestinationStatus::Pending,
                        \App\Enums\DestinationStatus::Arrived,
                    ])->count(),
                    'failed' => $destinations->where('status', \App\Enums\DestinationStatus::Failed)->count(),
                ];
            }),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms DeliveryRequest model to API response.
 *
 * @property-read \App\Models\DeliveryRequest $resource
 */
class DeliveryRequestResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'total_km' => $this->resource->total_km ? (float) $this->resource->total_km : null,
            'estimated_cost' => $this->resource->estimated_cost ? (float) $this->resource->estimated_cost : null,
            'actual_km' => $this->resource->actual_km ? (float) $this->resource->actual_km : null,
            'actual_cost' => $this->resource->actual_cost ? (float) $this->resource->actual_cost : null,
            'destination_count' => $this->resource->total_destinations,
            'completed_count' => $this->resource->completed_count,
            'optimized_route' => $this->resource->optimized_route,
            'notes' => $this->resource->notes,
            'requested_at' => $this->resource->requested_at?->toIso8601String(),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),

            // Include destinations when loaded
            'destinations' => $this->when(
                $this->resource->relationLoaded('destinations'),
                fn () => DestinationResource::collection($this->resource->destinations)
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms Destination model to API response.
 *
 * @property-read \App\Models\Destination $resource
 */
class DestinationResource extends ApiResource
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
            'external_id' => $this->resource->external_id,
            'address' => $this->resource->address,
            'lat' => (float) $this->resource->lat,
            'lng' => (float) $this->resource->lng,
            'sequence_order' => $this->resource->sequence_order,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'navigation_url' => $this->resource->navigation_url,
            'notes' => $this->resource->notes,
            'recipient_name' => $this->resource->recipient_name,
            'failure_reason' => $this->resource->failure_reason?->value,
            'failure_notes' => $this->resource->failure_notes,
            'arrived_at' => $this->resource->arrived_at?->toIso8601String(),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            // Item-level delivery tracking
            'items' => DestinationItemResource::collection($this->whenLoaded('items')),
            'has_item_tracking' => $this->when(
                $this->resource->relationLoaded('items'),
                fn () => $this->resource->items->isNotEmpty()
            ),
        ];
    }
}

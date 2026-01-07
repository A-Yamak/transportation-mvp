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
            'contact_name' => $this->resource->contact_name,
            'contact_phone' => $this->resource->contact_phone,
            'sequence_order' => $this->resource->sequence_order,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'navigation_url' => $this->resource->navigation_url,
            'notes' => $this->resource->notes,
            // Payment info
            'amount_to_collect' => $this->resource->amount_to_collect ? (float) $this->resource->amount_to_collect : null,
            'amount_collected' => $this->resource->amount_collected ? (float) $this->resource->amount_collected : null,
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
            // Calculate items total if items are loaded
            'items_total' => $this->when(
                $this->resource->relationLoaded('items') && $this->resource->items->isNotEmpty(),
                fn () => $this->resource->calculateTotalFromItems()
            ),
        ];
    }
}

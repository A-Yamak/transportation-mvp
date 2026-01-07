<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms DestinationItem model to API response.
 *
 * @property-read \App\Models\DestinationItem $resource
 */
class DestinationItemResource extends ApiResource
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
            'order_item_id' => $this->resource->order_item_id,
            'name' => $this->resource->name,
            'unit_price' => $this->resource->unit_price ? (float) $this->resource->unit_price : null,
            'quantity_ordered' => $this->resource->quantity_ordered,
            'quantity_delivered' => $this->resource->quantity_delivered,
            'line_total' => $this->resource->line_total,
            'delivered_total' => $this->resource->delivered_total,
            'shortage' => $this->resource->shortage,
            'is_fully_delivered' => $this->resource->isFullyDelivered(),
            'delivery_reason' => $this->resource->delivery_reason?->value,
            'delivery_reason_label' => $this->resource->delivery_reason?->label(),
            'notes' => $this->resource->notes,
        ];
    }
}

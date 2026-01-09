<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms TupperwareMovement model to API response.
 *
 * @property-read \App\Models\TupperwareMovement $resource
 */
class TupperwareMovementResource extends ApiResource
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
            'shop_id' => $this->resource->shop_id,
            'destination_id' => $this->resource->destination_id,
            'trip_id' => $this->resource->trip_id,
            'driver_id' => $this->resource->driver_id,
            'business_id' => $this->resource->business_id,
            'product_type' => $this->resource->product_type,
            'quantity_delivered' => $this->resource->quantity_delivered,
            'quantity_returned' => $this->resource->quantity_returned,
            'shop_balance_before' => $this->resource->shop_balance_before,
            'shop_balance_after' => $this->resource->shop_balance_after,
            'net_change' => $this->resource->net_change,
            'movement_type' => $this->resource->movement_type,
            'movement_type_label' => ucfirst($this->resource->movement_type),
            'is_delivery' => $this->resource->isDelivery(),
            'is_return' => $this->resource->isReturn(),
            'notes' => $this->resource->notes,
            'movement_at' => $this->resource->movement_at->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}

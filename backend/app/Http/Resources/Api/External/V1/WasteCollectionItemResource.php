<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\External\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Waste Collection Item Resource
 *
 * Transforms WasteCollectionItem model for external API response.
 *
 * @mixin \App\Models\WasteCollectionItem
 */
class WasteCollectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product_name' => $this->product_name,
            'quantity_delivered' => $this->quantity_delivered,
            'delivered_at' => $this->delivered_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'pieces_waste' => $this->pieces_waste,
            'pieces_sold' => $this->pieces_sold,
            'waste_percentage' => round($this->getWastePercentage(), 2),
            'is_expired' => $this->isExpired(),
            'days_expired' => $this->getDaysExpired(),
            'notes' => $this->notes,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\External\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shop Resource
 *
 * Transforms Shop model for external API response.
 *
 * @mixin \App\Models\Shop
 */
class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_shop_id' => $this->external_shop_id,
            'name' => $this->name,
            'address' => $this->address,
            'lat' => $this->lat?->__toString(),
            'lng' => $this->lng?->__toString(),
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'track_waste' => $this->track_waste,
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

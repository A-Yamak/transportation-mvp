<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms DailyReconciliation model to API response.
 *
 * @property-read \App\Models\DailyReconciliation $resource
 */
class DailyReconciliationResource extends ApiResource
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
            'driver_id' => $this->resource->driver_id,
            'business_id' => $this->resource->business_id,
            'reconciliation_date' => $this->resource->reconciliation_date->toDateString(),
            'total_expected' => (float) $this->resource->total_expected,
            'total_collected' => (float) $this->resource->total_collected,
            'total_cash' => (float) $this->resource->total_cash,
            'total_cliq' => (float) $this->resource->total_cliq,
            'shortage_amount' => (float) $this->resource->shortage_amount,
            'overage_amount' => (float) $this->resource->overage_amount,
            'collection_rate' => $this->resource->collection_rate,
            'cash_percentage' => $this->resource->cash_percentage,
            'cliq_percentage' => $this->resource->cliq_percentage,
            'trips_completed' => $this->resource->trips_completed,
            'deliveries_completed' => $this->resource->deliveries_completed,
            'total_km_driven' => (float) $this->resource->total_km_driven,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->color(),
            'has_shortage' => $this->resource->hasShortage(),
            'has_overage' => $this->resource->hasOverage(),
            'is_fully_collected' => $this->resource->isFullyCollected(),
            'shop_breakdown' => $this->resource->shop_breakdown,
            'submitted_at' => $this->resource->submitted_at?->toIso8601String(),
            'acknowledged_at' => $this->resource->acknowledged_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),
        ];
    }
}

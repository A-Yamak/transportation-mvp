<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Transforms PaymentCollection model to API response.
 *
 * @property-read \App\Models\PaymentCollection $resource
 */
class PaymentCollectionResource extends ApiResource
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
            'destination_id' => $this->resource->destination_id,
            'trip_id' => $this->resource->trip_id,
            'driver_id' => $this->resource->driver_id,
            'amount_expected' => (float) $this->resource->amount_expected,
            'amount_collected' => (float) $this->resource->amount_collected,
            'payment_method' => $this->resource->payment_method->value,
            'payment_method_label' => $this->resource->payment_method->label(),
            'cliq_reference' => $this->resource->cliq_reference,
            'payment_status' => $this->resource->payment_status->value,
            'payment_status_label' => $this->resource->payment_status->label(),
            'payment_status_color' => $this->resource->payment_status->color(),
            'shortage_amount' => round((float) $this->resource->shortage_amount, 2),
            'shortage_percentage' => $this->resource->hasShortage()
                ? round(($this->resource->shortage_amount / $this->resource->amount_expected) * 100, 2)
                : 0,
            'shortage_reason' => $this->resource->shortage_reason,
            'notes' => $this->resource->notes,
            'is_fully_collected' => $this->resource->isFullyCollected(),
            'is_partial' => $this->resource->isPartial(),
            'has_shortage' => $this->resource->hasShortage(),
            'is_cash_payment' => $this->resource->isCashPayment(),
            'is_cliq_payment' => $this->resource->isCliQPayment(),
            'collected_at' => $this->resource->collected_at->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}

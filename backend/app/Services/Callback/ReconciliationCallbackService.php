<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Jobs\SendReconciliationCallbackJob;
use App\Models\DailyReconciliation;

/**
 * Reconciliation Callback Service
 *
 * Handles sending daily reconciliation data back to external business systems (Melo ERP).
 * Similar pattern to WasteCallbackService and DeliveryCallbackService.
 */
class ReconciliationCallbackService
{
    public function __construct(
        protected CallbackService $callbackService,
    ) {}

    /**
     * Send reconciliation callback to business (async via queue).
     *
     * @param  DailyReconciliation  $reconciliation
     * @return void
     */
    public function sendReconciliationCallback(DailyReconciliation $reconciliation): void
    {
        SendReconciliationCallbackJob::dispatch($reconciliation->id);
    }

    /**
     * Send reconciliation callback synchronously (for testing/immediate delivery).
     *
     * @param  DailyReconciliation  $reconciliation
     * @return bool
     */
    public function sendReconciliationCallbackSync(DailyReconciliation $reconciliation): bool
    {
        $business = $reconciliation->business;

        if (! $business?->callback_url) {
            return false;
        }

        $payload = $this->buildCallbackPayload($reconciliation);

        return $this->callbackService->sendCallback(
            $business->callback_url,
            $payload,
            $business->callback_api_key
        );
    }

    /**
     * Build callback payload for daily reconciliation.
     *
     * @param  DailyReconciliation  $reconciliation
     * @return array
     */
    public function buildCallbackPayload(DailyReconciliation $reconciliation): array
    {
        $driver = $reconciliation->driver;

        return [
            'event' => 'daily_reconciliation_submitted',
            'reconciliation_id' => $reconciliation->id,
            'reconciliation_date' => $reconciliation->reconciliation_date->toDateString(),
            'submitted_at' => $reconciliation->submitted_at?->toIso8601String(),
            'driver' => [
                'id' => $driver?->id,
                'name' => $driver?->name,
            ],
            'summary' => [
                'total_expected' => (float) $reconciliation->total_expected,
                'total_collected' => (float) $reconciliation->total_collected,
                'total_cash' => (float) $reconciliation->total_cash,
                'total_cliq' => (float) $reconciliation->total_cliq,
                'shortage_amount' => $reconciliation->shortage_amount,
                'collection_rate' => $reconciliation->collection_rate,
            ],
            'metrics' => [
                'trips_completed' => $reconciliation->trips_completed,
                'deliveries_completed' => $reconciliation->deliveries_completed,
                'total_km_driven' => (float) $reconciliation->total_km_driven,
            ],
            'shop_breakdown' => $reconciliation->shop_breakdown ?? [],
            'status' => $reconciliation->status->value ?? $reconciliation->status,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Jobs\SendWasteCallbackJob;
use App\Models\Shop;
use App\Models\WasteCollection;

/**
 * Waste Callback Service
 *
 * Handles sending waste collection data back to external business systems.
 * Similar pattern to existing DeliveryCallbackService.
 */
class WasteCallbackService
{
    public function __construct(
        protected CallbackService $callbackService,
    ) {}

    /**
     * Send waste collection callback to business (async via queue).
     *
     * @param  WasteCollection  $wasteCollection
     * @return void
     */
    public function sendWasteCallback(WasteCollection $wasteCollection): void
    {
        SendWasteCallbackJob::dispatch($wasteCollection->id);
    }

    /**
     * Send waste callback synchronously (for testing/immediate delivery).
     *
     * @param  WasteCollection  $wasteCollection
     * @return bool
     */
    public function sendWasteCallbackSync(WasteCollection $wasteCollection): bool
    {
        $shop = $wasteCollection->shop;
        $business = $wasteCollection->business;

        if (! $business->callback_url) {
            return false;
        }

        $payload = $this->buildCallbackPayload($wasteCollection);

        return $this->callbackService->sendCallback(
            $business->callback_url,
            $payload,
            $business->callback_api_key
        );
    }

    /**
     * Build callback payload for waste collection.
     *
     * @param  WasteCollection  $wasteCollection
     * @return array
     */
    public function buildCallbackPayload(WasteCollection $wasteCollection): array
    {
        $shop = $wasteCollection->shop;

        return [
            'event' => 'waste_collected',
            'shop_id' => $shop->external_shop_id,
            'shop_name' => $shop->name,
            'collection_date' => $wasteCollection->collection_date->toDateString(),
            'collected_at' => $wasteCollection->collected_at?->toIso8601String(),
            'collected_by_user_id' => $wasteCollection->driver_id,
            'waste_items' => $wasteCollection->items->map(fn ($item) => [
                'order_item_id' => $item->order_item_id,
                'product_name' => $item->product_name,
                'quantity_delivered' => $item->quantity_delivered,
                'pieces_returned' => $item->pieces_waste,
                'pieces_sold' => $item->pieces_sold,
                'waste_date' => $item->delivered_at?->toDateString(),
                'notes' => $item->notes,
            ])->toArray(),
        ];
    }
}

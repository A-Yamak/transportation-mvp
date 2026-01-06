<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Models\Destination;
use Illuminate\Support\Facades\Log;

/**
 * Delivery Callback Service
 *
 * Sends callbacks to client ERPs when deliveries are completed.
 *
 * TODO [DEV-1]: Implement actual callback logic:
 * 1. Get the business's payload schema
 * 2. Transform destination data using schema
 * 3. Send HTTP POST to business's callback_url
 * 4. Handle retry logic for failed callbacks
 *
 * @see \App\Models\BusinessPayloadSchema for callback field mapping
 */
class DeliveryCallbackService
{
    /**
     * Send delivery completion callback to business ERP.
     *
     * Called by DriverController when a destination is completed.
     * Transforms the destination data according to the business's
     * payload schema and sends to their callback URL.
     */
    public function sendCallback(Destination $destination): void
    {
        // TODO [DEV-1]: Implement callback sending logic
        // Example implementation:
        //
        // $business = $destination->deliveryRequest->business;
        // $schema = $business->payloadSchema;
        //
        // if (!$business->callback_url) {
        //     return; // No callback configured
        // }
        //
        // $payload = $this->schemaTransformer->transformCallback($destination, $schema);
        //
        // Http::withToken($business->callback_api_key)
        //     ->timeout(30)
        //     ->retry(3, 1000)
        //     ->post($business->callback_url, $payload);

        // Temporary: Log instead of sending actual callback
        Log::info('DeliveryCallbackService: Callback would be sent', [
            'destination_id' => $destination->id,
            'external_id' => $destination->external_id,
            'status' => $destination->status->value,
            'completed_at' => $destination->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Send batch callbacks for multiple completed destinations.
     *
     * TODO [DEV-1]: Implement if business supports batch callbacks.
     *
     * @param  \Illuminate\Support\Collection<Destination>  $destinations
     */
    public function sendBatchCallback($destinations): void
    {
        foreach ($destinations as $destination) {
            $this->sendCallback($destination);
        }
    }
}

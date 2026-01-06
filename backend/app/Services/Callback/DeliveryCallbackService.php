<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Models\Destination;

/**
 * Delivery Callback Service
 *
 * Sends callbacks to client ERPs when deliveries are completed.
 * Delegates to CallbackService for actual HTTP communication.
 *
 * @see \App\Services\Callback\CallbackService for HTTP implementation
 * @see \App\Models\BusinessPayloadSchema for callback field mapping
 */
class DeliveryCallbackService
{
    public function __construct(
        private readonly CallbackService $callbackService,
    ) {}

    /**
     * Send delivery completion callback to business ERP.
     *
     * Called by DriverController when a destination is completed or failed.
     * Transforms the destination data according to the business's
     * payload schema and sends to their callback URL.
     */
    public function sendCallback(Destination $destination): void
    {
        $this->callbackService->sendCompletionCallback($destination);
    }

    /**
     * Send batch callbacks for multiple completed destinations.
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

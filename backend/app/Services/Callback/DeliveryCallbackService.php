<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Jobs\SendDeliveryCallbackJob;
use App\Models\Destination;

/**
 * Delivery Callback Service
 *
 * Sends callbacks to client ERPs when deliveries are completed.
 * Uses async job queue by default for reliability and retry logic.
 * Delegates to CallbackService for actual HTTP communication.
 *
 * @see \App\Jobs\SendDeliveryCallbackJob for async processing
 * @see \App\Services\Callback\CallbackService for HTTP implementation
 * @see \App\Models\BusinessPayloadSchema for callback field mapping
 */
class DeliveryCallbackService
{
    public function __construct(
        private readonly CallbackService $callbackService,
    ) {}

    /**
     * Send delivery completion callback to business ERP (async via queue).
     *
     * Called by DriverController when a destination is completed or failed.
     * Dispatches a job to the 'callbacks' queue for reliable delivery
     * with retry logic and exponential backoff.
     */
    public function sendCallback(Destination $destination): void
    {
        SendDeliveryCallbackJob::dispatch($destination->id);
    }

    /**
     * Send delivery completion callback synchronously (for testing/immediate feedback).
     *
     * Use this when you need immediate confirmation of callback success,
     * such as in admin testing interfaces.
     */
    public function sendCallbackSync(Destination $destination): bool
    {
        return $this->callbackService->sendCompletionCallback($destination);
    }

    /**
     * Send batch callbacks for multiple completed destinations.
     *
     * Each callback is dispatched as a separate job for independent retry handling.
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

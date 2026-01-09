<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WasteCollection;
use App\Services\Callback\WasteCallbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send Waste Callback Job
 *
 * Asynchronously sends waste collection data to external business systems.
 * Implements exponential backoff retry strategy.
 */
class SendWasteCallbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300]; // Exponential backoff: 10s, 30s, 1m, 2m, 5m
    }

    public function __construct(
        public string $wasteCollectionId,
    ) {
        $this->onQueue('callbacks');
        $this->delay(0); // Process immediately
    }

    public function handle(WasteCallbackService $callbackService): void
    {
        $wasteCollection = WasteCollection::find($this->wasteCollectionId);

        if (! $wasteCollection) {
            Log::warning('Waste collection not found for callback', [
                'waste_collection_id' => $this->wasteCollectionId,
            ]);
            return;
        }

        $business = $wasteCollection->business;

        if (! $business->callback_url) {
            Log::info('No callback URL for business', [
                'business_id' => $business->id,
                'waste_collection_id' => $this->wasteCollectionId,
            ]);
            return;
        }

        try {
            $success = $callbackService->sendWasteCallbackSync($wasteCollection);

            if ($success) {
                Log::info('Waste callback sent successfully', [
                    'business_id' => $business->id,
                    'waste_collection_id' => $wasteCollection->id,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                // Failure in HTTP request, will retry
                $this->fail(new \Exception('Waste callback HTTP request failed'));
            }
        } catch (\Throwable $e) {
            Log::error('Waste callback job failed', [
                'business_id' => $business->id,
                'waste_collection_id' => $wasteCollection->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1]);
            } else {
                $this->fail($e);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Waste callback job failed permanently', [
            'waste_collection_id' => $this->wasteCollectionId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}

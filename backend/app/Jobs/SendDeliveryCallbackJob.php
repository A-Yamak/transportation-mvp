<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Destination;
use App\Services\Callback\CallbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async job to send delivery completion callbacks to ERP systems.
 *
 * This job is dispatched when a driver marks a destination as completed or failed.
 * It handles retry logic and exponential backoff for failed callbacks.
 *
 * Queue: callbacks (dedicated queue for ERP integration)
 * Retries: 5 attempts with exponential backoff
 */
class SendDeliveryCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 10s, 30s, 60s, 120s, 300s
        return [10, 30, 60, 120, 300];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $destinationId,
    ) {
        $this->onQueue('callbacks');
    }

    /**
     * Execute the job.
     */
    public function handle(CallbackService $callbackService): void
    {
        $destination = Destination::with(['deliveryRequest.business.payloadSchema', 'items'])
            ->find($this->destinationId);

        if (! $destination) {
            Log::warning('SendDeliveryCallbackJob: Destination not found', [
                'destination_id' => $this->destinationId,
            ]);

            return;
        }

        $success = $callbackService->sendCompletionCallback($destination);

        if (! $success && $this->attempts() < $this->tries) {
            // Let the job fail to trigger retry with backoff
            throw new \RuntimeException(
                "Callback failed for destination {$this->destinationId}, attempt {$this->attempts()}"
            );
        }

        if (! $success) {
            Log::error('SendDeliveryCallbackJob: All retry attempts failed', [
                'destination_id' => $this->destinationId,
                'attempts' => $this->attempts(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('SendDeliveryCallbackJob failed permanently', [
            'destination_id' => $this->destinationId,
            'exception' => $exception?->getMessage(),
        ]);

        // Optional: Mark destination as callback_failed in database
        // or dispatch a notification to admins
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'callback',
            'destination:'.$this->destinationId,
        ];
    }
}

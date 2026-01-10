<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyReconciliation;
use App\Services\Callback\ReconciliationCallbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send Reconciliation Callback Job
 *
 * Asynchronously sends daily reconciliation data to external business systems (Melo ERP).
 * Implements exponential backoff retry strategy.
 */
class SendReconciliationCallbackJob implements ShouldQueue
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
        public string $reconciliationId,
    ) {
        $this->onQueue('callbacks');
        $this->delay(0); // Process immediately
    }

    public function handle(ReconciliationCallbackService $callbackService): void
    {
        $reconciliation = DailyReconciliation::find($this->reconciliationId);

        if (! $reconciliation) {
            Log::warning('Daily reconciliation not found for callback', [
                'reconciliation_id' => $this->reconciliationId,
            ]);
            return;
        }

        $business = $reconciliation->business;

        if (! $business?->callback_url) {
            Log::info('No callback URL for business', [
                'business_id' => $business?->id,
                'reconciliation_id' => $this->reconciliationId,
            ]);
            return;
        }

        try {
            $success = $callbackService->sendReconciliationCallbackSync($reconciliation);

            if ($success) {
                Log::info('Reconciliation callback sent successfully', [
                    'business_id' => $business->id,
                    'reconciliation_id' => $reconciliation->id,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                // Failure in HTTP request, will retry
                $this->fail(new \Exception('Reconciliation callback HTTP request failed'));
            }
        } catch (\Throwable $e) {
            Log::error('Reconciliation callback job failed', [
                'business_id' => $business->id,
                'reconciliation_id' => $reconciliation->id,
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
        Log::error('Reconciliation callback job failed permanently', [
            'reconciliation_id' => $this->reconciliationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}

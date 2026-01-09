<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Notification $notification,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $result = $notificationService->sendFcmSync($this->notification);

        if ($result) {
            Log::info("FCM notification {$this->notification->id} sent successfully");
        } else {
            Log::warning("FCM notification {$this->notification->id} failed, will retry");
            // Job will be automatically retried based on maxTries setting
        }
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 10s, 30s, 1m, 2m, 5m
        return [10, 30, 60, 120, 300];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function timeout(): int
    {
        return 30; // 30 seconds
    }

    /**
     * Get the maximum number of attempts for this job.
     */
    public function tries(): int
    {
        return 5;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(
            "FCM notification {$this->notification->id} failed permanently after retries: {$exception->getMessage()}"
        );

        $this->notification->markAsFailed();
    }
}

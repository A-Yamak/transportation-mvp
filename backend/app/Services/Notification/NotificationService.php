<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Jobs\SendFcmNotificationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create and dispatch a notification to a driver
     */
    public function notifyDriver(User $driver, string $type, string $title, string $body, array $data = []): ?Notification
    {
        // Create notification record
        $notification = Notification::create([
            'user_id' => $driver->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'pending',
        ]);

        // Dispatch FCM job if driver has token
        if ($driver->hasFcmToken()) {
            SendFcmNotificationJob::dispatch($notification);
        }

        return $notification;
    }

    /**
     * Send FCM notification synchronously (for testing/critical notifications)
     */
    public function sendFcmSync(Notification $notification): bool
    {
        $driver = $notification->user;

        if (!$driver?->hasFcmToken()) {
            Log::warning("Driver {$driver->id} has no FCM token");
            $notification->markAsFailed();
            return false;
        }

        try {
            $result = $this->sendToFcm($notification->user->fcm_token, [
                'title' => $notification->title,
                'body' => $notification->body,
            ], $notification->data);

            if ($result) {
                $notification->markAsSent();
                return true;
            }

            $notification->markAsFailed();
            return false;
        } catch (\Exception $e) {
            Log::error("FCM send failed for notification {$notification->id}: {$e->getMessage()}");
            $notification->markAsFailed();
            return false;
        }
    }

    /**
     * Send to FCM via Firebase Admin SDK or HTTP endpoint
     */
    private function sendToFcm(string $token, array $notification, array $data = []): bool
    {
        try {
            // If you have Firebase Admin SDK configured, use it
            // For now, assuming you send to your own backend endpoint that forwards to FCM
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.fcm.key'),
            ])->post(config('services.fcm.url') . '/send', [
                'token' => $token,
                'notification' => $notification,
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("FCM HTTP request failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Notify driver of trip assignment
     */
    public function notifyTripAssigned(User $driver, array $tripData): ?Notification
    {
        return $this->notifyDriver(
            $driver,
            'trip_assigned',
            'New Trip Assigned',
            "Trip with {$tripData['destinations_count']} deliveries has been assigned to you",
            [
                'trip_id' => $tripData['trip_id'],
                'action' => 'open_trip',
                'total_km' => $tripData['total_km'],
                'estimated_cost' => $tripData['estimated_cost'],
            ]
        );
    }

    /**
     * Notify driver of trip reassignment
     */
    public function notifyTripReassigned(User $driver, array $tripData): ?Notification
    {
        return $this->notifyDriver(
            $driver,
            'trip_reassigned',
            'Trip Reassigned',
            "Your trip has been reassigned. Check the app for details.",
            [
                'trip_id' => $tripData['trip_id'],
                'action' => 'open_trip',
            ]
        );
    }

    /**
     * Notify driver of payment
     */
    public function notifyPaymentReceived(User $driver, float $amount, string $reference = ''): ?Notification
    {
        return $this->notifyDriver(
            $driver,
            'payment_received',
            'Payment Received',
            "You received a payment of \${$amount}",
            [
                'amount' => $amount,
                'reference' => $reference,
                'action' => 'open_earnings',
            ]
        );
    }

    /**
     * Notify driver of action required
     */
    public function notifyActionRequired(User $driver, string $actionTitle, string $actionBody, array $actionData = []): ?Notification
    {
        return $this->notifyDriver(
            $driver,
            'action_required',
            $actionTitle,
            $actionBody,
            $actionData
        );
    }

    /**
     * Bulk notify multiple drivers
     */
    public function notifyMultiple(array $drivers, string $type, string $title, string $body, array $data = []): array
    {
        return array_map(
            fn($driver) => $this->notifyDriver($driver, $type, $title, $body, $data),
            $drivers
        );
    }

    /**
     * Resend failed notifications
     */
    public function retrySendFailed(): int
    {
        $failedNotifications = Notification::where('status', 'failed')
            ->where('created_at', '>', now()->subHours(24))
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($failedNotifications as $notification) {
            if ($notification->user->hasFcmToken()) {
                SendFcmNotificationJob::dispatch($notification);
                $count++;
            }
        }

        return $count;
    }
}

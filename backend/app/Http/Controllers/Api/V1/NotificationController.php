<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\RegisterFcmTokenRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get paginated notifications for authenticated driver
     * GET /api/v1/driver/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();

        $notifications = Notification::forDriver($driver)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get unread notifications count
     * GET /api/v1/driver/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $driver = $request->user();

        $unreadCount = Notification::forDriver($driver)
            ->unread()
            ->count();

        return response()->json([
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Get unread notifications
     * GET /api/v1/driver/notifications/unread
     */
    public function unread(Request $request): JsonResponse
    {
        $driver = $request->user();

        $notifications = Notification::forDriver($driver)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * Mark notification as read
     * PATCH /api/v1/driver/notifications/{notification}/read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $driver = $request->user();

        // Verify ownership
        if ($notification->user_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'data' => $notification,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark notification as unread
     * PATCH /api/v1/driver/notifications/{notification}/unread
     */
    public function markAsUnread(Request $request, Notification $notification): JsonResponse
    {
        $driver = $request->user();

        // Verify ownership
        if ($notification->user_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $notification->markAsUnread();

        return response()->json([
            'data' => $notification,
            'message' => 'Notification marked as unread',
        ]);
    }

    /**
     * Mark all notifications as read
     * PATCH /api/v1/driver/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $driver = $request->user();

        Notification::forDriver($driver)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification
     * DELETE /api/v1/driver/notifications/{notification}
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $driver = $request->user();

        // Verify ownership
        if ($notification->user_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Register FCM token
     * POST /api/v1/driver/notifications/register-token
     */
    public function registerFcmToken(RegisterFcmTokenRequest $request): JsonResponse
    {
        $driver = $request->user();
        $token = $request->validated()['fcm_token'];

        $driver->updateFcmToken($token);

        return response()->json([
            'data' => [
                'fcm_token' => $driver->fcm_token,
                'updated_at' => $driver->fcm_token_updated_at,
            ],
            'message' => 'FCM token registered successfully',
        ]);
    }
}

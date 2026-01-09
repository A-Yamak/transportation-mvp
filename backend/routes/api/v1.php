<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeliveryRequestController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TripAssignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All V1 API routes are defined here. Routes are automatically prefixed
| with /api/v1 and use the 'api' middleware group.
|
| Controllers: App\Http\Controllers\Api\V1\*
| Requests:    App\Http\Requests\Api\V1\*
| Resources:   App\Http\Resources\Api\V1\*
|
| Status: Active (Current Version)
|
*/

// -----------------------------------------------------------------------------
// Public Routes (No Authentication Required)
// -----------------------------------------------------------------------------

Route::get('/', fn () => response()->json([
    'version' => 'v1',
    'status' => 'active',
    'message' => 'Al-Sabiqoon API V1',
]));

// Health check (useful for load balancers)
Route::get('/health', fn () => response()->json(['status' => 'healthy']));

// -----------------------------------------------------------------------------
// Authentication Routes
// -----------------------------------------------------------------------------

Route::prefix('auth')->group(function () {
    // Public auth routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// -----------------------------------------------------------------------------
// Protected Routes (Authentication Required)
// -----------------------------------------------------------------------------

Route::middleware('auth:api')->group(function () {
    // -----------------------------------------------------------------------------
    // Driver Routes - Trip Management (for mobile app)
    // -----------------------------------------------------------------------------
    Route::prefix('driver')->group(function () {
        // Driver Profile & Dashboard
        Route::get('profile', [DriverController::class, 'profile']);
        Route::put('profile', [DriverController::class, 'updateProfile']);
        Route::post('profile/photo', [DriverController::class, 'uploadProfilePhoto']);
        Route::put('vehicle/odometer', [DriverController::class, 'updateOdometer']);
        Route::get('stats', [DriverController::class, 'stats']);
        Route::get('trips/history', [DriverController::class, 'tripHistory']);

        // Today's trips for driver
        Route::get('trips/today', [DriverController::class, 'todaysTrips']);

        // Trip details
        Route::get('trips/{trip}', [DriverController::class, 'showTrip']);

        // Trip lifecycle
        Route::post('trips/{trip}/start', [DriverController::class, 'startTrip']);
        Route::post('trips/{trip}/complete', [DriverController::class, 'completeTrip']);

        // Destination management
        Route::post('trips/{trip}/destinations/{destination}/arrive', [DriverController::class, 'arriveAtDestination']);
        Route::post('trips/{trip}/destinations/{destination}/complete', [DriverController::class, 'completeDestination']);
        Route::post('trips/{trip}/destinations/{destination}/fail', [DriverController::class, 'failDestination']);
        Route::get('trips/{trip}/destinations/{destination}/navigate', [DriverController::class, 'getNavigationUrl']);

        // Shops and waste collection
        Route::get('shops', [DriverController::class, 'listShops']);
        Route::get('shops/{shop}/waste-expected', [DriverController::class, 'getExpectedWaste']);
        Route::post('trips/{trip}/shops/{shop}/waste-collected', [DriverController::class, 'logWasteCollection']);

        // Notification management
        Route::prefix('notifications')->group(function () {
            Route::post('register-token', [NotificationController::class, 'registerFcmToken']);
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::get('unread', [NotificationController::class, 'unread']);
            Route::patch('{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::patch('{notification}/unread', [NotificationController::class, 'markAsUnread']);
            Route::patch('mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('{notification}', [NotificationController::class, 'destroy']);
        });
    });

    // -----------------------------------------------------------------------------
    // Trip Assignment Routes - Admin/Dispatch Operations
    // -----------------------------------------------------------------------------
    Route::prefix('trips')->group(function () {
        Route::post('assign', [TripAssignmentController::class, 'assign']);
        Route::get('unassigned', [TripAssignmentController::class, 'unassigned']);
        Route::get('available-drivers', [TripAssignmentController::class, 'availableDrivers']);
    });
});

// -----------------------------------------------------------------------------
// Business API Routes (API Key Authentication)
// -----------------------------------------------------------------------------
// These routes use X-API-Key header authentication for B2B/ERP integrations.
// Businesses authenticate with their api_key to submit delivery requests.

Route::middleware('auth.api_key')->group(function () {
    // Delivery Requests - Core ERP integration endpoints
    Route::prefix('delivery-requests')->group(function () {
        Route::get('/', [DeliveryRequestController::class, 'index'])
            ->name('delivery-requests.index');
        Route::post('/', [DeliveryRequestController::class, 'store'])
            ->name('delivery-requests.store');
        Route::get('{deliveryRequest}', [DeliveryRequestController::class, 'show'])
            ->name('delivery-requests.show');
        Route::get('{deliveryRequest}/route', [DeliveryRequestController::class, 'route'])
            ->name('delivery-requests.route');
        Route::post('{deliveryRequest}/cancel', [DeliveryRequestController::class, 'cancel'])
            ->name('delivery-requests.cancel');
    });
});

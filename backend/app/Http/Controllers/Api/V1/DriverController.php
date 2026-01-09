<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DestinationStatus;
use App\Enums\ItemDeliveryReason;
use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\Driver\ArriveAtDestinationRequest;
use App\Http\Requests\Api\V1\Driver\CompleteDestinationRequest;
use App\Http\Requests\Api\V1\Driver\CompleteTripRequest;
use App\Http\Requests\Api\V1\Driver\FailDestinationRequest;
use App\Http\Requests\Api\V1\Driver\StartTripRequest;
use App\Http\Requests\Api\V1\Driver\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Driver\UploadProfilePhotoRequest;
use App\Http\Resources\Api\V1\DestinationResource;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Http\Resources\Api\V1\DriverStatsResource;
use App\Http\Resources\Api\V1\TripHistoryResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Http\Requests\Api\V1\Driver\LogWasteCollectionRequest;
use App\Models\Shop;
use App\Models\WasteCollection;
use App\Models\WasteCollectionItem;
use App\Services\Callback\DeliveryCallbackService;
use App\Services\Callback\WasteCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Driver Controller
 *
 * Handles all driver trip management endpoints.
 * Used by the Flutter mobile app for delivery operations.
 */
class DriverController extends Controller
{
    public function __construct(
        private readonly DeliveryCallbackService $callbackService,
        private readonly WasteCallbackService $wasteCallbackService,
    ) {}

    /**
     * Get today's trips for the authenticated driver.
     *
     * GET /api/v1/driver/trips/today
     */
    public function todaysTrips(): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        $trips = Trip::where('driver_id', $driver->id)
            ->today()
            ->with(['deliveryRequest.destinations.items', 'deliveryRequest.business', 'vehicle'])
            ->orderBy('created_at')
            ->get();

        return TripResource::collection($trips)->response();
    }

    /**
     * Get trip details with destinations.
     *
     * GET /api/v1/driver/trips/{trip}
     */
    public function showTrip(Trip $trip): TripResource
    {
        $this->authorizeTrip($trip);

        return new TripResource(
            $trip->load(['deliveryRequest.destinations.items', 'vehicle'])
        );
    }

    /**
     * Start a trip - driver begins their route.
     *
     * POST /api/v1/driver/trips/{trip}/start
     */
    public function startTrip(Trip $trip, StartTripRequest $request): TripResource|JsonResponse
    {
        $this->authorizeTrip($trip);

        if ($trip->status !== TripStatus::NotStarted) {
            return $this->error(
                'Trip cannot be started - current status: ' . $trip->status->value,
                422
            );
        }

        // Use the model's start method
        $trip->start();

        return new TripResource(
            $trip->fresh(['deliveryRequest.destinations', 'vehicle'])
        );
    }

    /**
     * Mark arrival at a destination.
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/arrive
     */
    public function arriveAtDestination(
        Trip $trip,
        Destination $destination,
        ArriveAtDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if ($destination->status !== DestinationStatus::Pending) {
            return $this->error(
                'Destination cannot be marked as arrived - current status: ' . $destination->status->value,
                422
            );
        }

        // Use the model's markArrived method
        $destination->markArrived();

        return new DestinationResource($destination->fresh());
    }

    /**
     * Complete delivery at a destination.
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/complete
     */
    public function completeDestination(
        Trip $trip,
        Destination $destination,
        CompleteDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if (! in_array($destination->status, [DestinationStatus::Pending, DestinationStatus::Arrived])) {
            return $this->error(
                'Destination cannot be completed - current status: '.$destination->status->value,
                422
            );
        }

        // Handle item-level delivery data if provided
        if ($request->hasItemData()) {
            $this->saveItemDeliveryData($destination, $request->getItems());
        }

        // Use the model's markCompleted method
        $destination->markCompleted(
            $request->getRecipientName(),
            $request->getNotes()
        );

        // Send callback to ERP with item data if available
        $this->callbackService->sendCallback($destination->fresh(['items']));

        return new DestinationResource($destination->fresh(['items']));
    }

    /**
     * Save item-level delivery data for a destination.
     *
     * Updates existing items (created from ERP) or creates new ones.
     * Preserves original quantity_ordered if item already exists.
     *
     * @param  array<int, array{order_item_id: string, quantity_ordered?: int, quantity_delivered: int, reason?: string, notes?: string}>  $items
     */
    private function saveItemDeliveryData(Destination $destination, array $items): void
    {
        foreach ($items as $itemData) {
            // Check if item already exists (created when ERP submitted the delivery request)
            $existingItem = $destination->items()
                ->where('order_item_id', $itemData['order_item_id'])
                ->first();

            if ($existingItem) {
                // Update existing item - preserve original quantity_ordered
                $existingItem->update([
                    'quantity_delivered' => $itemData['quantity_delivered'],
                    'delivery_reason' => isset($itemData['reason'])
                        ? ItemDeliveryReason::from($itemData['reason'])
                        : null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            } else {
                // Create new item (fallback if ERP didn't send items upfront)
                $destination->items()->create([
                    'order_item_id' => $itemData['order_item_id'],
                    'quantity_ordered' => $itemData['quantity_ordered'] ?? $itemData['quantity_delivered'],
                    'quantity_delivered' => $itemData['quantity_delivered'],
                    'delivery_reason' => isset($itemData['reason'])
                        ? ItemDeliveryReason::from($itemData['reason'])
                        : null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }
        }
    }

    /**
     * Mark destination as failed (shop closed, wrong address, etc.)
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/fail
     */
    public function failDestination(
        Trip $trip,
        Destination $destination,
        FailDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if (!in_array($destination->status, [DestinationStatus::Pending, DestinationStatus::Arrived])) {
            return $this->error(
                'Destination cannot be marked as failed - current status: ' . $destination->status->value,
                422
            );
        }

        // Use the model's markFailed method
        $destination->markFailed(
            $request->getReason(),
            $request->getNotes()
        );

        // Send callback to ERP with failure status
        $this->callbackService->sendCallback($destination);

        return new DestinationResource($destination->fresh());
    }

    /**
     * Complete entire trip.
     *
     * POST /api/v1/driver/trips/{trip}/complete
     */
    public function completeTrip(Trip $trip, CompleteTripRequest $request): TripResource|JsonResponse
    {
        $this->authorizeTrip($trip);

        if ($trip->status !== TripStatus::InProgress) {
            return $this->error(
                'Trip cannot be completed - current status: ' . $trip->status->value,
                422
            );
        }

        // Check all destinations are completed or failed
        $incompleteCount = $trip->deliveryRequest->destinations()
            ->whereNotIn('status', [
                DestinationStatus::Completed,
                DestinationStatus::Failed,
            ])
            ->count();

        if ($incompleteCount > 0) {
            return $this->error(
                "Cannot complete trip - {$incompleteCount} destinations not completed",
                422
            );
        }

        // Use the model's complete method
        $trip->complete($request->getTotalKm());

        return new TripResource(
            $trip->fresh(['deliveryRequest.destinations', 'vehicle'])
        );
    }

    /**
     * Get Google Maps navigation URL for a destination.
     *
     * GET /api/v1/driver/trips/{trip}/destinations/{destination}/navigate
     */
    public function getNavigationUrl(Trip $trip, Destination $destination): JsonResponse
    {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        return $this->success([
            'url' => $destination->navigation_url,
            'destination' => new DestinationResource($destination),
        ]);
    }

    /**
     * Get the authenticated driver or abort.
     */
    private function getAuthenticatedDriver(): Driver
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            abort(403, 'No driver profile associated with this user');
        }

        return $driver;
    }

    /**
     * Verify the trip belongs to the authenticated driver.
     */
    private function authorizeTrip(Trip $trip): void
    {
        $driver = $this->getAuthenticatedDriver();

        if ($trip->driver_id !== $driver->id) {
            abort(403, 'This trip does not belong to you');
        }
    }

    /**
     * Verify the destination belongs to the trip's delivery request.
     */
    private function validateDestinationBelongsToTrip(Trip $trip, Destination $destination): void
    {
        if ($destination->delivery_request_id !== $trip->delivery_request_id) {
            abort(404, 'Destination not found in this trip');
        }
    }

    // =========================================================================
    // Driver Profile & Dashboard Endpoints
    // =========================================================================

    /**
     * Get driver profile with vehicle info.
     *
     * GET /api/v1/driver/profile
     */
    public function profile(): DriverProfileResource|JsonResponse
    {
        $driver = auth()->user()->driver;

        if (! $driver) {
            return $this->error('Driver profile not found', 404);
        }

        return new DriverProfileResource(
            $driver->load('vehicle', 'user')
        );
    }

    /**
     * Update driver profile.
     *
     * PUT /api/v1/driver/profile
     */
    public function updateProfile(UpdateProfileRequest $request): DriverProfileResource
    {
        $driver = $this->getAuthenticatedDriver();

        // Update driver fields (only phone is allowed)
        $driverData = array_filter($request->driverData());
        if (! empty($driverData)) {
            $driver->update($driverData);
        }

        // Update user fields (name)
        $userData = array_filter($request->userData());
        if (! empty($userData)) {
            $driver->user->update($userData);
        }

        return new DriverProfileResource(
            $driver->fresh(['vehicle', 'user'])
        );
    }

    /**
     * Update vehicle odometer reading.
     *
     * PUT /api/v1/driver/vehicle/odometer
     */
    public function updateOdometer(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        if (! $driver->vehicle) {
            return $this->error('No vehicle assigned to this driver', 404);
        }

        $validated = $request->validate([
            'total_km_driven' => 'required|numeric|min:0',
        ]);

        $driver->vehicle->update([
            'total_km_driven' => $validated['total_km_driven'],
        ]);

        return $this->success([
            'total_km_driven' => (float) $driver->vehicle->fresh()->total_km_driven,
            'app_tracked_km' => (float) $driver->vehicle->fresh()->app_tracked_km,
        ], 'Odometer updated successfully');
    }

    /**
     * Upload driver profile photo.
     *
     * POST /api/v1/driver/profile/photo
     */
    public function uploadProfilePhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        // Delete old photo if exists
        if ($driver->profile_photo_path) {
            Storage::disk('r2')->delete($driver->profile_photo_path);
        }

        // Store new photo with driver ID as filename
        $extension = $request->file('photo')->getClientOriginalExtension();
        $filename = $driver->id.'.'.$extension;
        $path = $request->file('photo')->storeAs('driver-photos', $filename, 'r2');

        $driver->update(['profile_photo_path' => $path]);

        return $this->success([
            'profile_photo_url' => $driver->fresh()->profile_photo_url,
        ], 'Profile photo uploaded successfully');
    }

    /**
     * Get driver statistics (KM, deliveries, etc.)
     *
     * GET /api/v1/driver/stats
     */
    public function stats(): DriverStatsResource
    {
        $driver = $this->getAuthenticatedDriver();
        $driver->load('vehicle');

        // Only count COMPLETED trips
        $completedStatus = TripStatus::Completed;

        // Today's stats - only completed trips
        $todaysTrips = $driver->trips()
            ->whereDate('created_at', today())
            ->where('status', $completedStatus)
            ->with('deliveryRequest.destinations')
            ->get();

        $todayKm = $todaysTrips->sum('actual_km') ?? 0;
        $todayCompletedDestinations = $todaysTrips->sum(fn ($trip) =>
            $trip->deliveryRequest->destinations
                ->where('status', DestinationStatus::Completed)
                ->count()
        );

        // This month's stats - only completed trips
        $monthlyTrips = $driver->trips()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', $completedStatus)
            ->with('deliveryRequest.destinations')
            ->get();

        $monthlyKm = $monthlyTrips->sum('actual_km') ?? 0;
        $monthlyCompletedDestinations = $monthlyTrips->sum(fn ($trip) =>
            $trip->deliveryRequest->destinations
                ->where('status', DestinationStatus::Completed)
                ->count()
        );

        // All-time stats - only completed trips
        $allTimeTrips = $driver->trips()
            ->where('status', $completedStatus)
            ->with('deliveryRequest.destinations')
            ->get();

        $allTimeKm = $allTimeTrips->sum('actual_km') ?? 0;
        $allTimeCompletedDestinations = $allTimeTrips->sum(fn ($trip) =>
            $trip->deliveryRequest->destinations
                ->where('status', DestinationStatus::Completed)
                ->count()
        );

        return new DriverStatsResource([
            'today' => [
                'trips_count' => $todaysTrips->count(),
                'destinations_completed' => $todayCompletedDestinations,
                'km_driven' => round((float) $todayKm, 2),
            ],
            'this_month' => [
                'trips_count' => $monthlyTrips->count(),
                'destinations_completed' => $monthlyCompletedDestinations,
                'km_driven' => round((float) $monthlyKm, 2),
            ],
            'all_time' => [
                'trips_count' => $allTimeTrips->count(),
                'destinations_completed' => $allTimeCompletedDestinations,
                'km_driven' => round((float) $allTimeKm, 2),
            ],
            'vehicle' => $driver->vehicle ? [
                'acquisition_km' => (float) $driver->vehicle->acquisition_km,
                'total_km_driven' => (float) $driver->vehicle->total_km_driven,
                'app_tracked_km' => (float) $driver->vehicle->app_tracked_km,
            ] : null,
        ]);
    }

    /**
     * Get driver trip history with pagination.
     *
     * GET /api/v1/driver/trips/history
     */
    public function tripHistory(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        $query = $driver->trips()
            ->with(['deliveryRequest.destinations', 'deliveryRequest.business', 'vehicle'])
            ->orderBy('created_at', 'desc');

        // Filter by date range
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', TripStatus::from($request->status));
        }

        $trips = $query->paginate(15);

        return TripHistoryResource::collection($trips)->response();
    }

    // ========== SHOPS & WASTE COLLECTION ENDPOINTS ==========

    /**
     * List shops with expected waste for the driver.
     *
     * GET /api/v1/driver/shops
     *
     * Returns shops that have pending waste collections, along with
     * their waste status and item counts.
     */
    public function listShops(): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        // Get all active shops with pending waste (not yet collected)
        $shops = Shop::where('is_active', true)
            ->where('track_waste', true)
            ->with(['wasteCollections' => function ($query) {
                $query->whereNull('collected_at')
                    ->with('items');
            }])
            ->get();

        $shopsData = $shops->map(function ($shop) {
            $pendingCollections = $shop->wasteCollections;
            $wasteItems = $pendingCollections->pluck('items')->flatten();

            $totalDelivered = $wasteItems->sum('quantity_delivered');
            $totalWaste = $wasteItems->sum('pieces_waste');
            $expiredCount = $wasteItems->filter(fn ($item) => $item->isExpired())->count();

            return [
                'id' => $shop->id,
                'external_id' => $shop->external_shop_id,
                'name' => $shop->name,
                'address' => $shop->address,
                'lat' => $shop->lat ? (float) $shop->lat : null,
                'lng' => $shop->lng ? (float) $shop->lng : null,
                'contact_name' => $shop->contact_name,
                'contact_phone' => $shop->contact_phone,
                'has_pending_waste' => $wasteItems->isNotEmpty(),
                'waste_summary' => [
                    'items_count' => $wasteItems->count(),
                    'total_delivered' => $totalDelivered,
                    'total_waste' => $totalWaste,
                    'total_sold' => $totalDelivered - $totalWaste,
                    'expired_items_count' => $expiredCount,
                ],
                'is_collected' => $pendingCollections->isEmpty() || $pendingCollections->every(fn ($c) => $c->collected_at !== null),
            ];
        });

        // Sort: shops with pending waste first
        $sorted = $shopsData->sortByDesc('has_pending_waste')->values();

        return response()->json([
            'data' => $sorted,
            'meta' => [
                'total' => $shops->count(),
                'with_pending_waste' => $shopsData->where('has_pending_waste', true)->count(),
            ],
        ]);
    }

    /**
     * Get expected waste items for a shop.
     *
     * GET /api/v1/driver/shops/{shop}/waste-expected
     *
     * Returns list of waste items expected to be collected from this shop.
     */
    public function getExpectedWaste(Shop $shop): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        // Verify shop belongs to driver's business (via delivery request)
        // For MVP, we allow access to any shop - can be restricted later

        $wasteCollections = $shop->wasteCollections()
            ->where('collected_at', null)
            ->with('items')
            ->get();

        $wasteItems = $wasteCollections
            ->pluck('items')
            ->flatten();

        return response()->json([
            'data' => [
                'shop' => [
                    'id' => $shop->id,
                    'external_id' => $shop->external_shop_id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'contact_phone' => $shop->contact_phone,
                ],
                'waste_items' => $wasteItems->map(fn ($item) => [
                    'id' => $item->id,
                    'order_item_id' => $item->order_item_id,
                    'product_name' => $item->product_name,
                    'quantity_delivered' => $item->quantity_delivered,
                    'delivered_at' => $item->delivered_at?->toDateString(),
                    'expires_at' => $item->expires_at?->toDateString(),
                    'is_expired' => $item->isExpired(),
                    'days_expired' => $item->getDaysExpired(),
                ])->toArray(),
                'total_expected_items' => $wasteItems->count(),
            ],
        ]);
    }

    /**
     * Log waste collection for a shop during a trip.
     *
     * POST /api/v1/driver/trips/{trip}/shops/{shop}/waste-collected
     *
     * Request body:
     * {
     *   "waste_items": [
     *     {
     *       "waste_item_id": "uuid",
     *       "pieces_waste": 3,
     *       "notes": "Optional notes"
     *     }
     *   ],
     *   "driver_notes": "Optional overall notes"
     * }
     */
    public function logWasteCollection(
        Trip $trip,
        Shop $shop,
        LogWasteCollectionRequest $request
    ): JsonResponse {
        $driver = $this->getAuthenticatedDriver();

        $this->authorizeTrip($trip);

        // Verify this is a waste collection trip
        if (! $trip->isWasteCollection()) {
            return $this->error('This trip is not a waste collection trip', 422);
        }

        // Get or create waste collection record
        $wasteCollection = WasteCollection::firstOrCreate(
            [
                'shop_id' => $shop->id,
                'trip_id' => $trip->id,
                'collection_date' => today(),
            ],
            [
                'business_id' => $trip->deliveryRequest->business_id,
                'driver_id' => $driver->id,
            ]
        );

        $collectedCount = 0;

        // Update waste items with collected data
        foreach ($request->getWasteItems() as $wasteData) {
            $wasteItem = WasteCollectionItem::find($wasteData['waste_item_id']);

            if ($wasteItem) {
                // Validate waste quantity doesn't exceed delivered
                if ($wasteData['pieces_waste'] > $wasteItem->quantity_delivered) {
                    return $this->error(
                        "Waste quantity cannot exceed delivered quantity for item {$wasteItem->order_item_id}",
                        422
                    );
                }

                $wasteItem->update([
                    'pieces_waste' => $wasteData['pieces_waste'],
                    'notes' => $wasteData['notes'] ?? $wasteItem->notes,
                    'waste_collection_id' => $wasteCollection->id,
                ]);

                $collectedCount++;
            }
        }

        // Mark collection as complete
        $wasteCollection->update([
            'collected_at' => now(),
            'driver_notes' => $request->getDriverNotes(),
            'total_items_count' => $collectedCount,
        ]);

        // Send callback to business system
        $this->wasteCallbackService->sendWasteCallback($wasteCollection);

        return response()->json([
            'data' => [
                'waste_collection_id' => $wasteCollection->id,
                'shop_id' => $shop->id,
                'collected_items_count' => $collectedCount,
                'collected_at' => $wasteCollection->collected_at?->toIso8601String(),
            ],
            'message' => "Waste collection logged successfully for {$shop->name}",
        ]);
    }
}

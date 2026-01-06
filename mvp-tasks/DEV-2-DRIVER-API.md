# DEV-2: Driver API - Trip Management Endpoints

> **Role**: Driver API Developer
> **Branch**: `feature/dev-2-driver-api`
> **Dependency**: WAIT for DEV-1 to complete DeliveryRequestController first

---

## SCRUM MASTER AVAILABLE

A scrum master is **ALWAYS available** to help you:
- Resolve blockers or unclear requirements
- Answer architecture questions
- Coordinate with DEV-1 and DEV-3
- Review your approach before implementation

**Don't hesitate to ask for help!** It's better to clarify than to build the wrong thing.

---

## WATERFALL DEPENDENCY

**IMPORTANT**: You depend on DEV-1's work. While waiting:

1. **Study the existing models**:
   ```bash
   ls backend/app/Models/
   cat backend/app/Models/Driver.php
   cat backend/app/Models/Trip.php
   cat backend/app/Models/DeliveryRequest.php
   ```

2. **Review existing API structure**:
   ```bash
   ls backend/app/Http/Controllers/Api/V1/
   cat backend/routes/api/v1.php
   ```

3. **Write test skeletons for driver endpoints**

When DEV-1 completes, you can implement the driver flow.

---

## YOUR RESPONSIBILITIES (What You DO)

### Task 1: DriverController - Today's Trips

**Files to create**:
- `backend/app/Http/Controllers/Api/V1/DriverController.php`
- `backend/app/Http/Resources/Api/V1/TripResource.php`
- `backend/app/Http/Resources/Api/V1/DriverDestinationResource.php`

**Routes to add** in `backend/routes/api/v1.php`:
```php
Route::prefix('driver')->middleware('auth:api')->group(function () {
    // Trip management
    Route::get('trips/today', [DriverController::class, 'todaysTrips']);
    Route::get('trips/{trip}', [DriverController::class, 'showTrip']);
    Route::post('trips/{trip}/start', [DriverController::class, 'startTrip']);
    Route::post('trips/{trip}/complete', [DriverController::class, 'completeTrip']);

    // Destination management
    Route::post('trips/{trip}/destinations/{destination}/arrive', [DriverController::class, 'arriveAtDestination']);
    Route::post('trips/{trip}/destinations/{destination}/complete', [DriverController::class, 'completeDestination']);
    Route::get('trips/{trip}/destinations/{destination}/navigate', [DriverController::class, 'getNavigationUrl']);
});
```

**Controller Implementation**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TripResource;
use App\Http\Resources\Api\V1\DriverDestinationResource;
use App\Models\Trip;
use App\Models\Destination;
use App\Services\DeliveryCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private readonly DeliveryCallbackService $callbackService,
    ) {}

    /**
     * Get today's trips for the authenticated driver.
     */
    public function todaysTrips(): JsonResponse
    {
        $driver = auth()->user()->driver;

        $trips = Trip::where('driver_id', $driver->id)
            ->whereDate('scheduled_date', today())
            ->with(['deliveryRequest.destinations'])
            ->orderBy('scheduled_date')
            ->get();

        return TripResource::collection($trips)->response();
    }

    /**
     * Get trip details with destinations.
     */
    public function showTrip(Trip $trip): TripResource
    {
        $this->authorizeTrip($trip);

        return new TripResource($trip->load(['deliveryRequest.destinations', 'vehicle']));
    }

    /**
     * Start a trip - driver begins their route.
     */
    public function startTrip(Trip $trip, Request $request): TripResource
    {
        $this->authorizeTrip($trip);

        if ($trip->status !== 'pending') {
            return response()->json([
                'message' => 'Trip cannot be started - current status: ' . $trip->status,
            ], 422);
        }

        $trip->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'start_location_lat' => $request->input('lat'),
            'start_location_lng' => $request->input('lng'),
        ]);

        return new TripResource($trip->fresh(['deliveryRequest.destinations']));
    }

    /**
     * Mark arrival at a destination.
     */
    public function arriveAtDestination(Trip $trip, Destination $destination, Request $request): DriverDestinationResource
    {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        $destination->update([
            'status' => 'arrived',
            'arrived_at' => now(),
            'arrival_lat' => $request->input('lat'),
            'arrival_lng' => $request->input('lng'),
        ]);

        return new DriverDestinationResource($destination->fresh());
    }

    /**
     * Complete delivery at a destination.
     */
    public function completeDestination(Trip $trip, Destination $destination, Request $request): DriverDestinationResource
    {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string'], // Base64 encoded
            'photo' => ['nullable', 'string'], // Base64 encoded
        ]);

        $destination->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $request->input('notes'),
            'signature_url' => $this->storeSignature($request->input('signature')),
            'photo_url' => $this->storePhoto($request->input('photo')),
        ]);

        // Send callback to ERP
        $this->callbackService->sendCallback($destination);

        return new DriverDestinationResource($destination->fresh());
    }

    /**
     * Complete entire trip.
     */
    public function completeTrip(Trip $trip, Request $request): TripResource
    {
        $this->authorizeTrip($trip);

        $request->validate([
            'total_km' => ['required', 'numeric', 'min:0'],
        ]);

        // Check all destinations are completed
        $incompleteCount = $trip->deliveryRequest->destinations()
            ->where('status', '!=', 'completed')
            ->count();

        if ($incompleteCount > 0) {
            return response()->json([
                'message' => "Cannot complete trip - {$incompleteCount} destinations not completed",
            ], 422);
        }

        $trip->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_km' => $request->input('total_km'),
            'end_location_lat' => $request->input('lat'),
            'end_location_lng' => $request->input('lng'),
        ]);

        // Update vehicle km
        $trip->vehicle->increment('total_km_driven', $request->input('total_km'));
        $trip->vehicle->increment('monthly_km_app', $request->input('total_km'));

        return new TripResource($trip->fresh(['deliveryRequest.destinations']));
    }

    /**
     * Get Google Maps navigation URL for a destination.
     */
    public function getNavigationUrl(Trip $trip, Destination $destination): JsonResponse
    {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        $url = "https://www.google.com/maps/dir/?api=1" .
               "&destination={$destination->lat},{$destination->lng}" .
               "&travelmode=driving";

        return response()->json([
            'data' => [
                'url' => $url,
                'destination' => new DriverDestinationResource($destination),
            ],
        ]);
    }

    private function authorizeTrip(Trip $trip): void
    {
        if ($trip->driver_id !== auth()->user()->driver->id) {
            abort(403, 'This trip does not belong to you');
        }
    }

    private function validateDestinationBelongsToTrip(Trip $trip, Destination $destination): void
    {
        if ($destination->delivery_request_id !== $trip->delivery_request_id) {
            abort(404, 'Destination not found in this trip');
        }
    }

    private function storeSignature(?string $base64): ?string
    {
        if (!$base64) return null;
        // TODO: Store to R2 and return URL
        return null;
    }

    private function storePhoto(?string $base64): ?string
    {
        if (!$base64) return null;
        // TODO: Store to R2 and return URL
        return null;
    }
}
```

---

### Task 2: TripResource

**File**: `backend/app/Http/Resources/Api/V1/TripResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'actual_km' => $this->actual_km,

            // Delivery request info
            'delivery_request' => [
                'id' => $this->deliveryRequest->id,
                'total_km' => $this->deliveryRequest->total_km,
                'estimated_cost' => $this->deliveryRequest->estimated_cost,
                'polyline' => $this->deliveryRequest->optimized_route,
            ],

            // Destinations in optimized order
            'destinations' => DriverDestinationResource::collection(
                $this->whenLoaded('deliveryRequest', function () {
                    return $this->deliveryRequest->destinations->sortBy('sequence_order');
                })
            ),

            // Vehicle info
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'plate_number' => $this->vehicle->plate_number,
            ]),

            // Progress summary
            'progress' => [
                'total_destinations' => $this->deliveryRequest->destinations->count(),
                'completed' => $this->deliveryRequest->destinations->where('status', 'completed')->count(),
                'pending' => $this->deliveryRequest->destinations->where('status', 'pending')->count(),
            ],
        ];
    }
}
```

---

### Task 3: DriverDestinationResource

**File**: `backend/app/Http/Resources/Api/V1/DriverDestinationResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class DriverDestinationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sequence_order' => $this->sequence_order,
            'external_id' => $this->external_id,
            'status' => $this->status,

            // Location
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,

            // Contact
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'notes' => $this->notes,

            // Timestamps
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Proof of delivery
            'signature_url' => $this->signature_url,
            'photo_url' => $this->photo_url,

            // Navigation helper
            'navigation_url' => "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}&travelmode=driving",
        ];
    }
}
```

---

### Task 4: Trip Assignment (Admin/Dispatch)

**Files to create**:
- `backend/app/Http/Controllers/Api/V1/TripAssignmentController.php`
- `backend/app/Http/Requests/Api/V1/AssignTripRequest.php`

**Routes**:
```php
Route::prefix('trips')->middleware('auth:api')->group(function () {
    Route::post('assign', [TripAssignmentController::class, 'assign']);
    Route::get('unassigned', [TripAssignmentController::class, 'unassigned']);
});
```

**Controller**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AssignTripRequest;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Trip;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class TripAssignmentController extends Controller
{
    /**
     * Assign a delivery request to a driver.
     */
    public function assign(AssignTripRequest $request): TripResource
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->input('delivery_request_id'));
        $driver = Driver::findOrFail($request->input('driver_id'));
        $vehicle = Vehicle::findOrFail($request->input('vehicle_id'));

        // Check if delivery request already has a trip
        if ($deliveryRequest->trip()->exists()) {
            return response()->json([
                'message' => 'Delivery request already assigned to a trip',
            ], 422);
        }

        $trip = Trip::create([
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'scheduled_date' => $request->input('scheduled_date', today()),
            'status' => 'pending',
        ]);

        // Update delivery request status
        $deliveryRequest->update(['status' => 'assigned']);

        return new TripResource($trip->load(['deliveryRequest.destinations', 'vehicle']));
    }

    /**
     * List unassigned delivery requests.
     */
    public function unassigned(): JsonResponse
    {
        $deliveryRequests = DeliveryRequest::where('status', 'pending')
            ->whereDoesntHave('trip')
            ->with('destinations')
            ->orderBy('created_at')
            ->paginate();

        return response()->json([
            'data' => $deliveryRequests,
        ]);
    }
}
```

---

### Task 5: Write Feature Tests

```
tests/Feature/Api/V1/DriverControllerTest.php
- test_lists_todays_trips_for_driver
- test_shows_trip_with_destinations
- test_starts_trip
- test_marks_arrival_at_destination
- test_completes_destination
- test_sends_callback_on_destination_completion
- test_completes_trip_when_all_destinations_done
- test_rejects_trip_completion_with_pending_destinations
- test_returns_navigation_url
- test_prevents_access_to_other_drivers_trips

tests/Feature/Api/V1/TripAssignmentControllerTest.php
- test_assigns_delivery_request_to_driver
- test_lists_unassigned_delivery_requests
- test_prevents_double_assignment
```

---

## NOT YOUR RESPONSIBILITY (Handled by Others)

| Task | Handled By | Why Not You |
|------|------------|-------------|
| DeliveryRequestController | DEV-1 | Creates the delivery requests |
| RouteOptimizer service | DEV-1 | Google Maps integration |
| Flutter app UI | DEV-3 | Different tech stack |
| Callback payload building | DEV-1 | Part of callback service |

---

## TECH STACK REFERENCE

**Laravel 12 + PHP 8.4**:
- Controllers in `app/Http/Controllers/Api/V1/`
- Resources for all API responses
- Use middleware for auth

**Authentication**:
- Drivers authenticate via OAuth2
- Driver record linked to User via `user.driver_id`
- Use `auth()->user()->driver` to get Driver model

**Testing**:
```bash
make test-filter name="DriverControllerTest"
make test-filter name="TripAssignmentControllerTest"
make test
```

---

## EXPECTED STANDARDS

1. **Authorization**: Check driver owns trip before any operation
2. **State Machine**: Validate status transitions (pending → in_progress → completed)
3. **Resources**: Transform all responses
4. **Logging**: Log important operations
5. **Error Messages**: Clear, actionable error messages

---

## DEFINITION OF DONE

- [ ] DriverController with 7 endpoints
- [ ] TripResource with full trip data
- [ ] DriverDestinationResource with navigation URL
- [ ] Driver routes added to api/v1.php
- [ ] DriverControllerTest with 10+ tests passing
- [ ] TripAssignmentController for dispatching
- [ ] TripAssignmentControllerTest passing
- [ ] All tests pass: `make test`
- [ ] Code linted: `make lint`
- [ ] Changes committed to your branch

---

## HANDOFF FROM DEV-1

DEV-1 provides:
1. `DeliveryRequest` model with destinations
2. `DeliveryCallbackService` - you call this when destination completes
3. Routes for ERP to submit delivery requests

Wait for DEV-1 to confirm their work is complete before implementing.

## HANDOFF TO DEV-3

DEV-3 (Flutter) will consume your endpoints:
- `GET /api/v1/driver/trips/today`
- `POST /api/v1/driver/trips/{id}/start`
- `POST /api/v1/driver/trips/{id}/destinations/{id}/arrive`
- `POST /api/v1/driver/trips/{id}/destinations/{id}/complete`
- `GET /api/v1/driver/trips/{id}/destinations/{id}/navigate`

**Document your response structures for DEV-3!**

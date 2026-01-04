# Developer 2: Module B - Driver Mobile App API

**Date**: 2026-01-05
**Phase**: Phase 3 - API Endpoints (Driver API)
**Estimated Time**: 6-8 hours
**Priority**: HIGH (Critical for driver operations)

---

## 🎯 Mission

Build the complete API layer for the **Flutter Driver Mobile App**. This allows drivers to view their assigned trips, navigate to destinations, mark arrivals/completions, and trigger ERP callbacks. When destinations are completed, the system automatically records revenue in the ledger and sends callbacks to client ERPs.

**Key Focus**: You own the ENTIRE driver-facing API. No dependencies on other developers.

---

## 📋 Your Module Ownership

### Files You Will Create (Complete Ownership)

```
routes/api/v1.php (add your routes only)
app/Http/Controllers/Api/V1/Driver/DriverTripController.php
app/Http/Controllers/Api/V1/Driver/DriverDestinationController.php
app/Http/Requests/Api/V1/Driver/StartTripRequest.php
app/Http/Requests/Api/V1/Driver/CompleteDestinationRequest.php
app/Http/Resources/Api/V1/Driver/TripResource.php
app/Http/Resources/Api/V1/Driver/DestinationResource.php
tests/Feature/Api/V1/Driver/DriverTripApiTest.php
tests/Feature/Api/V1/Driver/DriverDestinationApiTest.php
```

### Services You Will Use (Already Built)

- ✅ `CallbackService` - Sends completion notifications to client ERPs
- ✅ `LedgerService` - Records trip revenue in double-entry accounting
- ✅ `CostCalculator` - Calculates final trip cost (if KM differs from estimate)

**No Integration Needed**: Other developers are building different endpoints. You work completely independently.

---

## 🗂️ API Endpoints You Will Build

### 1. GET /api/v1/driver/trips/today
**Purpose**: Get all trips assigned to the authenticated driver for today

**Request Example**:
```
GET /api/v1/driver/trips/today
Authorization: Bearer {driver_token}
```

**Response Example** (200 OK):
```json
{
  "data": [
    {
      "id": "9d8f7a6b-...",
      "delivery_request_id": "9d8f7a6b-...",
      "status": "not_started",
      "business_name": "Sweets Factory ERP",
      "total_destinations": 3,
      "completed_destinations": 0,
      "estimated_km": 12.5,
      "started_at": null,
      "completed_at": null,
      "destinations": [
        {
          "id": "9d8f7a6b-...",
          "address": "Abdali Mall, Amman",
          "lat": 31.9730,
          "lng": 35.9087,
          "sequence_order": 1,
          "status": "pending"
        },
        {
          "id": "9d8f7a6b-...",
          "address": "Rainbow St, Amman",
          "lat": 31.9539,
          "lng": 35.9106,
          "sequence_order": 2,
          "status": "pending"
        },
        {
          "id": "9d8f7a6b-...",
          "address": "Swefieh, Amman",
          "lat": 31.9332,
          "lng": 35.8621,
          "sequence_order": 3,
          "status": "pending"
        }
      ]
    }
  ]
}
```

**Processing Flow**:
1. Get authenticated driver: `$request->user()` (assumes driver is a User)
2. Find driver's trips for today
3. Load destinations with each trip
4. Return with `TripResource::collection()`

---

### 2. POST /api/v1/driver/trips/{id}/start
**Purpose**: Mark trip as started (driver begins their route)

**Request Example**:
```
POST /api/v1/driver/trips/9d8f7a6b-.../start
Authorization: Bearer {driver_token}
```

**Response Example** (200 OK):
```json
{
  "data": {
    "id": "9d8f7a6b-...",
    "status": "in_progress",
    "started_at": "2026-01-05T10:30:00Z",
    "destinations": [...]
  }
}
```

**Processing Flow**:
1. Validate trip belongs to authenticated driver
2. Check trip status is `not_started`
3. Update trip: `status = in_progress`, `started_at = now()`
4. Return updated trip

---

### 3. POST /api/v1/driver/destinations/{id}/arrive
**Purpose**: Mark driver as arrived at destination (GPS confirmation)

**Request Example**:
```
POST /api/v1/driver/destinations/9d8f7a6b-.../arrive
Authorization: Bearer {driver_token}
```

**Response Example** (200 OK):
```json
{
  "data": {
    "id": "9d8f7a6b-...",
    "address": "Abdali Mall, Amman",
    "status": "arrived",
    "arrived_at": "2026-01-05T10:45:00Z"
  }
}
```

**Processing Flow**:
1. Validate destination belongs to driver's trip
2. Check status is `pending`
3. Update: `status = arrived`, `arrived_at = now()`
4. Return updated destination

---

### 4. POST /api/v1/driver/destinations/{id}/complete
**Purpose**: Mark delivery as completed (triggers callback + ledger entry)

**Request Example**:
```
POST /api/v1/driver/destinations/9d8f7a6b-.../complete
Authorization: Bearer {driver_token}
Content-Type: application/json

{
  "signature_url": "https://r2.cloudflare.com/signatures/abc123.png",
  "photo_url": "https://r2.cloudflare.com/photos/def456.jpg",
  "notes": "Delivered to receptionist"
}
```

**Response Example** (200 OK):
```json
{
  "data": {
    "id": "9d8f7a6b-...",
    "status": "completed",
    "arrived_at": "2026-01-05T10:45:00Z",
    "completed_at": "2026-01-05T10:50:00Z",
    "signature_url": "https://r2.cloudflare.com/signatures/abc123.png",
    "photo_url": "https://r2.cloudflare.com/photos/def456.jpg"
  },
  "message": "Delivery completed. Callback sent to ERP."
}
```

**Processing Flow**:
1. Validate destination belongs to driver's trip
2. Check status is `arrived` or `pending`
3. Update: `status = completed`, `completed_at = now()`, save signature/photo URLs
4. **Send callback to client ERP** via `CallbackService`
5. Check if ALL destinations in trip are completed:
   - If yes: Mark trip as `completed`
   - Calculate actual KM vs estimated KM
   - **Record revenue in ledger** via `LedgerService::recordTripRevenue()`
6. Return updated destination

---

### 5. POST /api/v1/driver/trips/{id}/complete
**Purpose**: Mark entire trip as complete (manual KM entry)

**Request Example**:
```
POST /api/v1/driver/trips/9d8f7a6b-.../complete
Authorization: Bearer {driver_token}
Content-Type: application/json

{
  "actual_km_driven": 14.2
}
```

**Response Example** (200 OK):
```json
{
  "data": {
    "id": "9d8f7a6b-...",
    "status": "completed",
    "started_at": "2026-01-05T10:30:00Z",
    "completed_at": "2026-01-05T12:15:00Z",
    "actual_km_driven": 14.2,
    "estimated_km": 12.5,
    "revenue_recorded": true
  }
}
```

**Processing Flow**:
1. Validate trip belongs to driver
2. Check all destinations are `completed`
3. Update trip: `status = completed`, `completed_at = now()`, `actual_km_driven`
4. **Record revenue in ledger** via `LedgerService::recordTripRevenue()`
5. Update vehicle KM counter
6. Return completed trip

---

## 📐 Detailed Implementation Guide

### Step 1: Form Requests (60 minutes)

#### StartTripRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for starting a trip.
 */
class StartTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        // Driver must own this trip
        return $this->user() !== null && $trip->driver_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // No body parameters needed, but we validate trip state
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $trip = $this->route('trip');

            if ($trip->status !== TripStatus::NotStarted) {
                $validator->errors()->add(
                    'trip',
                    'Trip has already been started or completed.'
                );
            }
        });
    }
}
```

#### CompleteDestinationRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\DestinationStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for completing a destination.
 */
class CompleteDestinationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $destination = $this->route('destination');

        // Driver must own the trip this destination belongs to
        return $this->user() !== null
            && $destination->trip !== null
            && $destination->trip->driver_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signature_url' => ['nullable', 'string', 'url', 'max:500'],
            'photo_url' => ['nullable', 'string', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $destination = $this->route('destination');

            if ($destination->status === DestinationStatus::Completed) {
                $validator->errors()->add(
                    'destination',
                    'Destination has already been completed.'
                );
            }
        });
    }
}
```

---

### Step 2: API Resources (60 minutes)

#### TripResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Trip model (driver view).
 */
class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_request_id' => $this->delivery_request_id,
            'status' => $this->status->value,
            'business_name' => $this->deliveryRequest->business->business_name ?? null,
            'total_destinations' => $this->whenLoaded('destinations', fn() => $this->destinations->count()),
            'completed_destinations' => $this->whenLoaded('destinations', function () {
                return $this->destinations->where('status', \App\Enums\DestinationStatus::Completed)->count();
            }),
            'estimated_km' => $this->deliveryRequest->total_km ? (float) $this->deliveryRequest->total_km : null,
            'actual_km_driven' => $this->actual_km_driven ? (float) $this->actual_km_driven : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Relationships
            'destinations' => DestinationResource::collection($this->whenLoaded('destinations')),
        ];
    }
}
```

#### DestinationResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Destination model (driver view).
 */
class DestinationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'address' => $this->address,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'sequence_order' => $this->sequence_order,
            'status' => $this->status->value,
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'signature_url' => $this->signature_url,
            'photo_url' => $this->photo_url,
            'notes' => $this->notes,

            // Helper for navigation
            'navigation_url' => "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}&travelmode=driving",
        ];
    }
}
```

---

### Step 3: Controllers (240 minutes)

#### DriverTripController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StartTripRequest;
use App\Http\Resources\Api\V1\Driver\TripResource;
use App\Models\Trip;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for driver trip operations.
 */
class DriverTripController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Get today's trips for the authenticated driver.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function today(Request $request): AnonymousResourceCollection
    {
        $driver = $request->user();

        $trips = Trip::with(['destinations' => function ($query) {
            $query->orderBy('sequence_order');
        }, 'deliveryRequest.business'])
            ->where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->orderBy('created_at')
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * Start a trip.
     *
     * @param StartTripRequest $request
     * @param Trip $trip
     * @return TripResource
     */
    public function start(StartTripRequest $request, Trip $trip): TripResource
    {
        $trip->update([
            'status' => TripStatus::InProgress,
            'started_at' => now(),
        ]);

        $trip->load(['destinations' => function ($query) {
            $query->orderBy('sequence_order');
        }, 'deliveryRequest.business']);

        return new TripResource($trip);
    }

    /**
     * Complete a trip (with actual KM driven).
     *
     * @param Request $request
     * @param Trip $trip
     * @return JsonResponse
     */
    public function complete(Request $request, Trip $trip): JsonResponse
    {
        // Validate authorization
        if ($trip->driver_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Validate request
        $validated = $request->validate([
            'actual_km_driven' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        // Check all destinations are completed
        $allCompleted = $trip->destinations()
            ->where('status', '!=', \App\Enums\DestinationStatus::Completed)
            ->doesntExist();

        if (! $allCompleted) {
            return response()->json([
                'message' => 'Cannot complete trip. Some destinations are not completed yet.',
            ], 422);
        }

        // Update trip
        $trip->update([
            'status' => TripStatus::Completed,
            'completed_at' => now(),
            'actual_km_driven' => $validated['actual_km_driven'],
        ]);

        // Record revenue in ledger
        $actualKm = (float) $validated['actual_km_driven'];
        $estimatedCost = (float) $trip->deliveryRequest->estimated_cost;

        // If actual KM differs significantly from estimate, recalculate cost
        // For MVP, we'll use estimated cost as billed amount
        $this->ledgerService->recordTripRevenue($trip, $estimatedCost);

        // Update vehicle KM
        if ($trip->vehicle) {
            $trip->vehicle->updateKm($actualKm);
        }

        $trip->load(['destinations', 'deliveryRequest.business']);

        return (new TripResource($trip))
            ->additional([
                'message' => 'Trip completed successfully. Revenue recorded.',
            ])
            ->response();
    }
}
```

#### DriverDestinationController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DestinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\CompleteDestinationRequest;
use App\Http\Resources\Api\V1\Driver\DestinationResource;
use App\Models\Destination;
use App\Services\Callback\CallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for driver destination operations.
 */
class DriverDestinationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly CallbackService $callbackService,
    ) {
    }

    /**
     * Mark destination as arrived.
     *
     * @param Request $request
     * @param Destination $destination
     * @return DestinationResource
     */
    public function arrive(Request $request, Destination $destination): DestinationResource
    {
        // Validate authorization
        $trip = $destination->trip;
        if (! $trip || $trip->driver_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Validate status
        if ($destination->status !== DestinationStatus::Pending) {
            abort(422, 'Destination is not in pending status.');
        }

        // Update destination
        $destination->update([
            'status' => DestinationStatus::Arrived,
            'arrived_at' => now(),
        ]);

        return new DestinationResource($destination);
    }

    /**
     * Mark destination as completed (triggers callback).
     *
     * @param CompleteDestinationRequest $request
     * @param Destination $destination
     * @return JsonResponse
     */
    public function complete(CompleteDestinationRequest $request, Destination $destination): JsonResponse
    {
        $validated = $request->validated();

        // Update destination
        $destination->update([
            'status' => DestinationStatus::Completed,
            'completed_at' => now(),
            'signature_url' => $validated['signature_url'] ?? null,
            'photo_url' => $validated['photo_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Send callback to client ERP
        try {
            $this->callbackService->sendDeliveryCallback($destination);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            logger()->error('Failed to send callback for destination ' . $destination->id, [
                'error' => $e->getMessage(),
            ]);
        }

        return (new DestinationResource($destination))
            ->additional([
                'message' => 'Delivery completed. Callback sent to ERP.',
            ])
            ->response();
    }

    /**
     * Mark destination as failed.
     *
     * @param Request $request
     * @param Destination $destination
     * @return DestinationResource
     */
    public function fail(Request $request, Destination $destination): DestinationResource
    {
        // Validate authorization
        $trip = $destination->trip;
        if (! $trip || $trip->driver_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Validate request
        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'max:500'],
        ]);

        // Update destination
        $destination->update([
            'status' => DestinationStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => $validated['failure_reason'],
        ]);

        // Send callback to client ERP (with failed status)
        try {
            $this->callbackService->sendDeliveryCallback($destination);
        } catch (\Exception $e) {
            logger()->error('Failed to send callback for failed destination ' . $destination->id, [
                'error' => $e->getMessage(),
            ]);
        }

        return new DestinationResource($destination);
    }
}
```

---

### Step 4: Routes (30 minutes)

Add these routes to `routes/api/v1.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Driver\DriverDestinationController;
use App\Http\Controllers\Api\V1\Driver\DriverTripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1 - Driver Endpoints
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->prefix('driver')->group(function () {
    // Trip operations
    Route::get('trips/today', [DriverTripController::class, 'today']);
    Route::post('trips/{trip}/start', [DriverTripController::class, 'start']);
    Route::post('trips/{trip}/complete', [DriverTripController::class, 'complete']);

    // Destination operations
    Route::post('destinations/{destination}/arrive', [DriverDestinationController::class, 'arrive']);
    Route::post('destinations/{destination}/complete', [DriverDestinationController::class, 'complete']);
    Route::post('destinations/{destination}/fail', [DriverDestinationController::class, 'fail']);
});
```

---

### Step 5: Feature Tests (180 minutes)

#### tests/Feature/Api/V1/Driver/DriverTripApiTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Driver;

use App\Enums\DestinationStatus;
use App\Enums\TripStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Feature tests for Driver Trip API endpoints.
 */
class DriverTripApiTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create driver user
        $this->driver = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create();

        Passport::actingAs($this->driver);
    }

    /**
     * Test: Can get today's trips.
     */
    public function test_can_get_todays_trips(): void
    {
        $business = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->for($business)->create();

        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->for($this->vehicle)
            ->has(Destination::factory()->count(3))
            ->create(['created_at' => now()]);

        // Create trip from yesterday (should not appear)
        Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/driver/trips/today');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('data.0.total_destinations', 3);
    }

    /**
     * Test: Can start a trip.
     */
    public function test_can_start_trip(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->for($this->vehicle)
            ->create(['status' => TripStatus::NotStarted]);

        $response = $this->postJson("/api/v1/driver/trips/{$trip->id}/start");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TripStatus::InProgress->value)
            ->assertJsonPath('data.started_at', fn($value) => $value !== null);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::InProgress,
        ]);
    }

    /**
     * Test: Cannot start trip that's already started.
     */
    public function test_cannot_start_trip_already_started(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->create(['status' => TripStatus::InProgress]);

        $response = $this->postJson("/api/v1/driver/trips/{$trip->id}/start");

        $response->assertStatus(422);
    }

    /**
     * Test: Cannot start trip belonging to another driver.
     */
    public function test_cannot_start_trip_of_another_driver(): void
    {
        $otherDriver = User::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($otherDriver, 'driver')
            ->create(['status' => TripStatus::NotStarted]);

        $response = $this->postJson("/api/v1/driver/trips/{$trip->id}/start");

        $response->assertStatus(403);
    }

    /**
     * Test: Can complete trip with actual KM.
     */
    public function test_can_complete_trip_with_actual_km(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'total_km' => 12.5,
            'estimated_cost' => 6.25,
        ]);

        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->for($this->vehicle)
            ->has(
                Destination::factory()
                    ->count(2)
                    ->state(['status' => DestinationStatus::Completed])
            )
            ->create(['status' => TripStatus::InProgress]);

        $response = $this->postJson("/api/v1/driver/trips/{$trip->id}/complete", [
            'actual_km_driven' => 14.2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TripStatus::Completed->value)
            ->assertJsonPath('data.actual_km_driven', 14.2)
            ->assertJsonPath('message', 'Trip completed successfully. Revenue recorded.');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::Completed,
            'actual_km_driven' => 14.2,
        ]);

        // Verify ledger entry was created
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => Trip::class,
            'reference_id' => $trip->id,
        ]);
    }

    /**
     * Test: Cannot complete trip with incomplete destinations.
     */
    public function test_cannot_complete_trip_with_incomplete_destinations(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->for($this->vehicle)
            ->has(
                Destination::factory()
                    ->count(2)
                    ->state(['status' => DestinationStatus::Pending])
            )
            ->create(['status' => TripStatus::InProgress]);

        $response = $this->postJson("/api/v1/driver/trips/{$trip->id}/complete", [
            'actual_km_driven' => 10.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot complete trip. Some destinations are not completed yet.');
    }

    /**
     * Test: Trip completion updates vehicle KM.
     */
    public function test_trip_completion_updates_vehicle_km(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'estimated_cost' => 5.0,
        ]);

        $vehicle = Vehicle::factory()->create([
            'total_km_driven' => 1000.0,
            'monthly_km_app' => 50.0,
        ]);

        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->for($vehicle)
            ->has(
                Destination::factory()
                    ->count(1)
                    ->state(['status' => DestinationStatus::Completed])
            )
            ->create(['status' => TripStatus::InProgress]);

        $this->postJson("/api/v1/driver/trips/{$trip->id}/complete", [
            'actual_km_driven' => 15.5,
        ]);

        $vehicle->refresh();

        $this->assertEquals(1015.5, (float) $vehicle->total_km_driven);
        $this->assertEquals(65.5, (float) $vehicle->monthly_km_app);
    }
}
```

#### tests/Feature/Api/V1/Driver/DriverDestinationApiTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Driver;

use App\Enums\DestinationStatus;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use App\Services\Callback\CallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Feature tests for Driver Destination API endpoints.
 */
class DriverDestinationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::factory()->create();

        $deliveryRequest = DeliveryRequest::factory()->create();
        $this->trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($this->driver, 'driver')
            ->create();

        Passport::actingAs($this->driver);
    }

    /**
     * Test: Can mark destination as arrived.
     */
    public function test_can_mark_destination_as_arrived(): void
    {
        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Pending,
                'trip_id' => $this->trip->id,
            ]);

        $response = $this->postJson("/api/v1/driver/destinations/{$destination->id}/arrive");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DestinationStatus::Arrived->value)
            ->assertJsonPath('data.arrived_at', fn($value) => $value !== null);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'status' => DestinationStatus::Arrived,
        ]);
    }

    /**
     * Test: Cannot mark already arrived destination as arrived again.
     */
    public function test_cannot_mark_already_arrived_destination(): void
    {
        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Arrived,
                'trip_id' => $this->trip->id,
            ]);

        $response = $this->postJson("/api/v1/driver/destinations/{$destination->id}/arrive");

        $response->assertStatus(422);
    }

    /**
     * Test: Can complete destination with optional fields.
     */
    public function test_can_complete_destination_with_optional_fields(): void
    {
        Http::fake(); // Mock callback HTTP request

        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Arrived,
                'trip_id' => $this->trip->id,
            ]);

        $response = $this->postJson("/api/v1/driver/destinations/{$destination->id}/complete", [
            'signature_url' => 'https://example.com/signature.png',
            'photo_url' => 'https://example.com/photo.jpg',
            'notes' => 'Delivered to receptionist',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DestinationStatus::Completed->value)
            ->assertJsonPath('data.signature_url', 'https://example.com/signature.png')
            ->assertJsonPath('message', 'Delivery completed. Callback sent to ERP.');

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'status' => DestinationStatus::Completed,
            'signature_url' => 'https://example.com/signature.png',
            'notes' => 'Delivered to receptionist',
        ]);
    }

    /**
     * Test: Completing destination triggers ERP callback.
     */
    public function test_completing_destination_triggers_callback(): void
    {
        $business = $this->trip->deliveryRequest->business;
        $business->update([
            'callback_url' => 'https://erp.example.com/api/delivery-status',
            'callback_api_key' => 'test_api_key_123',
        ]);

        Http::fake([
            'erp.example.com/*' => Http::response(['status' => 'received'], 200),
        ]);

        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Arrived,
                'trip_id' => $this->trip->id,
                'external_id' => 'ORDER-123',
            ]);

        $this->postJson("/api/v1/driver/destinations/{$destination->id}/complete");

        // Assert HTTP request was made to ERP
        Http::assertSent(function ($request) {
            return $request->url() === 'https://erp.example.com/api/delivery-status'
                && $request->hasHeader('Authorization', 'Bearer test_api_key_123');
        });
    }

    /**
     * Test: Can mark destination as failed with reason.
     */
    public function test_can_mark_destination_as_failed(): void
    {
        Http::fake();

        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Pending,
                'trip_id' => $this->trip->id,
            ]);

        $response = $this->postJson("/api/v1/driver/destinations/{$destination->id}/fail", [
            'failure_reason' => 'Customer not available',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DestinationStatus::Failed->value);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'status' => DestinationStatus::Failed,
            'failure_reason' => 'Customer not available',
        ]);
    }

    /**
     * Test: Cannot complete destination belonging to another driver.
     */
    public function test_cannot_complete_destination_of_another_driver(): void
    {
        $otherDriver = User::factory()->create();
        $otherTrip = Trip::factory()
            ->for($this->trip->deliveryRequest)
            ->for($otherDriver, 'driver')
            ->create();

        $destination = Destination::factory()
            ->for($this->trip->deliveryRequest)
            ->create([
                'status' => DestinationStatus::Arrived,
                'trip_id' => $otherTrip->id,
            ]);

        $response = $this->postJson("/api/v1/driver/destinations/{$destination->id}/complete");

        $response->assertStatus(403);
    }
}
```

---

## ✅ Success Criteria

By end of day, you must have:

### Code Deliverables
- ✅ 2 Form Requests (StartTrip, CompleteDestination)
- ✅ 2 API Resources (Trip, Destination)
- ✅ 2 Controllers (DriverTripController, DriverDestinationController)
- ✅ 6 Routes added to routes/api/v1.php
- ✅ 2 comprehensive Feature Test files

### Functionality Working
- ✅ GET /api/v1/driver/trips/today - Shows today's trips
- ✅ POST /api/v1/driver/trips/{id}/start - Starts trip
- ✅ POST /api/v1/driver/trips/{id}/complete - Completes trip + records revenue
- ✅ POST /api/v1/driver/destinations/{id}/arrive - Marks arrived
- ✅ POST /api/v1/driver/destinations/{id}/complete - Completes + sends callback
- ✅ POST /api/v1/driver/destinations/{id}/fail - Marks failed
- ✅ Integration with CallbackService (ERP notifications)
- ✅ Integration with LedgerService (revenue recording)
- ✅ Vehicle KM tracking updates

### Testing & Quality
- ✅ All 13+ feature tests passing
- ✅ `php artisan test --filter=Driver` - 100% pass rate
- ✅ PSR-12 compliant code
- ✅ Full PHPDoc coverage
- ✅ Strict type hints on all methods
- ✅ No debug statements

---

## 🚫 What NOT to Do

**Do NOT**:
- ❌ Modify files owned by other developers (Business API, Admin Panel)
- ❌ Create business-facing endpoints (that's Developer 1's job)
- ❌ Create Filament resources (that's Developer 3's job)
- ❌ Skip callback integration
- ❌ Skip ledger integration
- ❌ Forget authorization checks (driver ownership validation)

---

## 🧪 Testing Commands

```bash
# Run your tests only
php artisan test --filter=Driver

# Test specific file
php artisan test tests/Feature/Api/V1/Driver/DriverTripApiTest.php

# View your routes
php artisan route:list --path=driver
```

---

## 💡 Implementation Tips

1. **Start with Resources** - Define how data appears to drivers
2. **Mock HTTP in tests** - Use `Http::fake()` for callback tests
3. **Test authorization** - Drivers can only access their own trips
4. **Use services** - CallbackService, LedgerService already built
5. **Handle errors gracefully** - Callback failures shouldn't break completions
6. **Follow patterns** - Look at Business API controller for reference

---

**Good luck! You own the Driver API. Make it solid! 🚀**

**Remember**: Your Scrum Master is here to help. Ask questions early!

---

**Task File Created**: 2026-01-05 @ 00:00 UTC

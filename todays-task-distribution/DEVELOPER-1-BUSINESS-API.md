# Developer 1: Module A - Business & ERP Integration API

**Date**: 2026-01-05
**Phase**: Phase 3 - API Endpoints (Business API)
**Estimated Time**: 6-8 hours
**Priority**: HIGH (Critical business integration)

---

## 🎯 Mission

Build the complete API layer for **Business & ERP Integration**. This allows client businesses (like the Sweets Factory ERP) to submit delivery requests programmatically, receive optimized routes, get cost estimates, and track their delivery requests.

**Key Focus**: You own the ENTIRE business-facing API. No dependencies on other developers.

---

## 📋 Your Module Ownership

### Files You Will Create (Complete Ownership)

```
routes/api/v1.php (add your routes only)
app/Http/Controllers/Api/V1/DeliveryRequestController.php
app/Http/Requests/Api/V1/StoreDeliveryRequestRequest.php
app/Http/Requests/Api/V1/UpdateDeliveryRequestRequest.php
app/Http/Resources/Api/V1/DeliveryRequestResource.php
app/Http/Resources/Api/V1/DestinationResource.php
tests/Feature/Api/V1/DeliveryRequestApiTest.php
```

### Services You Will Use (Already Built)

- ✅ `RouteOptimizer` - Google Maps route optimization
- ✅ `DistanceCalculator` - Distance matrix calculations
- ✅ `CostCalculator` - Price per KM calculations
- ✅ `SchemaTransformer` - Dynamic payload mapping

**No Integration Needed**: Other developers are building different endpoints. You work completely independently.

---

## 🗂️ API Endpoints You Will Build

### 1. POST /api/v1/delivery-requests
**Purpose**: Create a new delivery request with route optimization

**Request Example**:
```json
{
  "destinations": [
    {
      "external_id": "ORDER-123",
      "address": "Rainbow St, Amman",
      "lat": 31.9539,
      "lng": 35.9106
    },
    {
      "external_id": "ORDER-124",
      "address": "Abdali Mall, Amman",
      "lat": 31.9730,
      "lng": 35.9087
    },
    {
      "external_id": "ORDER-125",
      "address": "Swefieh, Amman",
      "lat": 31.9332,
      "lng": 35.8621
    }
  ]
}
```

**Response Example** (201 Created):
```json
{
  "data": {
    "id": "9d8f7a6b-5c4d-3e2f-1a0b-9c8d7e6f5a4b",
    "business_id": "9d8f7a6b-...",
    "status": "pending",
    "total_km": 12.5,
    "estimated_cost": 6.25,
    "optimized_route": "encoded_polyline_string...",
    "created_at": "2026-01-05T10:30:00Z",
    "destinations": [
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-124",
        "address": "Abdali Mall, Amman",
        "lat": 31.9730,
        "lng": 35.9087,
        "sequence_order": 1,
        "status": "pending"
      },
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-123",
        "address": "Rainbow St, Amman",
        "lat": 31.9539,
        "lng": 35.9106,
        "sequence_order": 2,
        "status": "pending"
      },
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-125",
        "address": "Swefieh, Amman",
        "lat": 31.9332,
        "lng": 35.8621,
        "sequence_order": 3,
        "status": "pending"
      }
    ]
  }
}
```

**Processing Flow**:
1. Validate request (StoreDeliveryRequestRequest)
2. Get authenticated business (`$request->user()->business`)
3. Use `RouteOptimizer` to get optimized order + total distance
4. Use `CostCalculator` to calculate estimated cost
5. Create `DeliveryRequest` record
6. Create `Destination` records with optimized sequence_order
7. Return response with `DeliveryRequestResource`

---

### 2. GET /api/v1/delivery-requests
**Purpose**: List all delivery requests for authenticated business (paginated)

**Query Parameters**:
- `page` (default: 1)
- `per_page` (default: 15, max: 100)
- `status` (optional filter: pending, accepted, in_progress, completed, cancelled)
- `from_date` (optional filter: YYYY-MM-DD)
- `to_date` (optional filter: YYYY-MM-DD)

**Request Example**:
```
GET /api/v1/delivery-requests?status=pending&per_page=20
```

**Response Example** (200 OK):
```json
{
  "data": [
    {
      "id": "9d8f7a6b-...",
      "status": "pending",
      "total_km": 12.5,
      "estimated_cost": 6.25,
      "destinations_count": 3,
      "created_at": "2026-01-05T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

**Processing Flow**:
1. Get authenticated business
2. Query `DeliveryRequest::where('business_id', $business->id)`
3. Apply optional filters (status, date range)
4. Paginate results
5. Return with `DeliveryRequestResource::collection()`

---

### 3. GET /api/v1/delivery-requests/{id}
**Purpose**: Get details of a specific delivery request (with all destinations)

**Request Example**:
```
GET /api/v1/delivery-requests/9d8f7a6b-5c4d-3e2f-1a0b-9c8d7e6f5a4b
```

**Response Example** (200 OK):
```json
{
  "data": {
    "id": "9d8f7a6b-...",
    "business_id": "9d8f7a6b-...",
    "status": "in_progress",
    "total_km": 12.5,
    "estimated_cost": 6.25,
    "optimized_route": "encoded_polyline_string...",
    "created_at": "2026-01-05T10:30:00Z",
    "updated_at": "2026-01-05T11:00:00Z",
    "destinations": [
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-124",
        "address": "Abdali Mall, Amman",
        "lat": 31.9730,
        "lng": 35.9087,
        "sequence_order": 1,
        "status": "completed",
        "arrived_at": "2026-01-05T10:45:00Z",
        "completed_at": "2026-01-05T10:50:00Z"
      },
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-123",
        "address": "Rainbow St, Amman",
        "lat": 31.9539,
        "lng": 35.9106,
        "sequence_order": 2,
        "status": "arrived",
        "arrived_at": "2026-01-05T11:00:00Z",
        "completed_at": null
      },
      {
        "id": "9d8f7a6b-...",
        "external_id": "ORDER-125",
        "address": "Swefieh, Amman",
        "lat": 31.9332,
        "lng": 35.8621,
        "sequence_order": 3,
        "status": "pending",
        "arrived_at": null,
        "completed_at": null
      }
    ]
  }
}
```

**Processing Flow**:
1. Get authenticated business
2. Find delivery request: `DeliveryRequest::with('destinations')->findOrFail($id)`
3. Check ownership: `$deliveryRequest->business_id === $business->id` (authorization)
4. Return with `DeliveryRequestResource`

---

## 📐 Detailed Implementation Guide

### Step 1: Form Request Validation (90 minutes)

#### StoreDeliveryRequestRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating delivery requests.
 */
class StoreDeliveryRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->business !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destinations' => ['required', 'array', 'min:1', 'max:25'],
            'destinations.*.external_id' => ['required', 'string', 'max:255'],
            'destinations.*.address' => ['required', 'string', 'max:500'],
            'destinations.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'destinations.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destinations.required' => 'At least one destination is required.',
            'destinations.min' => 'At least one destination is required.',
            'destinations.max' => 'Maximum 25 destinations allowed per request.',
            'destinations.*.external_id.required' => 'Each destination must have an external_id.',
            'destinations.*.address.required' => 'Each destination must have an address.',
            'destinations.*.lat.required' => 'Each destination must have latitude coordinates.',
            'destinations.*.lng.required' => 'Each destination must have longitude coordinates.',
            'destinations.*.lat.between' => 'Latitude must be between -90 and 90.',
            'destinations.*.lng.between' => 'Longitude must be between -180 and 180.',
        ];
    }
}
```

#### UpdateDeliveryRequestRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\DeliveryRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating delivery requests.
 */
class UpdateDeliveryRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $deliveryRequest = $this->route('delivery_request');

        return $this->user() !== null
            && $this->user()->business !== null
            && $deliveryRequest->business_id === $this->user()->business->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'required',
                Rule::enum(DeliveryRequestStatus::class),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required when updating.',
        ];
    }
}
```

---

### Step 2: API Resources (60 minutes)

#### DeliveryRequestResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for DeliveryRequest model.
 */
class DeliveryRequestResource extends JsonResource
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
            'business_id' => $this->business_id,
            'status' => $this->status->value,
            'total_km' => $this->total_km ? (float) $this->total_km : null,
            'estimated_cost' => $this->estimated_cost ? (float) $this->estimated_cost : null,
            'optimized_route' => $this->optimized_route,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'destinations' => DestinationResource::collection($this->whenLoaded('destinations')),

            // Computed attributes (only in list view)
            'destinations_count' => $this->when(
                ! $this->relationLoaded('destinations'),
                fn() => $this->destinations()->count()
            ),
        ];
    }
}
```

#### DestinationResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Destination model.
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
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
        ];
    }
}
```

---

### Step 3: Controller Implementation (180 minutes)

#### DeliveryRequestController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DestinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeliveryRequestRequest;
use App\Http\Requests\Api\V1\UpdateDeliveryRequestRequest;
use App\Http\Resources\Api\V1\DeliveryRequestResource;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Services\GoogleMaps\RouteOptimizer;
use App\Services\Pricing\CostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Controller for managing delivery requests via API.
 */
class DeliveryRequestController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly RouteOptimizer $routeOptimizer,
        private readonly CostCalculator $costCalculator,
    ) {
    }

    /**
     * Display a listing of delivery requests for the authenticated business.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $business = $request->user()->business;

        $query = DeliveryRequest::where('business_id', $business->id)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        $deliveryRequests = $query->paginate($perPage);

        return DeliveryRequestResource::collection($deliveryRequests);
    }

    /**
     * Store a newly created delivery request.
     *
     * @param StoreDeliveryRequestRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(StoreDeliveryRequestRequest $request): JsonResponse
    {
        $business = $request->user()->business;
        $destinationsData = $request->input('destinations');

        // Step 1: Optimize route using Google Maps
        $optimizationResult = $this->routeOptimizer->optimize($destinationsData);

        // Step 2: Calculate total KM and cost
        $totalKm = $optimizationResult['total_distance_meters'] / 1000;

        // Step 3: Create delivery request and destinations in transaction
        $deliveryRequest = DB::transaction(function () use ($business, $totalKm, $optimizationResult, $destinationsData) {
            // Create delivery request
            $deliveryRequest = DeliveryRequest::create([
                'business_id' => $business->id,
                'total_km' => $totalKm,
                'estimated_cost' => $this->costCalculator->calculateCost($totalKm, $business),
                'optimized_route' => $optimizationResult['polyline'] ?? null,
            ]);

            // Create destinations with optimized sequence order
            foreach ($optimizationResult['optimized_order'] as $newIndex => $originalIndex) {
                $destination = $destinationsData[$originalIndex];

                Destination::create([
                    'delivery_request_id' => $deliveryRequest->id,
                    'external_id' => $destination['external_id'],
                    'address' => $destination['address'],
                    'lat' => $destination['lat'],
                    'lng' => $destination['lng'],
                    'sequence_order' => $newIndex + 1,
                    'status' => DestinationStatus::Pending,
                ]);
            }

            return $deliveryRequest;
        });

        // Load relationships for response
        $deliveryRequest->load('destinations');

        return (new DeliveryRequestResource($deliveryRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified delivery request.
     *
     * @param Request $request
     * @param string $id
     * @return DeliveryRequestResource
     */
    public function show(Request $request, string $id): DeliveryRequestResource
    {
        $business = $request->user()->business;

        $deliveryRequest = DeliveryRequest::with('destinations')
            ->where('business_id', $business->id)
            ->findOrFail($id);

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Update the specified delivery request.
     *
     * @param UpdateDeliveryRequestRequest $request
     * @param DeliveryRequest $deliveryRequest
     * @return DeliveryRequestResource
     */
    public function update(UpdateDeliveryRequestRequest $request, DeliveryRequest $deliveryRequest): DeliveryRequestResource
    {
        $deliveryRequest->update($request->validated());

        $deliveryRequest->load('destinations');

        return new DeliveryRequestResource($deliveryRequest);
    }
}
```

---

### Step 4: Routes (30 minutes)

Add these routes to `routes/api/v1.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DeliveryRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

// Business API - Delivery Requests
Route::middleware(['auth:api'])->group(function () {
    Route::apiResource('delivery-requests', DeliveryRequestController::class);
});
```

---

### Step 5: Feature Tests (180 minutes)

#### tests/Feature/Api/V1/DeliveryRequestApiTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessType;
use App\Enums\DeliveryRequestStatus;
use App\Enums\DestinationStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\User;
use App\Services\GoogleMaps\RouteOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Feature tests for Delivery Request API endpoints.
 */
class DeliveryRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and business
        $this->business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Authenticate user via Passport
        Passport::actingAs($this->user);
    }

    /**
     * Test: Can create delivery request with valid destinations.
     */
    public function test_can_create_delivery_request_with_valid_destinations(): void
    {
        // Mock Google Maps API response
        Http::fake([
            'maps.googleapis.com/maps/api/directions/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'overview_polyline' => ['points' => 'encoded_polyline_string'],
                        'legs' => [
                            ['distance' => ['value' => 5000]],
                            ['distance' => ['value' => 7500]],
                        ],
                        'waypoint_order' => [1, 0],
                    ],
                ],
            ], 200),
        ]);

        $destinationsData = [
            [
                'external_id' => 'ORDER-123',
                'address' => 'Rainbow St, Amman',
                'lat' => 31.9539,
                'lng' => 35.9106,
            ],
            [
                'external_id' => 'ORDER-124',
                'address' => 'Abdali Mall, Amman',
                'lat' => 31.9730,
                'lng' => 35.9087,
            ],
        ];

        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => $destinationsData,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'business_id',
                    'status',
                    'total_km',
                    'estimated_cost',
                    'optimized_route',
                    'created_at',
                    'destinations' => [
                        '*' => [
                            'id',
                            'external_id',
                            'address',
                            'lat',
                            'lng',
                            'sequence_order',
                            'status',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.status', DeliveryRequestStatus::Pending->value)
            ->assertJsonPath('data.business_id', $this->business->id);

        // Assert database records created
        $this->assertDatabaseHas('delivery_requests', [
            'business_id' => $this->business->id,
            'status' => DeliveryRequestStatus::Pending,
        ]);

        $this->assertDatabaseCount('destinations', 2);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'ORDER-123',
            'status' => DestinationStatus::Pending,
        ]);
    }

    /**
     * Test: Cannot create delivery request without destinations.
     */
    public function test_cannot_create_delivery_request_without_destinations(): void
    {
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinations']);
    }

    /**
     * Test: Cannot create delivery request with invalid coordinates.
     */
    public function test_cannot_create_delivery_request_with_invalid_coordinates(): void
    {
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-123',
                    'address' => 'Test Address',
                    'lat' => 200, // Invalid latitude
                    'lng' => 35.9106,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinations.0.lat']);
    }

    /**
     * Test: Can list delivery requests for authenticated business.
     */
    public function test_can_list_delivery_requests_for_authenticated_business(): void
    {
        // Create delivery requests for this business
        DeliveryRequest::factory()
            ->count(3)
            ->for($this->business)
            ->create();

        // Create delivery request for another business (should not appear)
        $otherBusiness = Business::factory()->create();
        DeliveryRequest::factory()
            ->for($otherBusiness)
            ->create();

        $response = $this->getJson('/api/v1/delivery-requests');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'status', 'total_km', 'estimated_cost', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /**
     * Test: Can filter delivery requests by status.
     */
    public function test_can_filter_delivery_requests_by_status(): void
    {
        DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Completed]);

        DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Completed]);

        $response = $this->getJson('/api/v1/delivery-requests?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test: Can show specific delivery request with destinations.
     */
    public function test_can_show_specific_delivery_request_with_destinations(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->has(Destination::factory()->count(3))
            ->create();

        $response = $this->getJson("/api/v1/delivery-requests/{$deliveryRequest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $deliveryRequest->id)
            ->assertJsonCount(3, 'data.destinations');
    }

    /**
     * Test: Cannot view delivery request from other business.
     */
    public function test_cannot_view_delivery_request_from_other_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()
            ->for($otherBusiness)
            ->create();

        $response = $this->getJson("/api/v1/delivery-requests/{$deliveryRequest->id}");

        $response->assertStatus(404);
    }

    /**
     * Test: Can update delivery request status.
     */
    public function test_can_update_delivery_request_status(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        $response = $this->patchJson("/api/v1/delivery-requests/{$deliveryRequest->id}", [
            'status' => DeliveryRequestStatus::Accepted->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DeliveryRequestStatus::Accepted->value);

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => DeliveryRequestStatus::Accepted,
        ]);
    }

    /**
     * Test: Unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        Passport::actingAs(null);

        $response = $this->getJson('/api/v1/delivery-requests');

        $response->assertStatus(401);
    }

    /**
     * Test: Pagination works correctly.
     */
    public function test_pagination_works_correctly(): void
    {
        DeliveryRequest::factory()
            ->count(25)
            ->for($this->business)
            ->create();

        $response = $this->getJson('/api/v1/delivery-requests?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);
    }

    /**
     * Test: Route optimization creates destinations in correct order.
     */
    public function test_route_optimization_creates_destinations_in_correct_order(): void
    {
        Http::fake([
            'maps.googleapis.com/maps/api/directions/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'overview_polyline' => ['points' => 'test_polyline'],
                        'legs' => [
                            ['distance' => ['value' => 3000]],
                            ['distance' => ['value' => 4000]],
                            ['distance' => ['value' => 5000]],
                        ],
                        'waypoint_order' => [2, 0, 1], // Google says: visit dest 2, then 0, then 1
                    ],
                ],
            ], 200),
        ]);

        $destinationsData = [
            ['external_id' => 'A', 'address' => 'Address A', 'lat' => 31.9539, 'lng' => 35.9106],
            ['external_id' => 'B', 'address' => 'Address B', 'lat' => 31.9730, 'lng' => 35.9087],
            ['external_id' => 'C', 'address' => 'Address C', 'lat' => 31.9332, 'lng' => 35.8621],
        ];

        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => $destinationsData,
        ]);

        $response->assertStatus(201);

        // Verify destinations are stored in optimized order
        $destinations = Destination::where('delivery_request_id', $response->json('data.id'))
            ->orderBy('sequence_order')
            ->get();

        $this->assertEquals('C', $destinations[0]->external_id);
        $this->assertEquals(1, $destinations[0]->sequence_order);

        $this->assertEquals('A', $destinations[1]->external_id);
        $this->assertEquals(2, $destinations[1]->sequence_order);

        $this->assertEquals('B', $destinations[2]->external_id);
        $this->assertEquals(3, $destinations[2]->sequence_order);
    }
}
```

---

## ✅ Success Criteria

By end of day, you must have:

### Code Deliverables
- ✅ 2 Form Requests (Store, Update) with full validation
- ✅ 2 API Resources (DeliveryRequest, Destination)
- ✅ 1 Controller with 4 methods (index, store, show, update)
- ✅ 4 Routes added to routes/api/v1.php
- ✅ 1 comprehensive Feature Test file

### Functionality Working
- ✅ POST /api/v1/delivery-requests creates optimized delivery requests
- ✅ GET /api/v1/delivery-requests lists requests (with filters)
- ✅ GET /api/v1/delivery-requests/{id} shows details
- ✅ PATCH /api/v1/delivery-requests/{id} updates status
- ✅ Route optimization via RouteOptimizer service
- ✅ Cost calculation via CostCalculator service
- ✅ Authorization (users can only see their business's requests)

### Testing & Quality
- ✅ All 11+ feature tests passing
- ✅ `php artisan test --filter=DeliveryRequestApiTest` - 100% pass rate
- ✅ PSR-12 compliant code
- ✅ Full PHPDoc coverage
- ✅ Strict type hints on all methods
- ✅ No debug statements (dd, var_dump, etc.)

---

## 🚫 What NOT to Do

**Do NOT**:
- ❌ Modify files owned by other developers (Driver API, Admin Panel)
- ❌ Create driver-related endpoints (that's Developer 2's job)
- ❌ Create Filament resources (that's Developer 3's job)
- ❌ Skip writing tests
- ❌ Hardcode values (use services and config)
- ❌ Use facades in services (use dependency injection)
- ❌ Skip authorization checks
- ❌ Return models directly (always use Resources)

---

## 🧪 Testing Commands

```bash
# Run your tests only
php artisan test --filter=DeliveryRequestApiTest

# Run with coverage
php artisan test --filter=DeliveryRequestApiTest --coverage

# Test specific scenario
php artisan test --filter=test_can_create_delivery_request_with_valid_destinations

# Check code style
./vendor/bin/phpcs app/Http/Controllers/Api/V1/DeliveryRequestController.php

# View routes you created
php artisan route:list --path=delivery-requests
```

---

## 💡 Implementation Tips

1. **Start with Form Requests** - Get validation right first
2. **Mock HTTP in tests** - Use `Http::fake()` for Google Maps
3. **Use transactions** - Wrap multi-step operations in `DB::transaction()`
4. **Test authorization** - Ensure businesses can't see each other's data
5. **Follow existing patterns** - Look at `AuthController` for reference
6. **Ask for help** - Scrum Master is available if you get stuck

---

## 📚 Reference Code

### Existing Patterns to Follow

**Controller Pattern**: `app/Http/Controllers/Api/V1/Auth/AuthController.php`
**Resource Pattern**: `app/Http/Resources/Api/V1/AuthTokenResource.php`
**Test Pattern**: `tests/Feature/Api/V1/Auth/AuthApiTest.php`

### Services You Will Use

```php
// RouteOptimizer usage
$result = $this->routeOptimizer->optimize($destinationsArray);
// Returns: ['optimized_order' => [...], 'total_distance_meters' => 12500, 'polyline' => '...']

// CostCalculator usage
$cost = $this->costCalculator->calculateCost($totalKm, $business);
// Returns: float (e.g., 6.25)
```

---

## 🎯 Workflow Recommendation

**Morning (2 hours)**:
1. Create Form Requests (Store, Update)
2. Create API Resources (DeliveryRequest, Destination)
3. Test validation rules work

**Midday (3 hours)**:
4. Implement Controller (index, store, show, update)
5. Add routes
6. Manual testing via Postman/curl

**Afternoon (3 hours)**:
7. Write feature tests (all 11 scenarios)
8. Fix any failing tests
9. Run full test suite
10. Verify code quality (PSR-12, PHPDoc, types)

---

**Good luck! You own the Business API. Make it rock-solid! 🚀**

**Remember**: Your Scrum Master is here to help. Don't struggle alone - ask questions early!

---

**Task File Created**: 2026-01-05 @ 00:00 UTC

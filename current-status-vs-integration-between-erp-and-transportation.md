# Transportation MVP - Integration Status & Task List

> **Your Role**: You are the Transportation development team. This document outlines what you need to build to integrate with the Melo ERP system.

---

## 1. Your Project Status (Transportation MVP)

### What Exists ✅

- **Database Schema** (8 tables ready):
  - `businesses` - ERP clients configuration
  - `vehicles` - Fleet management
  - `drivers` - Personnel with user accounts
  - `delivery_requests` - Delivery batches from clients
  - `destinations` - Individual delivery stops
  - `trips` - Driver assignments and execution
  - `pricing_tiers` - Cost calculation rules
  - `business_payload_schemas` - Dynamic API field mapping

- **Eloquent Models** (9 models):
  - Business, Vehicle, Driver, DeliveryRequest, Destination, Trip, PricingTier, BusinessPayloadSchema, User
  - All with relationships, scopes, and methods

- **Enums** (6 status enums):
  - BusinessType, DeliveryRequestStatus, DestinationStatus, TripStatus, FailureReason, LedgerAccountType

- **Authentication**:
  - Laravel Passport OAuth2 configured
  - Token-based authentication ready

- **Flutter Driver App** (85% UI complete):
  - Login screen (functional)
  - Trips list screen (UI ready, using mock data)
  - Trip details screen (UI ready, using mock data)
  - Arabic/English localization
  - Riverpod state management
  - Secure token storage
  - API client with auto-refresh

### What's Missing ❌

- API Controllers (only AuthController exists)
- API endpoints for delivery requests
- Driver trip management endpoints
- Route optimization service (Google Maps)
- Cost calculation service
- Callback mechanism to notify ERP
- Flutter API integration (replace mock data)
- GPS tracking service
- Admin panel Filament resources

### Current Completion

**Backend**: ~30% complete (infrastructure ready, features missing)
**Flutter UI**: ~85% complete (screens ready, need API integration)
**Integration**: 0% (no endpoints implemented)

---

## 2. ERP Project Status

### What Exists in ERP ✅

- Order system with Order and OrderItem models
- Shop model with GPS coordinates (lat/lng fields)
- Order.external_delivery_id field (to store your delivery request ID)
- OrderStatus enum with lifecycle management (9 statuses)
- API infrastructure (Laravel + Passport OAuth2)
- 389 passing tests for order and production systems
- WhatsApp Business API integration (for order intake)

### What's Missing in ERP ❌

- Environment configuration for Transportation API
- Service to submit delivery requests to you
- API endpoint to receive delivery completion callbacks from you
- Order status transitions for delivery (out_for_delivery, delivered)
- Invoice generation after delivery confirmation

### What ERP Will Send You

When orders are ready for delivery, ERP will call your API with:

```json
POST /api/v1/delivery-requests
Authorization: Bearer {business_api_key}

{
  "destinations": [
    {
      "external_id": "ORD-20260104-001",
      "address": "Shop address in Amman",
      "lat": 31.9539,
      "lng": 35.9106
    },
    {
      "external_id": "ORD-20260104-002",
      "address": "Another shop address",
      "lat": 31.9600,
      "lng": 35.9200
    }
  ]
}
```

---

## 3. What You Need to Implement

### Task 1: Create DeliveryRequest API Endpoint 🔴 CRITICAL

**File**: `/backend/app/Http/Controllers/Api/V1/DeliveryRequestController.php` (new)

**Description**: Build API endpoint to receive delivery requests from ERP

**What to Build**:
1. Create `DeliveryRequestController` with `store()` method
2. Accept authenticated request from Business (via API key)
3. Create `DeliveryRequest` record
4. Create `Destination` records for each stop
5. Use `BusinessPayloadSchema` to transform incoming field names
6. Return delivery request ID, estimated cost, total KM

**API Contract**:
```php
POST /api/v1/delivery-requests
Authorization: Bearer {business.api_key}

Request:
{
  "destinations": [
    {
      "external_id": "order-123",    // ERP's order ID
      "address": "123 Main St",
      "lat": 31.9539,
      "lng": 35.9106
    }
  ]
}

Response: 201 Created
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "total_km": 25.5,
    "estimated_cost": 12.75,
    "status": "pending",
    "destinations_count": 2
  }
}
```

**Implementation Hints**:
- Authenticate using Business.api_key
- Extract business_id from authenticated token
- Use `BusinessPayloadSchema::getFromRequest()` to map incoming fields
- Initially return placeholder values for total_km and estimated_cost (will integrate services later)

**Dependencies**: None (models already exist)

**Priority**: HIGH - This is the entry point for ERP integration

---

### Task 2: Create Form Validation

**File**: `/backend/app/Http/Requests/Api/V1/StoreDeliveryRequestRequest.php` (new)

**Description**: Validate incoming delivery requests

**What to Build**:
```php
public function rules(): array
{
    return [
        'destinations' => 'required|array|min:1',
        'destinations.*.external_id' => 'required|string|max:255',
        'destinations.*.address' => 'required|string',
        'destinations.*.lat' => 'required|numeric|between:-90,90',
        'destinations.*.lng' => 'required|numeric|between:-180,180',
        'destinations.*.notes' => 'nullable|string',
    ];
}
```

**Additional Validation**:
- Check that authenticated business is active
- Prevent duplicate external_id within same request

**Dependencies**: Task 1

---

### Task 3: Create JSON Response Resource

**File**: `/backend/app/Http/Resources/Api/V1/DeliveryRequestResource.php` (new)

**Description**: Transform DeliveryRequest model to JSON response

**What to Build**:
```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status->value,
        'total_km' => $this->total_km,
        'estimated_cost' => $this->estimated_cost,
        'actual_km' => $this->actual_km,
        'actual_cost' => $this->actual_cost,
        'destinations_count' => $this->destinations->count(),
        'optimized_route' => $this->optimized_route,
        'created_at' => $this->created_at,
    ];
}
```

**Dependencies**: Task 1

---

### Task 4: Create Route Optimizer Service

**File**: `/backend/app/Services/GoogleMaps/RouteOptimizerService.php` (new)

**Description**: Integrate Google Maps Directions API for route optimization

**What to Build**:
1. `optimize(DeliveryRequest $deliveryRequest)` method
2. Call Google Directions API with waypoints
3. Use `optimize:true` parameter for waypoint ordering
4. Parse response to extract:
   - Optimized waypoint order
   - Total distance in meters (convert to KM)
   - Route polyline (for map display)
5. Update `Destination.sequence_order` based on optimized order
6. Store `total_km` and `optimized_route` in `DeliveryRequest`

**Google Maps API Usage**:
```php
$waypoints = $deliveryRequest->destinations
    ->map(fn($d) => "{$d->lat},{$d->lng}")
    ->implode('|');

$response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
    'origin' => config('app.warehouse_location'),  // Starting point
    'destination' => config('app.warehouse_location'),  // Round trip
    'waypoints' => "optimize:true|{$waypoints}",
    'key' => config('services.google_maps.api_key'),
]);

// Parse waypoint_order from response
$optimizedOrder = $response['routes'][0]['waypoint_order'];

// Calculate total distance
$totalMeters = collect($response['routes'][0]['legs'])
    ->sum(fn($leg) => $leg['distance']['value']);
$totalKm = $totalMeters / 1000;
```

**Configuration Needed**:
- Add `GOOGLE_MAPS_API_KEY` to `.env`
- Add warehouse location (starting point) to config

**Cost Optimization**:
- This API call happens ONCE per delivery request
- Cost: ~$5 per 1,000 requests
- Driver navigation uses FREE external Google Maps app

**Dependencies**: None (can implement independently)

---

### Task 5: Create Pricing Service

**File**: `/backend/app/Services/Pricing/PricingService.php` (new)

**Description**: Calculate delivery cost based on distance and pricing tiers

**What to Build**:
```php
public function calculateCost(float $totalKm, string $businessType): float
{
    // Get active pricing tier for business type
    $pricingTier = PricingTier::query()
        ->where('business_type', $businessType)
        ->where('is_active', true)
        ->where('effective_date', '<=', now())
        ->orderBy('effective_date', 'desc')
        ->first();

    if (!$pricingTier) {
        // Fallback to default pricing
        $pricingTier = PricingTier::query()
            ->whereNull('business_type')
            ->where('is_active', true)
            ->first();
    }

    return round($totalKm * $pricingTier->price_per_km, 2);
}
```

**Pricing Tier Setup**:
- Seed default pricing tier: 0.50 JOD per KM
- Support different rates for different business types

**Dependencies**: None

---

### Task 6: Integrate Services into Controller

**File**: `/backend/app/Http/Controllers/Api/V1/DeliveryRequestController.php` (edit)

**Description**: Wire up route optimization and pricing into delivery request creation

**What to Build**:
```php
public function store(StoreDeliveryRequestRequest $request)
{
    DB::transaction(function () use ($request) {
        // 1. Create delivery request
        $deliveryRequest = DeliveryRequest::create([
            'business_id' => auth()->user()->business_id,
            'status' => DeliveryRequestStatus::Pending,
        ]);

        // 2. Create destinations
        foreach ($request->destinations as $dest) {
            $deliveryRequest->destinations()->create([
                'external_id' => $dest['external_id'],
                'address' => $dest['address'],
                'lat' => $dest['lat'],
                'lng' => $dest['lng'],
                'status' => DestinationStatus::Pending,
            ]);
        }

        // 3. Optimize route
        $routeOptimizer = app(RouteOptimizerService::class);
        $routeOptimizer->optimize($deliveryRequest);

        // 4. Calculate cost
        $pricingService = app(PricingService::class);
        $estimatedCost = $pricingService->calculateCost(
            $deliveryRequest->fresh()->total_km,
            auth()->user()->business->business_type
        );

        $deliveryRequest->update(['estimated_cost' => $estimatedCost]);

        return new DeliveryRequestResource($deliveryRequest);
    });
}
```

**Dependencies**: Tasks 1, 4, 5

---

### Task 7: Create Driver Trip Endpoints 🔴 CRITICAL

**File**: `/backend/app/Http/Controllers/Api/V1/DriverController.php` (new)

**Description**: Build API endpoints for driver to manage trips via Flutter app

**What to Build**:
1. `GET /api/v1/driver/trips/today` - Get driver's assigned trips for today
2. `POST /api/v1/driver/trips/{trip}/start` - Start a trip
3. `POST /api/v1/driver/trips/{trip}/destinations/{destination}/arrive` - Mark arrived at stop
4. `POST /api/v1/driver/trips/{trip}/destinations/{destination}/complete` - Complete delivery
5. `POST /api/v1/driver/trips/{trip}/destinations/{destination}/fail` - Mark delivery failed
6. `GET /api/v1/driver/navigation/{destination}` - Get Google Maps deep link

**API Contracts**:

**1. Today's Trips**:
```php
GET /api/v1/driver/trips/today
Authorization: Bearer {driver_token}

Response:
{
  "data": [
    {
      "id": "uuid",
      "delivery_request_id": "uuid",
      "status": "in_progress",
      "started_at": "2026-01-04T08:00:00Z",
      "destinations": [
        {
          "id": "uuid",
          "external_id": "ORD-001",
          "address": "123 Main St",
          "lat": 31.9539,
          "lng": 35.9106,
          "sequence_order": 1,
          "status": "pending"
        }
      ]
    }
  ]
}
```

**2. Start Trip**:
```php
POST /api/v1/driver/trips/{trip}/start

Response:
{
  "data": {
    "id": "uuid",
    "status": "in_progress",
    "started_at": "2026-01-04T08:00:00Z"
  }
}
```

**3. Mark Arrived**:
```php
POST /api/v1/driver/trips/{trip}/destinations/{destination}/arrive

Response:
{
  "data": {
    "id": "uuid",
    "status": "arrived",
    "arrived_at": "2026-01-04T08:15:00Z"
  }
}
```

**4. Mark Completed**:
```php
POST /api/v1/driver/trips/{trip}/destinations/{destination}/complete
{
  "recipient_name": "John Doe"
}

Response:
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "completed_at": "2026-01-04T08:20:00Z",
    "recipient_name": "John Doe"
  }
}
```

**5. Get Navigation URL**:
```php
GET /api/v1/driver/navigation/{destination}

Response:
{
  "url": "https://www.google.com/maps/dir/?api=1&destination=31.9539,35.9106&travelmode=driving"
}
```

**Implementation Notes**:
- Use `auth()->user()->driver` to get current driver
- Only allow drivers to access their own trips
- When destination completed, trigger callback to ERP (Task 8)
- Use `Destination::markArrived()`, `markCompleted()`, `markFailed()` model methods

**Dependencies**: None (models exist)

**Priority**: HIGH - Needed for Flutter app to function

---

### Task 8: Create Delivery Callback Service 🔴 CRITICAL

**File**: `/backend/app/Services/Delivery/DeliveryCallbackService.php` (new)

**Description**: Send delivery completion callbacks to ERP when driver completes a destination

**What to Build**:
```php
public function sendCompletionCallback(Destination $destination): void
{
    $business = $destination->deliveryRequest->business;

    // Don't send callback if business doesn't have callback URL configured
    if (!$business->callback_url) {
        return;
    }

    // Transform data using BusinessPayloadSchema
    $payload = [
        'external_id' => $destination->external_id,
        'status' => $destination->status->value,
        'completed_at' => $destination->completed_at?->toIso8601String(),
        'recipient_name' => $destination->recipient_name,
    ];

    // If business has custom callback schema, transform
    if ($business->payloadSchema) {
        $payload = $business->payloadSchema->transformForCallback($payload);
    }

    // Send POST request to ERP
    try {
        $response = Http::withToken($business->callback_api_key)
            ->timeout(10)
            ->post($business->callback_url, $payload);

        if (!$response->successful()) {
            Log::error('Delivery callback failed', [
                'business_id' => $business->id,
                'destination_id' => $destination->id,
                'status' => $response->status(),
            ]);

            // TODO: Queue retry job
        }
    } catch (\Exception $e) {
        Log::error('Delivery callback exception', [
            'business_id' => $business->id,
            'destination_id' => $destination->id,
            'error' => $e->getMessage(),
        ]);

        // TODO: Queue retry job
    }
}
```

**Trigger**: Call this from `DriverController::markCompleted()` after destination is marked as completed

**ERP Callback Format** (what they expect):
```json
POST {business.callback_url}
Authorization: Bearer {business.callback_api_key}

{
  "external_id": "ORD-20260104-001",
  "status": "completed",
  "completed_at": "2026-01-04T15:30:00Z",
  "recipient_name": "John Doe"
}
```

**Error Handling**:
- Log failures for monitoring
- Phase 2: Implement queue retry mechanism for failed callbacks

**Dependencies**: Task 7 (called from DriverController)

**Priority**: HIGH - ERP needs this to generate invoices

---

### Task 9: Add Routes to API

**File**: `/backend/routes/api/v1.php` (edit)

**Description**: Register all new endpoints

**What to Add**:
```php
use App\Http\Controllers\Api\V1\DeliveryRequestController;
use App\Http\Controllers\Api\V1\DriverController;

// Business delivery requests (authenticated with business API key)
Route::post('/delivery-requests', [DeliveryRequestController::class, 'store'])
    ->middleware('auth:api');

// Driver endpoints (authenticated with driver user token)
Route::middleware(['auth:api'])->prefix('driver')->group(function () {
    Route::get('/trips/today', [DriverController::class, 'todaysTrips']);
    Route::post('/trips/{trip}/start', [DriverController::class, 'startTrip']);
    Route::post('/trips/{trip}/destinations/{destination}/arrive', [DriverController::class, 'markArrived']);
    Route::post('/trips/{trip}/destinations/{destination}/complete', [DriverController::class, 'markCompleted']);
    Route::post('/trips/{trip}/destinations/{destination}/fail', [DriverController::class, 'markFailed']);
    Route::get('/navigation/{destination}', [DriverController::class, 'getNavigationUrl']);
});
```

**Note**: You may need to create a driver middleware to ensure only drivers can access driver endpoints

**Dependencies**: Tasks 1, 7

---

### Task 10: Create Feature Tests

**Files**:
- `/backend/tests/Feature/Api/V1/DeliveryRequestTest.php` (new)
- `/backend/tests/Feature/Api/V1/DriverTest.php` (new)

**Description**: Write comprehensive tests for all endpoints

**What to Test in DeliveryRequestTest**:
```php
- test_business_can_create_delivery_request()
- test_delivery_request_validates_destinations()
- test_delivery_request_optimizes_route()
- test_delivery_request_calculates_cost()
- test_unauthenticated_request_fails()
- test_inactive_business_cannot_create_request()
```

**What to Test in DriverTest**:
```php
- test_driver_can_view_todays_trips()
- test_driver_can_start_trip()
- test_driver_can_mark_arrived()
- test_driver_can_mark_completed()
- test_driver_can_mark_failed()
- test_driver_can_get_navigation_url()
- test_driver_cannot_access_other_drivers_trips()
- test_callback_sent_when_destination_completed()
```

**Dependencies**: All above tasks

---

### Task 11: Integrate Flutter App with APIs

**Files**:
- `/flutter_app/lib/features/trips/data/trips_repository.dart` (new)
- `/flutter_app/lib/features/trips/providers/trips_provider.dart` (edit)

**Description**: Replace mock data in Flutter app with real API calls

**What to Build**:

**1. Create TripsRepository**:
```dart
class TripsRepository {
  final ApiClient _apiClient;

  TripsRepository(this._apiClient);

  Future<List<Trip>> fetchTodaysTrips() async {
    final response = await _apiClient.get('/driver/trips/today');
    return (response.data['data'] as List)
        .map((json) => Trip.fromJson(json))
        .toList();
  }

  Future<Trip> startTrip(String tripId) async {
    final response = await _apiClient.post('/driver/trips/$tripId/start');
    return Trip.fromJson(response.data['data']);
  }

  Future<Destination> markArrived(String tripId, String destId) async {
    final response = await _apiClient.post(
      '/driver/trips/$tripId/destinations/$destId/arrive',
    );
    return Destination.fromJson(response.data['data']);
  }

  Future<Destination> markCompleted(
    String tripId,
    String destId,
    String recipientName,
  ) async {
    final response = await _apiClient.post(
      '/driver/trips/$tripId/destinations/$destId/complete',
      data: {'recipient_name': recipientName},
    );
    return Destination.fromJson(response.data['data']);
  }
}
```

**2. Update Riverpod Providers**:
```dart
final tripsRepositoryProvider = Provider<TripsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TripsRepository(apiClient);
});

final todaysTripsProvider = FutureProvider<List<Trip>>((ref) async {
  final repository = ref.watch(tripsRepositoryProvider);
  return repository.fetchTodaysTrips();
});
```

**3. Update UI Screens**:
- `trips_list_screen.dart`: Use `ref.watch(todaysTripsProvider)` instead of mock data
- `trip_details_screen.dart`: Wire up action buttons to repository methods
- Add loading states, error handling, and pull-to-refresh

**Dependencies**: Task 7 (API must be ready)

---

### Task 12: Add GPS Tracking Service

**File**: `/flutter_app/lib/services/location_service.dart` (new)

**Description**: Track driver location and calculate actual KM traveled

**What to Build**:
```dart
class LocationService {
  final Geolocator _geolocator;
  StreamSubscription<Position>? _positionStreamSubscription;
  List<Position> _positions = [];

  // Start tracking when trip starts
  Future<void> startTracking() async {
    final permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied) return;

    _positionStreamSubscription = Geolocator.getPositionStream(
      locationSettings: LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // Update every 10 meters
      ),
    ).listen((Position position) {
      _positions.add(position);
    });
  }

  // Stop tracking when trip ends
  void stopTracking() {
    _positionStreamSubscription?.cancel();
  }

  // Calculate total distance
  double calculateTotalKm() {
    double totalMeters = 0.0;
    for (int i = 0; i < _positions.length - 1; i++) {
      totalMeters += Geolocator.distanceBetween(
        _positions[i].latitude,
        _positions[i].longitude,
        _positions[i + 1].latitude,
        _positions[i + 1].longitude,
      );
    }
    return totalMeters / 1000; // Convert to KM
  }
}
```

**Integration**:
- Start tracking when driver starts trip
- Stop tracking when all destinations completed
- Send `actual_km` to backend when trip completed

**Dependencies**: Task 11

---

### Task 13: Create Admin Panel Resources

**Files**:
- `/backend/app/Filament/Resources/BusinessResource.php` (new)
- `/backend/app/Filament/Resources/DeliveryRequestResource.php` (new)
- `/backend/app/Filament/Resources/TripResource.php` (new)

**Description**: Build Filament admin panel for managing system

**What to Build**:

**1. BusinessResource**: CRUD for ERP clients
- List: name, business_type, is_active
- Form: name, business_type, api_key, callback_url, callback_api_key
- Action: Regenerate API key

**2. DeliveryRequestResource**: View delivery requests
- List: business, status, total_km, estimated_cost, created_at
- View: Full details with destinations list
- Filter by status, business, date

**3. TripResource**: Monitor trips
- List: driver, delivery_request, status, started_at
- View: Trip details with destination statuses
- Filter by driver, status, date

**Dependencies**: None (can work in parallel)

---

### Task 14: Configure Google Maps API

**Files**:
- `/backend/config/services.php` (edit)
- `/backend/.env.example` (edit)

**Description**: Add Google Maps configuration

**What to Add to `config/services.php`**:
```php
'google_maps' => [
    'api_key' => env('GOOGLE_MAPS_API_KEY'),
],
```

**What to Add to `.env.example`**:
```env
# Google Maps API
GOOGLE_MAPS_API_KEY=your_api_key_here

# Warehouse location (starting point for routes)
WAREHOUSE_LAT=31.9539
WAREHOUSE_LNG=35.9106
```

**Setup Instructions**:
1. Get API key from Google Cloud Console
2. Enable "Directions API" and "Distance Matrix API"
3. Set billing account (required even for free tier)
4. Add API key to `.env`

**Dependencies**: None

---

## Summary

### Integration Flow

```
ERP: Order ready
  ↓
ERP: POST /api/v1/delivery-requests (Task 1)
  ↓
You: Optimize route (Task 4) + Calculate cost (Task 5)
  ↓
You: Assign to driver, create Trip
  ↓
Driver: Uses Flutter app to execute deliveries (Task 11)
  ↓
Driver: Marks destination completed
  ↓
You: Send callback to ERP (Task 8)
  ↓
ERP: Generate invoice
```

### Task Priority

**Phase 1: Core API** (Week 1-2):
- ✅ Task 1: DeliveryRequest endpoint
- ✅ Task 2-3: Validation and resources
- ✅ Task 7: Driver endpoints
- ✅ Task 8: Callback service
- ✅ Task 9: Routes

**Phase 2: Business Logic** (Week 2-3):
- ✅ Task 4: Route optimization
- ✅ Task 5: Pricing service
- ✅ Task 6: Integration
- ✅ Task 14: Google Maps config

**Phase 3: Testing & Flutter** (Week 3-4):
- ✅ Task 10: Feature tests
- ✅ Task 11: Flutter API integration
- ✅ Task 12: GPS tracking

**Phase 4: Admin Panel** (Week 4-5, optional):
- ✅ Task 13: Filament resources

### Estimated Timeline

- **3-4 weeks** for full backend implementation
- **1 week** for Flutter app integration
- **Total: 4-5 weeks** to production-ready

### Critical Dependencies

1. **ERP must wait for Task 1** before they can submit delivery requests
2. **Flutter must wait for Task 7** before replacing mock data
3. **Task 8 must work** for ERP to receive delivery confirmations

### Getting Started

1. Start with **Task 1** (DeliveryRequest endpoint)
2. Implement **Task 7** (Driver endpoints) in parallel
3. Test integration with ERP team
4. Add services (Tasks 4-5) to enhance functionality
5. Complete Flutter integration (Tasks 11-12)

---

**Questions?** Contact ERP team for API contract clarification or integration testing.

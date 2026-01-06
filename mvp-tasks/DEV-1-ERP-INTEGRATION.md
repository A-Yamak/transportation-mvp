# DEV-1: ERP Integration - DeliveryRequest API & Callbacks

> **Role**: ERP Integration Developer
> **Branch**: `feature/dev-1-erp-integration`
> **Priority**: CRITICAL - This enables Melo ERP to send delivery requests

---

## SCRUM MASTER AVAILABLE

A scrum master is **ALWAYS available** to help you:
- Resolve blockers or unclear requirements
- Answer architecture questions
- Coordinate with Melo ERP team
- Review your approach before implementation

**Don't hesitate to ask for help!** It's better to clarify than to build the wrong thing.

---

## CONTEXT: Why This Matters

The **Melo Group ERP** (sweets factory) needs to send delivery requests to this Transportation App. Currently:
- ERP has orders ready for delivery
- But NO endpoint exists to receive them
- Drivers can't be dispatched
- ERP won't know when deliveries complete

**You are building the bridge between ERP and Transportation.**

---

## YOUR RESPONSIBILITIES (What You DO)

### Task 1: DeliveryRequestController (CRITICAL - Do First)

**Files to create**:
- `backend/app/Http/Controllers/Api/V1/DeliveryRequestController.php`
- `backend/app/Http/Requests/Api/V1/StoreDeliveryRequestRequest.php`
- `backend/app/Http/Resources/Api/V1/DeliveryRequestResource.php`
- `backend/app/Http/Resources/Api/V1/DestinationResource.php`

**Routes to add** in `backend/routes/api/v1.php`:
```php
Route::prefix('delivery-requests')->group(function () {
    Route::get('/', [DeliveryRequestController::class, 'index']);
    Route::post('/', [DeliveryRequestController::class, 'store']);
    Route::get('{deliveryRequest}', [DeliveryRequestController::class, 'show']);
    Route::get('{deliveryRequest}/route', [DeliveryRequestController::class, 'route']);
    Route::post('{deliveryRequest}/cancel', [DeliveryRequestController::class, 'cancel']);
});
```

**Controller implementation**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDeliveryRequestRequest;
use App\Http\Resources\Api\V1\DeliveryRequestResource;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Services\RouteOptimizer;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;

class DeliveryRequestController extends Controller
{
    public function __construct(
        private readonly RouteOptimizer $routeOptimizer,
        private readonly PricingService $pricingService,
    ) {}

    public function store(StoreDeliveryRequestRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        // Get destinations from request
        $destinations = $request->input('destinations');

        // Optimize route via Google Maps
        $optimized = $this->routeOptimizer->optimize($destinations);

        // Calculate cost
        $totalKm = $optimized['total_distance_meters'] / 1000;
        $cost = $this->pricingService->calculate($totalKm, $business);

        // Create delivery request
        $deliveryRequest = DeliveryRequest::create([
            'business_id' => $business->id,
            'total_km' => $totalKm,
            'estimated_cost' => $cost,
            'optimized_route' => $optimized['polyline'],
            'status' => 'pending',
            'callback_url' => $request->input('callback_url'),
        ]);

        // Create destinations with optimized order
        foreach ($optimized['optimized_order'] as $index => $originalIndex) {
            Destination::create([
                'delivery_request_id' => $deliveryRequest->id,
                'sequence_order' => $index + 1,
                'external_id' => $destinations[$originalIndex]['external_id'],
                'address' => $destinations[$originalIndex]['address'],
                'lat' => $destinations[$originalIndex]['lat'],
                'lng' => $destinations[$originalIndex]['lng'],
                'contact_name' => $destinations[$originalIndex]['contact_name'] ?? null,
                'contact_phone' => $destinations[$originalIndex]['contact_phone'] ?? null,
                'notes' => $destinations[$originalIndex]['notes'] ?? null,
            ]);
        }

        return (new DeliveryRequestResource($deliveryRequest->load('destinations')))
            ->response()
            ->setStatusCode(201);
    }

    public function index(): JsonResponse
    {
        $deliveryRequests = DeliveryRequest::where('business_id', auth()->user()->business_id)
            ->with('destinations')
            ->orderBy('created_at', 'desc')
            ->paginate();

        return DeliveryRequestResource::collection($deliveryRequests)->response();
    }

    public function show(DeliveryRequest $deliveryRequest): DeliveryRequestResource
    {
        return new DeliveryRequestResource($deliveryRequest->load('destinations'));
    }

    public function route(DeliveryRequest $deliveryRequest): JsonResponse
    {
        return response()->json([
            'data' => [
                'polyline' => $deliveryRequest->optimized_route,
                'total_km' => $deliveryRequest->total_km,
                'destinations' => DestinationResource::collection($deliveryRequest->destinations),
            ],
        ]);
    }
}
```

**Request Validation**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->business !== null;
    }

    public function rules(): array
    {
        return [
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.external_id' => ['required', 'string', 'max:100'],
            'destinations.*.address' => ['required', 'string', 'max:500'],
            'destinations.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'destinations.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'destinations.*.contact_name' => ['nullable', 'string', 'max:100'],
            'destinations.*.contact_phone' => ['nullable', 'string', 'max:20'],
            'destinations.*.notes' => ['nullable', 'string', 'max:500'],
            'callback_url' => ['nullable', 'url'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
```

**Write Tests First (TDD)**:
```
tests/Feature/Api/V1/DeliveryRequestControllerTest.php
- test_creates_delivery_request_with_destinations
- test_optimizes_route_on_creation
- test_calculates_cost_based_on_km
- test_lists_business_delivery_requests
- test_shows_delivery_request_with_route
- test_requires_authentication
- test_validates_destination_coordinates
```

---

### Task 2: RouteOptimizer Service

**File to create**: `backend/app/Services/RouteOptimizer.php`

**What it must do**:
1. Call Google Maps Directions API
2. Use `optimize:true` for waypoint optimization
3. Return optimized order and total distance
4. Cache results for identical destination sets

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RouteOptimizer
{
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/directions/json';

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');
    }

    /**
     * Optimize route for multiple destinations.
     *
     * @param array $destinations Array of ['lat' => float, 'lng' => float, ...]
     * @return array ['optimized_order' => int[], 'total_distance_meters' => int, 'polyline' => string]
     */
    public function optimize(array $destinations): array
    {
        if (count($destinations) < 2) {
            return [
                'optimized_order' => array_keys($destinations),
                'total_distance_meters' => 0,
                'polyline' => '',
            ];
        }

        // Use factory location as start/end point
        $origin = config('services.google_maps.factory_location', '31.9539,35.9106'); // Amman default

        // Build waypoints string
        $waypoints = collect($destinations)
            ->map(fn($d) => "{$d['lat']},{$d['lng']}")
            ->implode('|');

        // Cache key based on destinations
        $cacheKey = 'route_' . md5($origin . $waypoints);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($origin, $waypoints) {
            $response = Http::get($this->baseUrl, [
                'origin' => $origin,
                'destination' => $origin, // Round trip back to factory
                'waypoints' => "optimize:true|{$waypoints}",
                'key' => $this->apiKey,
            ]);

            if ($response->failed()) {
                throw new \Exception('Google Maps API error: ' . $response->body());
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                throw new \Exception('Google Maps API error: ' . $data['status']);
            }

            $route = $data['routes'][0];

            return [
                'optimized_order' => $route['waypoint_order'],
                'total_distance_meters' => $this->sumLegDistances($route['legs']),
                'polyline' => $route['overview_polyline']['points'],
            ];
        });
    }

    private function sumLegDistances(array $legs): int
    {
        return collect($legs)->sum(fn($leg) => $leg['distance']['value']);
    }
}
```

**Write Tests**:
```
tests/Unit/Services/RouteOptimizerTest.php
- test_optimizes_multiple_destinations
- test_handles_single_destination
- test_caches_route_results
- test_throws_on_api_error
```

---

### Task 3: PricingService

**File to create**: `backend/app/Services/PricingService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\PricingTier;

class PricingService
{
    /**
     * Calculate delivery cost based on distance.
     */
    public function calculate(float $totalKm, Business $business): float
    {
        $tier = PricingTier::where('business_type', $business->business_type)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();

        $pricePerKm = $tier?->price_per_km ?? config('services.pricing.default_per_km', 0.50);

        return round($totalKm * $pricePerKm, 2);
    }
}
```

---

### Task 4: DeliveryCallbackService

**File to create**: `backend/app/Services/DeliveryCallbackService.php`

**What it must do**:
1. Send HTTP callback to ERP when destination is completed
2. Use business's callback_url
3. Transform payload using PayloadSchema (if configured)
4. Retry on failure
5. Log callback attempts

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Destination;
use App\Models\CallbackLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryCallbackService
{
    /**
     * Send delivery completion callback to business ERP.
     */
    public function sendCallback(Destination $destination): bool
    {
        $deliveryRequest = $destination->deliveryRequest;
        $business = $deliveryRequest->business;

        $callbackUrl = $deliveryRequest->callback_url ?? $business->callback_url;

        if (!$callbackUrl) {
            Log::warning('No callback URL configured', [
                'destination_id' => $destination->id,
                'business_id' => $business->id,
            ]);
            return false;
        }

        $payload = $this->buildPayload($destination);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $business->callback_api_key,
                    'Content-Type' => 'application/json',
                ])
                ->post($callbackUrl, $payload);

            // Log callback attempt
            CallbackLog::create([
                'destination_id' => $destination->id,
                'url' => $callbackUrl,
                'payload' => $payload,
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'success' => $response->successful(),
            ]);

            if ($response->failed()) {
                Log::error('Callback failed', [
                    'destination_id' => $destination->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Callback exception', [
                'destination_id' => $destination->id,
                'error' => $e->getMessage(),
            ]);

            CallbackLog::create([
                'destination_id' => $destination->id,
                'url' => $callbackUrl,
                'payload' => $payload,
                'error_message' => $e->getMessage(),
                'success' => false,
            ]);

            return false;
        }
    }

    private function buildPayload(Destination $destination): array
    {
        return [
            'external_id' => $destination->external_id,
            'status' => 'delivered',
            'delivered_at' => $destination->completed_at?->toIso8601String(),
            'driver_notes' => $destination->notes,
            'signature_url' => $destination->signature_url,
            'photo_url' => $destination->photo_url,
        ];
    }
}
```

**You need to create migration for CallbackLog**:
```php
Schema::create('callback_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('destination_id')->constrained();
    $table->string('url');
    $table->json('payload');
    $table->integer('response_code')->nullable();
    $table->text('response_body')->nullable();
    $table->text('error_message')->nullable();
    $table->boolean('success')->default(false);
    $table->timestamps();
});
```

**Write Tests**:
```
tests/Unit/Services/DeliveryCallbackServiceTest.php
- test_sends_callback_on_delivery_completion
- test_logs_callback_attempt
- test_handles_callback_failure
- test_skips_when_no_callback_url
```

---

## NOT YOUR RESPONSIBILITY (Handled by Others)

| Task | Handled By | Why Not You |
|------|------------|-------------|
| Driver mobile endpoints | DEV-2 | Different API consumer |
| Flutter app changes | DEV-3 | Different tech stack |
| Melo ERP changes | Melo ERP Team | Separate project |
| Ledger/Accounting | Post-MVP | Not priority |

---

## TECH STACK REFERENCE

**Laravel 12 + PHP 8.4**:
- Controllers in `app/Http/Controllers/Api/V1/`
- Use typed properties and constructor promotion
- Use `DB::transaction()` for atomic operations

**Google Maps API**:
- Directions API for route optimization
- Cost: ~$5 per 1,000 requests
- Cache aggressively to minimize costs

**Testing (TDD)**:
```bash
# Run your tests
make test-filter name="DeliveryRequestControllerTest"
make test-filter name="RouteOptimizerTest"

# Run all tests
make test
```

**Generators**:
```bash
make v1-controller name=DeliveryRequestController
make v1-request name=StoreDeliveryRequestRequest
make v1-resource name=DeliveryRequestResource
```

---

## EXPECTED STANDARDS

1. **Form Requests**: All validation in request classes
2. **Resources**: Transform all API responses
3. **Service Pattern**: Business logic in services
4. **Caching**: Cache expensive operations (Google Maps)
5. **Logging**: Log important operations and errors
6. **Error Handling**: Return proper HTTP status codes

---

## DEFINITION OF DONE

- [ ] DeliveryRequestController with all CRUD endpoints
- [ ] StoreDeliveryRequestRequest with full validation
- [ ] DeliveryRequestResource and DestinationResource
- [ ] Routes added to api/v1.php
- [ ] DeliveryRequestControllerTest with 7+ tests passing
- [ ] RouteOptimizer service with Google Maps integration
- [ ] RouteOptimizerTest with 4+ tests passing
- [ ] PricingService for cost calculation
- [ ] DeliveryCallbackService for ERP notifications
- [ ] CallbackLog migration and model
- [ ] DeliveryCallbackServiceTest passing
- [ ] All tests pass: `make test`
- [ ] Code linted: `make lint`
- [ ] Changes committed to your branch

---

## HANDOFF TO DEV-2

When you complete:
- DeliveryRequest is created, destinations stored
- Route is optimized, cost calculated
- Callback service ready

DEV-2 will build driver endpoints that:
1. List delivery requests for drivers
2. Mark destinations as arrived/completed
3. Trigger your callback service on completion

Notify scrum master when your endpoints are ready!

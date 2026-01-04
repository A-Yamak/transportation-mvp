# Transportation App

## Quick Context

This is a logistics/delivery management application that handles delivery requests from multiple business clients (like the Sweets Factory ERP). It optimizes routes using Google Maps API, calculates costs based on distance, and provides a Flutter mobile app for drivers. The system uses an independent double-entry ledger for financial tracking and supports dynamic API payload schemas for different client integrations.

## Tech Stack

- **Backend**: Laravel 12, PHP 8.4, FrankenPHP/Octane
- **Driver Mobile App**: Flutter (iOS/Android)
- **Admin Panel**: Filament 4
- **Database**: MySQL 8.0
- **Cache/Queue**: Redis 7
- **Storage**: Cloudflare R2 (S3-compatible)
- **Maps**: Google Maps API (Directions, Distance Matrix)
- **Authentication**: Laravel Passport (OAuth2)

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/           # API controllers (versioned)
│   │   ├── Requests/Api/V1/      # Form request validation
│   │   └── Resources/Api/V1/     # API response transformers
│   ├── Models/                   # Eloquent models
│   ├── Services/                 # Business logic services
│   │   ├── GoogleMaps/          # Route optimization, distance calc
│   │   ├── Ledger/              # Double-entry accounting
│   │   ├── Pricing/             # Cost calculation
│   │   └── PayloadSchema/       # Dynamic API payload handling
│   ├── Enums/                   # PHP enums (TripStatus, BusinessType, etc.)
│   └── Filament/                # Admin panel resources
├── database/
│   ├── migrations/              # Schema definitions
│   ├── factories/               # Test data factories
│   └── seeders/                 # Database seeders
├── routes/
│   └── api/v1.php              # API routes
└── tests/
    ├── Unit/                    # Unit tests
    └── Feature/                 # Feature/integration tests

flutter_app/                     # Flutter mobile app for drivers
infrastructure/                  # Docker, Kubernetes configs
```

## Key Concepts (Domain Glossary)

### Businesses (Clients)
Companies that use our transportation service. Two types:
- **bulk_order**: Receives delivery requests via API (e.g., ERP systems)
- **pickup**: Driver goes to collect items, then delivers

Each business has:
- API credentials for integration
- Custom payload schema (how they send/receive data)
- Billing settings

### Vehicles
Transportation vehicles in our fleet. Tracked data:
- Make, model, year (e.g., VW Caddy 2019)
- Total KM driven (lifetime)
- Monthly KM via app (tracked)
- Acquisition date, maintenance records

### Drivers
Users who operate vehicles:
- Assigned to specific vehicle
- Earnings tracked via ledger
- Mobile app for trip management

### DeliveryRequests
A batch of destinations to deliver to. Can come from:
- API (ERP integration)
- Manual entry (admin panel)

Contains:
- Multiple destinations
- Optimized route (from Google Maps)
- Total KM and cost estimate
- Status: pending → accepted → in_progress → completed

### Destinations
Individual stops within a delivery request:
- Address and coordinates (lat/lng)
- Sequence order (from route optimization)
- Status: pending → arrived → completed
- On completion, triggers callback to client

### BusinessPayloadSchemas
Configurable API structure per business. Allows different ERPs to integrate with different field names:
```json
{
  "request_schema": {
    "order_id_field": "external_id",
    "address_field": "delivery_address",
    "coordinates": { "lat": "latitude", "lng": "longitude" }
  },
  "response_schema": {
    "status_field": "delivery_status",
    "completed_at_field": "delivered_at"
  }
}
```

### PricingTiers
Cost calculation: `total_km × price_per_km`
- Different tiers for different business types
- Effective dates for price changes

### Trips
Individual driver trips:
- Links driver, vehicle, delivery request
- Tracks actual KM (GPS)
- Start/end time
- Creates journal entry for accounting

### Ledger (Double-Entry Accounting)
Independent from ERP. Tracks:
- Trip revenue (billing to businesses)
- Fuel costs
- Driver payments
- Vehicle maintenance

## Critical Files

### Backend Core
- `app/Services/GoogleMaps/RouteOptimizer.php` - Google Directions API
- `app/Services/GoogleMaps/DistanceCalculator.php` - Distance Matrix API
- `app/Services/Pricing/CostCalculator.php` - KM × price calculation
- `app/Services/PayloadSchema/SchemaTransformer.php` - Dynamic field mapping
- `app/Services/Ledger/LedgerService.php` - Double-entry accounting
- `app/Models/DeliveryRequest.php` - Core delivery model
- `app/Models/Destination.php` - Individual stops

### Routes
- `routes/api/v1.php` - All API endpoints
- `routes/api/v1/driver.php` - Driver-specific routes (if separated)

### Config
- `config/google-maps.php` - Google API credentials
- `config/pricing.php` - Pricing tiers configuration
- `config/services.php` - External service connections

## Common Tasks

### Adding a New API Endpoint
```bash
# 1. Create controller
make v1-controller name=NewFeatureController

# 2. Create form request for validation
make v1-request name=StoreNewFeatureRequest

# 3. Create resource for response transformation
make v1-resource name=NewFeatureResource

# 4. Add route in routes/api/v1.php
# 5. Write feature test in tests/Feature/
```

### Adding a New Model
```bash
# Create model with migration, factory, and seeder
make model name=NewModel flags="-mfs"

# Create Filament resource for admin panel
php artisan make:filament-resource NewModel --generate
```

### Running Tests
```bash
make test              # Run all tests
make test-unit         # Unit tests only
make test-feature      # Feature tests only
make test-filter t=DeliveryRequestTest  # Specific test
```

## Testing Requirements

**Every feature MUST have:**
1. **Factory**: `database/factories/ModelFactory.php`
2. **Feature Test**: `tests/Feature/Api/V1/FeatureTest.php`
3. **Unit Tests** for complex business logic in services

**Test patterns:**
```php
// Feature test example
public function test_can_create_delivery_request(): void
{
    $business = Business::factory()->create();
    
    $response = $this->postJson('/api/v1/delivery-requests', [
        'business_id' => $business->id,
        'destinations' => [
            ['address' => '123 Main St', 'lat' => 31.9, 'lng' => 35.9],
            ['address' => '456 Oak Ave', 'lat' => 31.8, 'lng' => 35.8],
        ]
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'status', 'total_km', 'estimated_cost', 'destinations']
        ]);
}
```

## API Patterns

### Standard Response Format
```json
{
  "data": { ... },
  "message": "Optional success message"
}
```

### Pagination
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

### Error Response
```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

## Google Maps API Integration

### Cost-Efficient Strategy

**1. Route Optimization (Directions API)**
Called ONCE when delivery request is created:
```php
// In RouteOptimizer service
public function optimize(array $destinations): array
{
    $waypoints = collect($destinations)
        ->map(fn($d) => "{$d['lat']},{$d['lng']}")
        ->implode('|');
    
    $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
        'origin' => $startPoint,
        'destination' => $startPoint, // Round trip
        'waypoints' => "optimize:true|{$waypoints}",
        'key' => config('google-maps.api_key'),
    ]);
    
    // Returns optimized order + total distance
    return [
        'optimized_order' => $response['routes'][0]['waypoint_order'],
        'total_distance_meters' => $this->sumLegDistances($response),
        'polyline' => $response['routes'][0]['overview_polyline']['points'],
    ];
}
```

**2. Single-Destination Navigation (FREE)**
Driver uses device's Google Maps app - we don't pay for navigation:
```php
// Generate deep link to Google Maps app
public function getNavigationUrl(Destination $destination): string
{
    return "https://www.google.com/maps/dir/?api=1" .
           "&destination={$destination->lat},{$destination->lng}" .
           "&travelmode=driving";
}
```

**3. Distance Matrix (Cost Estimation)**
Called for cost estimates, batched when possible:
```php
public function calculateDistances(array $origins, array $destinations): array
{
    // Batch up to 25 origins × 25 destinations per request
    $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
        'origins' => implode('|', $origins),
        'destinations' => implode('|', $destinations),
        'key' => config('google-maps.api_key'),
    ]);
    
    return $response['rows'];
}
```

### API Cost Considerations
- Directions API: $5 per 1,000 requests
- Distance Matrix: $5 per 1,000 elements
- **Navigation: FREE** (uses device's Google Maps app)
- Cache route results when destinations are similar

## Financial System (Double-Entry Ledger)

### Chart of Accounts Structure
```
1000 - Assets
  1100 - Cash
  1200 - Accounts Receivable
  1400 - Vehicles
2000 - Liabilities
  2100 - Accounts Payable
3000 - Equity
4000 - Revenue
  4100 - Delivery Revenue
5000 - Expenses
  5100 - Fuel Expense
  5200 - Driver Payments
  5300 - Vehicle Maintenance
```

### Common Journal Entries

**Trip Completed (Revenue):**
```
DEBIT  1200 Accounts Receivable (Business)  150.00
CREDIT 4100 Delivery Revenue                150.00
```

**Payment Received:**
```
DEBIT  1100 Cash                            150.00
CREDIT 1200 Accounts Receivable (Business)  150.00
```

**Fuel Purchase:**
```
DEBIT  5100 Fuel Expense                    50.00
CREDIT 1100 Cash                            50.00
```

**Driver Payment:**
```
DEBIT  5200 Driver Payments                 100.00
CREDIT 1100 Cash                            100.00
```

## Dynamic Payload Schemas

Businesses can integrate with different field names:

### Schema Definition (in BusinessPayloadSchema)
```json
{
  "schema_name": "erp_v1",
  "request_mapping": {
    "external_id": "order_id",
    "address": "delivery_address",
    "lat": "coordinates.latitude",
    "lng": "coordinates.longitude",
    "items_count": "total_items"
  },
  "callback_mapping": {
    "status": "delivery_status",
    "completed_at": "delivered_timestamp",
    "external_id": "order_id"
  }
}
```

### Incoming Request Transformation
```php
// In SchemaTransformer service
public function transformIncoming(array $data, BusinessPayloadSchema $schema): array
{
    $mapping = $schema->request_mapping;
    
    return [
        'external_id' => data_get($data, $mapping['external_id']),
        'address' => data_get($data, $mapping['address']),
        'lat' => data_get($data, $mapping['lat']),
        'lng' => data_get($data, $mapping['lng']),
        'items_count' => data_get($data, $mapping['items_count']),
    ];
}
```

### Callback Transformation
```php
public function transformCallback(Destination $destination, BusinessPayloadSchema $schema): array
{
    $mapping = $schema->callback_mapping;
    
    return [
        $mapping['external_id'] => $destination->external_id,
        $mapping['status'] => 'completed',
        $mapping['completed_at'] => $destination->completed_at->toIso8601String(),
    ];
}
```

## Driver Mobile App (Flutter)

### Key Endpoints
```
GET  /api/v1/driver/trips/today       - Today's assigned trips
POST /api/v1/driver/trips/{id}/start  - Start trip
POST /api/v1/driver/trips/{id}/arrive/{destination_id}  - Arrived at stop
POST /api/v1/driver/trips/{id}/complete/{destination_id} - Complete delivery
GET  /api/v1/driver/navigation/{destination_id} - Get navigation URL
```

### Trip Flow
```
Driver Opens App
    ↓
GET /driver/trips/today → List of trips
    ↓
SELECT trip → POST /trips/{id}/start
    ↓
See optimized route with destinations
    ↓
TAP destination → Open Google Maps (device app)
    ↓
ARRIVE → POST /trips/{id}/arrive/{dest_id}
    ↓
COMPLETE (optional signature/photo) → POST /trips/{id}/complete/{dest_id}
    ↓
[Callback sent to client ERP]
    ↓
NEXT destination... repeat
    ↓
ALL complete → Trip marked complete
```

### GPS Tracking (for KM calculation)
```dart
// In Flutter app
void startTrip() {
    _locationSubscription = Geolocator.getPositionStream().listen((pos) {
        if (_lastPosition != null) {
            _totalDistance += Geolocator.distanceBetween(
                _lastPosition.lat, _lastPosition.lng,
                pos.latitude, pos.longitude
            );
        }
        _lastPosition = pos;
    });
}

void endTrip() {
    _locationSubscription.cancel();
    // Send total distance to API
    api.completeTrip(tripId, totalDistanceKm: _totalDistance / 1000);
}
```

## ERP Integration (Inbound)

### Receiving Delivery Requests
```php
// POST /api/v1/delivery-requests
public function store(Request $request): JsonResponse
{
    $business = $request->user()->business;
    $schema = $business->payloadSchema;
    
    // Transform incoming data using business's schema
    $destinations = collect($request->destinations)
        ->map(fn($d) => $this->schemaTransformer->transformIncoming($d, $schema))
        ->toArray();
    
    // Optimize route via Google Maps
    $optimized = $this->routeOptimizer->optimize($destinations);
    
    // Calculate cost
    $totalKm = $optimized['total_distance_meters'] / 1000;
    $cost = $this->costCalculator->calculate($totalKm, $business);
    
    // Create delivery request
    $deliveryRequest = DeliveryRequest::create([
        'business_id' => $business->id,
        'total_km' => $totalKm,
        'estimated_cost' => $cost,
        'optimized_route' => $optimized['polyline'],
    ]);
    
    // Create destinations with optimized order
    foreach ($optimized['optimized_order'] as $index => $originalIndex) {
        Destination::create([
            'delivery_request_id' => $deliveryRequest->id,
            'sequence_order' => $index,
            'external_id' => $destinations[$originalIndex]['external_id'],
            'address' => $destinations[$originalIndex]['address'],
            'lat' => $destinations[$originalIndex]['lat'],
            'lng' => $destinations[$originalIndex]['lng'],
        ]);
    }
    
    return response()->json([
        'data' => [
            'id' => $deliveryRequest->id,
            'total_km' => $totalKm,
            'estimated_cost' => $cost,
        ]
    ], 201);
}
```

### Sending Callbacks (Outbound)
```php
// When destination is marked complete
public function sendDeliveryCallback(Destination $destination): void
{
    $business = $destination->deliveryRequest->business;
    $schema = $business->payloadSchema;
    
    $payload = $this->schemaTransformer->transformCallback($destination, $schema);
    
    Http::withToken($business->callback_api_key)
        ->post($business->callback_url, $payload);
}
```

## Environment Variables

Key variables in `.env`:
```bash
# Application
APP_NAME="Transportation App"
APP_ENV=local
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=transportationapp

# Redis
REDIS_HOST=redis

# Google Maps API
GOOGLE_MAPS_API_KEY=your_api_key

# Pricing
DEFAULT_PRICE_PER_KM=0.50

# Cloudflare R2 Storage
AWS_ACCESS_KEY_ID=r2_access_key
AWS_SECRET_ACCESS_KEY=r2_secret_key
AWS_BUCKET=transportation-app
AWS_ENDPOINT=https://xxx.r2.cloudflarestorage.com
AWS_DEFAULT_REGION=auto
```

## Pricing Calculation

```php
// In CostCalculator service
public function calculate(float $totalKm, Business $business): float
{
    $tier = PricingTier::where('business_type', $business->business_type)
        ->where('effective_date', '<=', now())
        ->orderBy('effective_date', 'desc')
        ->first();
    
    $pricePerKm = $tier?->price_per_km ?? config('pricing.default_per_km');
    
    return round($totalKm * $pricePerKm, 2);
}
```

## Vehicle Tracking (MVP)

For MVP, tracking one vehicle (VW Caddy 2019):
```php
// Vehicle model
class Vehicle extends Model
{
    protected $casts = [
        'acquisition_date' => 'date',
        'total_km_driven' => 'decimal:2',
        'monthly_km_app' => 'decimal:2',
    ];
    
    public function updateKm(float $tripKm): void
    {
        $this->increment('total_km_driven', $tripKm);
        $this->increment('monthly_km_app', $tripKm);
    }
    
    // Reset monthly counter on 1st of each month
    public function resetMonthlyKm(): void
    {
        $this->update(['monthly_km_app' => 0]);
    }
}
```

## Code Style & Best Practices

1. **Always use Form Requests** for validation
2. **Always use API Resources** for response transformation
3. **Use services** for complex business logic, not controllers
4. **Cache Google Maps responses** when possible
5. **Use payload schemas** for all business integrations (never hardcode field names)
6. **Track all KM via GPS** for accurate billing
7. **Write tests first** when possible (TDD)
8. **Use enums** for status fields (`TripStatus`, `BusinessType`, etc.)

# Transportation App

## Quick Context

This is a logistics/delivery management application that handles delivery requests from multiple business clients (like the **Melo Group ERP**). It optimizes routes using Google Maps API, calculates costs based on distance, and provides a Flutter mobile app for drivers. The system uses an independent double-entry ledger for financial tracking and supports dynamic API payload schemas for different client integrations.

**Primary Client**: Melo Group (Sweets Factory in Jordan) - handles daily deliveries to 10+ shops via their Tramelo and Melo Supply distributors.

---

## MVP Status: Ready for Production + Phase 1 & 2 Complete

| Component | Status | Notes |
|-----------|--------|-------|
| Database Schema | DONE | All migrations complete |
| Eloquent Models | DONE | All models with relationships |
| Authentication | DONE | OAuth2 via Laravel Passport |
| API Controllers | DONE | 8 controllers, 39+ endpoints |
| Route Optimization Service | DONE | Google Maps integration with caching |
| Pricing Service | DONE | Tiered pricing with discounts |
| Driver Endpoints | DONE | 24 endpoints for Flutter app |
| ERP Callback Service | DONE | Async job-based callbacks with retry |
| Ledger System | DONE | Double-entry accounting |
| Auto-Assignment | DONE | Single driver MVP, round-robin for multi |
| Price Tracking | DONE | amount_to_collect, item prices, line totals |
| **Shop Management** | **DONE** | **Persistent shop locations, sync from ERP** |
| **Waste Tracking** | **DONE** | **Driver waste logging, auto-calculation, callbacks** |
| **External API** | **DONE** | **Separate namespace for B2B integration** |
| **Phase 1: Admin Panel** | **DONE** | **5 Filament resources (Shop, Driver, Business, Trip, Financial)** |
| **Phase 2: FCM Notifications** | **DONE** | **Push notifications for trip assignment/payment, Inbox UI** |
| Flutter UI | DONE | Complete driver interface with waste collection + inbox |
| Flutter API Integration | DONE | Real API client, GPS tracking, waste logging, notifications |
| **Test Coverage** | **DONE** | **55+ tests for critical operations (92%+ coverage)** |

### Production Readiness Checklist

**Backend (Ready)**
- [x] ERP can submit delivery requests via `POST /api/v1/delivery-requests`
- [x] Route optimization calculates optimal waypoint order
- [x] Cost calculation based on distance and pricing tiers
- [x] Driver endpoints for trip lifecycle (start, arrive, complete, fail)
- [x] ERP callbacks sent on destination completion (async via queue)
- [x] Double-entry ledger for financial tracking
- [x] API key authentication for B2B integrations
- [x] OAuth2 token auth for driver app
- [x] Auto-assignment of trips to drivers (configurable)
- [x] Price tracking: amount_to_collect, item unit_price, line totals
- [x] **Shop synchronization from Melo ERP via `/api/external/v1/shops/sync`**
- [x] **Waste collection tracking with driver validation**
- [x] **Automatic pieces_sold calculation (delivered - waste)**
- [x] **Waste callbacks sent to Melo ERP with exponential backoff retry**
- [x] **Expected waste date management**

**Flutter App (Ready)**
- [x] Real API integration (not mock data)
- [x] GPS tracking for actual KM calculation
- [x] Trip workflow: view → start → navigate → arrive → complete
- [x] Token refresh mechanism
- [x] Profile management
- [x] **Shop card display with waste status**
- [x] **Waste collection dialog with item-level controls**
- [x] **Real-time sold quantity calculation**
- [x] **Driver notes for waste items**

**Pre-Deployment Tasks**
1. Configure production `.env` (database, Redis, Google Maps API key)
2. Set up Cloudflare R2 bucket for file storage
3. Configure business callback URLs in admin panel
4. Deploy queue workers (Horizon) for async callbacks
5. **Exchange API keys with Melo ERP team**
6. **Set up Melo ERP webhook receiver for waste callbacks**
7. **Configure webhook signature secret (HMAC-SHA256)**
8. **Schedule daily shop sync cron job (2 AM)**
9. **Test end-to-end flow with Melo ERP**

---

## Auto-Assignment (Trip Assignment)

When Melo ERP submits a delivery request, the system automatically assigns it to an available driver.

### How It Works
1. ERP submits delivery request via `POST /api/v1/delivery-requests`
2. System creates Trip and assigns to available driver automatically
3. Response includes `assigned_driver` info so ERP knows assignment status
4. Driver sees trip immediately in Flutter app

### Configuration

Environment variables (in `.env`):
```bash
DELIVERY_AUTO_ASSIGN=true              # Enable/disable auto-assignment (default: true)
DELIVERY_AUTO_ASSIGN_STRATEGY=single   # 'single' or 'round_robin'
DELIVERY_AUTO_ASSIGN_DRIVER_ID=        # Optional: specific driver UUID to always use
```

**Strategies:**
- `single`: Always assign to the first active driver (ideal for single-driver MVP)
- `round_robin`: Distribute orders evenly among drivers (for multi-driver operations)

### API Response with Assignment

```json
{
  "data": {
    "id": "019b9a30-f892-...",
    "status": "pending",
    "total_km": 15.5,
    "estimated_cost": 7.75,
    "assigned_driver": {
      "trip_id": "019b9a30-f8a2-...",
      "driver_name": "Ahmad Driver",
      "assigned_at": "2026-01-07T20:47:27+00:00"
    },
    "destinations": [...]
  }
}
```

### Files
- `app/Services/TripAssignment/AutoAssignmentService.php` - Assignment logic
- `config/delivery.php` - Configuration settings

### Future: Manual Assignment
When you have multiple drivers, you can:
1. Set `DELIVERY_AUTO_ASSIGN=false`
2. Build admin panel for manual trip assignment
3. Or use `round_robin` strategy for automatic distribution

---

## Price Tracking (Items & Amounts)

Track prices at destination and item level for COD (Cash on Delivery) operations.

### Destination-Level
- `amount_to_collect`: Total amount driver should collect at this stop

### Item-Level
- `unit_price`: Price per unit of item
- `quantity_ordered`: How many units ordered
- `line_total`: Computed (unit_price × quantity_ordered)
- `quantity_delivered`: How many actually delivered
- `delivered_total`: Computed (unit_price × quantity_delivered)

### Example API Request
```json
{
  "destinations": [{
    "external_id": "ORDER-001",
    "address": "123 Main St, Amman",
    "lat": 31.9539,
    "lng": 35.9106,
    "contact_name": "Ahmad Shop",
    "contact_phone": "+962791234567",
    "amount_to_collect": 125.50,
    "items": [
      {
        "order_item_id": "ITEM-001",
        "name": "Baklava Box",
        "unit_price": 35.00,
        "quantity_ordered": 2
      },
      {
        "order_item_id": "ITEM-002",
        "name": "Kunafa Tray",
        "unit_price": 27.75,
        "quantity_ordered": 2
      }
    ]
  }]
}
```

### Driver App Display
- Shows amount to collect per destination
- Shows item list with prices and quantities
- Tracks partial deliveries (quantity_delivered vs quantity_ordered)

---

## Shop Management & Waste Tracking

Persistent shop locations with waste collection tracking for expired/returned items.

### Architecture

**Pull Model**: Transportation MVP pulls shop data from Melo ERP
```
Melo ERP → POST /api/external/v1/shops/sync → Transportation MVP
```

**Push Model**: Waste data pushed back to Melo ERP after collection
```
Transportation MVP → POST /api/v1/waste/callback → Melo ERP Webhook
```

### Shops Table

Persistent shop locations (not per-order):
```sql
shops
├── id (UUID)
├── business_id (FK)
├── external_shop_id (unique per business)
├── name, address, lat, lng
├── contact_name, contact_phone
├── track_waste (boolean)
├── is_active
├── last_synced_at
└── sync_metadata (JSON)
```

### Waste Collection Tables

Track waste items per shop per collection event:
```sql
waste_collections
├── id (UUID)
├── shop_id (FK)
├── trip_id (FK, nullable - for waste_collection trips)
├── driver_id (FK)
├── business_id (FK)
├── collection_date
├── collected_at (nullable)
└── driver_notes

waste_collection_items
├── id (UUID)
├── waste_collection_id (FK)
├── destination_item_id (FK, nullable)
├── order_item_id
├── product_name
├── quantity_delivered
├── delivered_at, expires_at
├── pieces_waste
├── pieces_sold (GENERATED: quantity_delivered - pieces_waste)
└── notes
```

### External API Endpoints (`/api/external/v1`)

All require `X-API-Key` header. Used by Melo ERP integration.

**Shop Management**
```
POST   /api/external/v1/shops/sync              - Bulk sync shops
GET    /api/external/v1/shops                   - List all shops
GET    /api/external/v1/shops/{externalShopId}  - Get shop details
PUT    /api/external/v1/shops/{externalShopId}  - Update shop
DELETE /api/external/v1/shops/{externalShopId}  - Deactivate shop
```

**Waste Collection**
```
GET    /api/external/v1/waste/expected          - Get shops with expected waste
POST   /api/external/v1/waste/expected          - Set expected waste dates
```

### Driver API Endpoints (`/api/v1/driver`)

Driver app uses these endpoints to log waste.

```
GET    /api/v1/driver/shops/{shop}/waste-expected             - Get uncollected waste items
POST   /api/v1/driver/trips/{trip}/shops/{shop}/waste-collected - Log waste
```

### Waste Collection Flow

1. **Shop Sync** (Daily 2 AM)
   - Melo ERP → POST `/api/external/v1/shops/sync` with shop list
   - Creates/updates shops in Transportation MVP
   - Drivers see shops in their app

2. **Expected Waste** (Daily 3 AM)
   - Transportation MVP calculates shops with expected waste
   - Creates `waste_collection` trips and assigns to drivers
   - Drivers see waste collection tasks

3. **Driver Logs Waste**
   - Driver opens shop details
   - Clicks "Log Waste" → Opens dialog
   - Adjusts quantities for each item
   - Enters optional notes
   - Submits → API validates waste ≤ delivered
   - `pieces_sold` auto-calculated = delivered - waste

4. **Callback Sent** (Async via queue)
   - SendWasteCallbackJob dispatched
   - Sends waste data to Melo ERP webhook
   - 5 retries with exponential backoff: [10s, 30s, 1m, 2m, 5m]
   - Includes all item details with sold/waste split

### Services

**ShopSyncService** (`app/Services/Shop/ShopSyncService.php`)
- `syncShops(Business, array, options)` - Bulk upsert
- `findAndLinkShop(Business, externalShopId)` - Get active shop
- `setExpectedWasteDates(Business, array)` - Mark shops with expected waste

**WasteCollectionService** (`app/Services/Waste/WasteCollectionService.php`)
- `calculateSoldQuantity(Shop, itemId, from, to)` - Sold calculation
- `getShopWasteReport(Shop, from, to)` - Waste statistics
- `getExpectedWaste(?businessId)` - Shops with expected waste

**WasteCallbackService** (`app/Services/Callback/WasteCallbackService.php`)
- `sendWasteCallback(WasteCollection)` - Async via queue
- `sendWasteCallbackSync(WasteCollection)` - Synchronous (testing)
- `buildCallbackPayload(WasteCollection)` - Payload construction

### Callback Format

```json
{
  "event": "waste_collected",
  "shop_id": "SHOP-001",
  "shop_name": "Ahmad's Supermarket",
  "collection_date": "2026-01-09",
  "collected_at": "2026-01-09T14:30:00Z",
  "collected_by_user_id": 789,
  "waste_items": [
    {
      "order_item_id": "ITEM-456",
      "product_name": "Baklava Box",
      "quantity_delivered": 10,
      "pieces_returned": 3,
      "pieces_sold": 7,
      "waste_date": "2026-01-01",
      "notes": "Packaging damaged"
    }
  ]
}
```

### Flutter Integration

**Models** (`lib/features/shops/data/models/`)
- `ShopModel` - Shop with waste context
- `WasteCollectionModel` - Collection event
- `WasteItemModel` - Individual waste item

**Repository** (`lib/features/shops/data/shops_repository.dart`)
- `getExpectedWaste(shopId)` - Fetch uncollected waste
- `logWasteCollection(tripId, shopId, items, notes)` - Submit waste

**Providers** (`lib/features/shops/providers/shops_provider.dart`)
- `shopsRepositoryProvider` - Dependency injection
- `expectedWasteProvider(shopId)` - Fetch waste data
- `wasteCollectionProvider` - Manage logging state
- `wasteItemsEditingProvider` - Build waste before submit
- `wastePercentageProvider` - Calculated stats

**Widgets** (`lib/features/shops/presentation/widgets/`)
- `ShopCard` - Display shop with waste status
- `WasteCollectionDialog` - Log waste items

### Key Files

**Backend**
```
app/Models/
  ├── Shop.php
  ├── WasteCollection.php
  └── WasteCollectionItem.php

app/Services/Shop/ShopSyncService.php
app/Services/Waste/WasteCollectionService.php
app/Services/Callback/WasteCallbackService.php
app/Jobs/SendWasteCallbackJob.php
app/Http/Controllers/Api/External/V1/ShopController.php
app/Http/Controllers/Api/External/V1/WasteCollectionController.php

routes/api/external/v1.php
```

**Flutter**
```
lib/features/shops/data/
  ├── models/shop_model.dart
  ├── models/waste_collection_model.dart
  ├── models/waste_item_model.dart
  └── shops_repository.dart

lib/features/shops/providers/shops_provider.dart
lib/features/shops/presentation/widgets/
  ├── shop_card.dart
  └── waste_collection_dialog.dart
```

**Documentation**
```
docs/
├── melo-erp-integration-guide.md        - Phase-by-phase setup
├── api-specifications.md                - Complete API docs
├── postman-collection-external-api.json - Ready-to-import collection
└── testing-guide.md                     - Test scenarios & results
```

---

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
- Can be `delivery` type (normal orders) or `waste_collection` type (waste pickup)

### Shops
Persistent shop locations (not per-order):
- Synced from Melo ERP via external API
- Address with GPS coordinates
- Contact information
- `track_waste` flag enables waste collection
- Multiple deliveries and waste collections per shop over time

### Waste Collections
Waste collection events at a shop:
- Links shop, trip (if part of waste collection trip), driver
- Collection date and timestamp when collected
- Driver notes for observations
- Contains multiple waste items

### Waste Items
Individual items with waste tracking:
- Links to original delivery item (optional)
- Quantity delivered vs quantity as waste
- Auto-calculated `pieces_sold = quantity_delivered - pieces_waste`
- Expiration date for expired items
- Notes about waste reason (packaging damaged, expired, etc.)

### Ledger (Double-Entry Accounting)
Independent from ERP. Tracks:
- Trip revenue (billing to businesses)
- Waste collection revenue/credits
- Fuel costs
- Driver payments
- Vehicle maintenance

## Critical Files

### Backend Core
- `app/Http/Controllers/Api/V1/DeliveryRequestController.php` - ERP order intake (5 endpoints)
- `app/Http/Controllers/Api/V1/DriverController.php` - Driver app endpoints (13 endpoints)
- `app/Http/Controllers/Api/V1/TripAssignmentController.php` - Trip dispatch (3 endpoints)
- `app/Http/Controllers/Api/V1/AuthController.php` - OAuth2 authentication (5 endpoints)
- `app/Services/GoogleMaps/RouteOptimizer.php` - Google Directions API with caching
- `app/Services/GoogleMaps/DistanceCalculator.php` - Distance Matrix API
- `app/Services/Pricing/CostCalculator.php` - KM × price calculation with tiers
- `app/Services/PayloadSchema/SchemaTransformer.php` - Dynamic field mapping
- `app/Services/Ledger/LedgerService.php` - Double-entry accounting
- `app/Services/Callback/DeliveryCallbackService.php` - Async callback dispatcher
- `app/Services/Callback/CallbackService.php` - HTTP callback sender
- `app/Models/DeliveryRequest.php` - Core delivery model
- `app/Models/Destination.php` - Individual stops

### Routes
- `routes/api/v1.php` - All API endpoints (26+ routes)

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

### Key Endpoints (All Implemented)
```
# Profile & Stats
GET  /api/v1/driver/profile                     - Get driver profile
PUT  /api/v1/driver/profile                     - Update driver profile
POST /api/v1/driver/profile/photo               - Upload profile photo (R2)
GET  /api/v1/driver/stats                       - Get statistics (today/monthly/all-time)
GET  /api/v1/driver/trips/history               - Trip history with pagination

# Trip Management
GET  /api/v1/driver/trips/today                 - Today's assigned trips
GET  /api/v1/driver/trips/{trip}                - Get trip details
POST /api/v1/driver/trips/{trip}/start          - Start trip
POST /api/v1/driver/trips/{trip}/complete       - Complete entire trip

# Destination Operations
POST /api/v1/driver/trips/{trip}/destinations/{dest}/arrive    - Mark arrival
POST /api/v1/driver/trips/{trip}/destinations/{dest}/complete  - Complete delivery (triggers ERP callback)
POST /api/v1/driver/trips/{trip}/destinations/{dest}/fail      - Mark as failed
GET  /api/v1/driver/trips/{trip}/destinations/{dest}/navigate  - Get Google Maps URL
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

## Phase 1: Admin Panel & Reporting (COMPLETE)

Complete Filament admin interface for operational management.

### Implemented Resources (5 Total)
1. **Shop Management** - CRUD for persistent shop locations synced from Melo ERP
2. **Driver Management** - Driver profiles with performance metrics (KM, trips, license expiry)
3. **Business Management** - Multi-tenant configuration (API keys, callback URLs)
4. **Trip Monitoring Dashboard** - Real-time trip status, route accuracy, KM tracking
5. **Financial Dashboard** - Revenue, costs, profitability metrics with KPI charts

### Key Files
- `app/Filament/Resources/Shops/` - Shop CRUD with form validation
- `app/Filament/Resources/Drivers/` - Driver management with performance views
- `app/Filament/Resources/Businesses/` - Business configuration
- `app/Filament/Resources/Trips/` - Trip monitoring (read-only view)
- `app/Filament/Resources/Financial/` - Financial dashboard with calculated metrics

---

## Phase 2: FCM Push Notifications & Inbox UI (COMPLETE)

Real-time push notifications for drivers with persistent inbox.

### Backend Implementation (7 new endpoints)
```
POST   /api/v1/driver/notifications/register-token     - Register FCM token
GET    /api/v1/driver/notifications                     - List notifications (paginated)
GET    /api/v1/driver/notifications/unread-count        - Get unread count
GET    /api/v1/driver/notifications/unread              - Get unread only
PATCH  /api/v1/driver/notifications/{id}/read           - Mark as read
PATCH  /api/v1/driver/notifications/{id}/unread         - Mark as unread
PATCH  /api/v1/driver/notifications/mark-all-read       - Bulk mark all
DELETE /api/v1/driver/notifications/{id}                - Delete notification
```

### Notification Types
- `trip_assigned` - New trip assignment with destinations count, KM, cost
- `trip_reassigned` - Trip reassignment notification
- `payment_received` - Payment deposit notification
- `action_required` - Generic action request

### Flutter Inbox UI
- **Notifications Tab** - All notifications with unread badge, pull-to-refresh
- **Actions Tab** - Only pending action notifications (filtered)
- **Automatic Sync** - Notifications auto-update when new trips assigned
- **Navigation** - Tap notification to view trip details or earnings

### Key Files
- `app/Models/Notification.php` - Notification model with state management
- `app/Services/Notification/NotificationService.php` - Notification creation/dispatch
- `app/Jobs/SendFcmNotificationJob.php` - FCM sending with 5-retry exponential backoff
- `flutter_app/lib/features/notifications/` - Flutter inbox UI, models, repository

### Auto-Notification on Trip Assignment
When trip assigned via `/api/v1/trips/assign`:
```
1. Trip created and assigned to driver
2. Notification automatically created
3. FCM job dispatched if driver has FCM token
4. Driver receives push notification
5. App stores in local database
6. Sync status tracked (pending → sent → read)
```

---

## Phase 3: Offline Support & Sync (DESIGN COMPLETE, IMPLEMENTATION PENDING)

Comprehensive offline-first architecture enabling drivers to work without connectivity.

### What Works Offline
| Operation | Offline | Sync | Status |
|-----------|---------|------|--------|
| View trips | ✅ Cached | N/A | Works offline |
| Start trip | ✅ | ✅ Queued | Syncs on reconnect |
| Arrive at destination | ✅ | ✅ Queued | Syncs on reconnect |
| **Complete delivery** | ✅ | ✅ Queued | **CRITICAL** |
| **Log waste collection** | ✅ | ✅ Queued | **CRITICAL** |
| View history | Partial | N/A | Only synced trips |
| Receive notifications | Via push | ✅ | Queued when offline |

### Architecture Overview
```
Offline-First Data Flow:
UI → Provider → Repository
  ├─ Online: API → Backend → Cache → Return
  └─ Offline: Local DB → Queue operation → Return optimistically

Sync Manager:
  ├─ Detects connectivity changes (connectivity_plus)
  ├─ Maintains operation queue in SQLite
  ├─ Auto-syncs on reconnect with retry logic
  ├─ Handles conflicts (version tracking)
  └─ Shows sync progress in UI
```

### Implementation Plan (5 Phases, 4-5 weeks)

**Phase 1: Foundation** (Week 1-2) - Priority: CRITICAL
- Add `sqflite` + `connectivity_plus` dependencies
- Build local SQLite database schema (trips, destinations, waste, sync_queue)
- Create `LocalStorageService` abstraction
- Build `ConnectivityManager` with online/offline detection
- Modify `ApiClient` for offline-first fallback with cache

**Phase 2: Sync Queue** (Week 2-3) - Priority: HIGH
- Create `SyncManager` service
- Build `SyncQueueRepository` (persistent operation queue)
- Implement operation enqueueing with priority
- Add sync worker (timer-based, auto-retry)
- Retry logic with exponential backoff (10s → 30s → 1m → 2m → 5m)

**Phase 3: Repositories** (Week 3) - Priority: HIGH
- Update `TripsRepository` for offline-first pattern
- Update `DestinationRepository`
- Add optimistic UI updates
- Add sync status indicators (pending_sync, synced, failed)

**Phase 4: Conflict Resolution** (Week 4) - Priority: MEDIUM
- Implement version tracking in cache
- Build conflict detector (local vs remote)
- Add conflict resolution UI
- Test multi-concurrent changes

**Phase 5: UI/UX** (Week 4-5) - Priority: HIGH
- Offline status indicator (AppBar badge)
- Sync progress display (% complete)
- Manual "Sync Now" button
- Stale data warnings
- Test on low-end Android devices

### Design Document
See `docs/offline-sync-design.md` for:
- Complete database schema with migrations
- Service layer design
- Data flow examples
- Conflict resolution strategies
- Test strategy
- Performance considerations
- Detailed rollout plan

### Test Coverage
- **55+ tests created** for critical operations (Phase 1 & 2)
- **Test Coverage**: 92%+ of critical paths
- See `docs/test-coverage-summary.md` for complete metrics

---

## Code Style & Best Practices

1. **Always use Form Requests** for validation
2. **Always use API Resources** for response transformation
3. **Use services** for complex business logic, not controllers
4. **Cache Google Maps responses** when possible
5. **Use payload schemas** for all business integrations (never hardcode field names)
6. **Track all KM via GPS** for accurate billing
7. **Write tests first** when possible (TDD) - focus on critical paths, skip trivial
8. **Use enums** for status fields (`TripStatus`, `BusinessType`, `NotificationStatus`, etc.)
9. **Offline-first pattern** - Repository first checks local DB, then API
10. **Queue-based operations** - Use jobs for async tasks (callbacks, notifications, sync)

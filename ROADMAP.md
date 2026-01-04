# Transportation App - Development Roadmap

> **Status**: Foundation Phase
> **Last Updated**: January 2026

## Current State

- ✅ Laravel 12 + FrankenPHP/Octane setup
- ✅ Authentication (OAuth2 Password Grant via Passport)
- ✅ User model with factory
- ✅ Telescope & Horizon configured
- ✅ Docker + Docker Compose
- ⬜ Domain models (0/9)
- ⬜ API endpoints (auth only)
- ⬜ Google Maps integration
- ⬜ Admin panel (Filament)
- ⬜ Flutter driver app

---

## Development Approach

**Principle**: Build the core business logic first, then layer on integrations.

```
Phase 1: Core Models & CRUD     →  Database foundation
Phase 2: Business Logic         →  Pricing, route optimization
Phase 3: External Integrations  →  Google Maps, ERP callbacks
Phase 4: Admin Panel            →  Filament resources
Phase 5: Flutter Driver App     →  Mobile experience
```

---

## Phase 1: Core Models & CRUD (Foundation)

Build the domain models with migrations, factories, and basic CRUD APIs.

### 1.1 Business Model (Client Companies)
```
Businesses receive delivery requests via our API.

Fields:
- id (uuid)
- name
- business_type: enum(bulk_order, pickup)
- api_key (for auth)
- callback_url (nullable - where we POST delivery status)
- callback_api_key (nullable - auth for callbacks)
- is_active: boolean
- timestamps
```

**Tasks:**
- [ ] Migration: `create_businesses_table`
- [ ] Model: `Business.php` with relationships
- [ ] Factory: `BusinessFactory.php`
- [ ] Form Request: `StoreBusinessRequest`, `UpdateBusinessRequest`
- [ ] Resource: `BusinessResource`
- [ ] Controller: `BusinessController` (index, store, show, update, destroy)
- [ ] Routes in `api/v1.php`
- [ ] Feature tests

---

### 1.2 Vehicle Model
```
Fleet vehicles (MVP: 1 vehicle - VW Caddy 2019).

Fields:
- id (uuid)
- make (e.g., "Volkswagen")
- model (e.g., "Caddy")
- year (e.g., 2019)
- license_plate
- total_km_driven: decimal (lifetime)
- monthly_km_app: decimal (reset monthly)
- acquisition_date: date
- is_active: boolean
- timestamps
```

**Tasks:**
- [ ] Migration: `create_vehicles_table`
- [ ] Model: `Vehicle.php`
- [ ] Factory: `VehicleFactory.php`
- [ ] Form Request: `StoreVehicleRequest`, `UpdateVehicleRequest`
- [ ] Resource: `VehicleResource`
- [ ] Controller: `VehicleController`
- [ ] Feature tests

---

### 1.3 Driver Model
```
Drivers operate vehicles and execute trips.

Fields:
- id (uuid)
- user_id (FK to users - for auth)
- vehicle_id (FK - assigned vehicle, nullable)
- phone
- license_number
- is_active: boolean
- timestamps
```

**Tasks:**
- [ ] Migration: `create_drivers_table`
- [ ] Model: `Driver.php` with User & Vehicle relationships
- [ ] Factory: `DriverFactory.php`
- [ ] Form Request: `StoreDriverRequest`, `UpdateDriverRequest`
- [ ] Resource: `DriverResource`
- [ ] Controller: `DriverController`
- [ ] Feature tests

---

### 1.4 PricingTier Model
```
Cost calculation: total_km × price_per_km

Fields:
- id (uuid)
- name (e.g., "Standard", "Premium")
- business_type: enum(bulk_order, pickup) - nullable for default
- price_per_km: decimal(8,4)
- effective_date: date
- is_active: boolean
- timestamps
```

**Tasks:**
- [ ] Migration: `create_pricing_tiers_table`
- [ ] Model: `PricingTier.php`
- [ ] Factory: `PricingTierFactory.php`
- [ ] Resource: `PricingTierResource`
- [ ] Controller: `PricingTierController`
- [ ] Seeder: Default pricing tier
- [ ] Feature tests

---

### 1.5 DeliveryRequest Model
```
A batch of destinations to deliver (from one business).

Fields:
- id (uuid)
- business_id (FK)
- status: enum(pending, accepted, in_progress, completed, cancelled)
- total_km: decimal (from route optimization)
- estimated_cost: decimal
- actual_km: decimal (nullable - from GPS tracking)
- actual_cost: decimal (nullable)
- optimized_route: json (polyline from Google Maps)
- notes: text (nullable)
- requested_at: timestamp
- completed_at: timestamp (nullable)
- timestamps
```

**Tasks:**
- [ ] Migration: `create_delivery_requests_table`
- [ ] Model: `DeliveryRequest.php` with Business, Destinations relationships
- [ ] Factory: `DeliveryRequestFactory.php`
- [ ] Enum: `DeliveryRequestStatus.php`
- [ ] Form Request: `StoreDeliveryRequestRequest`
- [ ] Resource: `DeliveryRequestResource`
- [ ] Controller: `DeliveryRequestController`
- [ ] Feature tests

---

### 1.6 Destination Model
```
Individual stops within a delivery request.

Fields:
- id (uuid)
- delivery_request_id (FK)
- external_id: string (from client ERP, e.g., order-123)
- address: string
- lat: decimal(10,7)
- lng: decimal(10,7)
- sequence_order: integer (optimized order)
- status: enum(pending, arrived, completed, failed)
- notes: text (nullable)
- arrived_at: timestamp (nullable)
- completed_at: timestamp (nullable)
- timestamps
```

**Tasks:**
- [ ] Migration: `create_destinations_table`
- [ ] Model: `Destination.php` with DeliveryRequest relationship
- [ ] Factory: `DestinationFactory.php`
- [ ] Enum: `DestinationStatus.php`
- [ ] Resource: `DestinationResource`
- [ ] Feature tests

---

### 1.7 Trip Model
```
Driver execution of a delivery request.

Fields:
- id (uuid)
- delivery_request_id (FK)
- driver_id (FK)
- vehicle_id (FK)
- status: enum(not_started, in_progress, completed, cancelled)
- started_at: timestamp (nullable)
- completed_at: timestamp (nullable)
- actual_km: decimal (nullable - from GPS)
- timestamps
```

**Tasks:**
- [ ] Migration: `create_trips_table`
- [ ] Model: `Trip.php` with relationships
- [ ] Factory: `TripFactory.php`
- [ ] Enum: `TripStatus.php`
- [ ] Resource: `TripResource`
- [ ] Feature tests

---

### 1.8 BusinessPayloadSchema Model
```
Dynamic API format mapping per business.

Fields:
- id (uuid)
- business_id (FK, unique)
- request_schema: json (maps incoming fields)
- callback_schema: json (maps outgoing callback fields)
- timestamps

Example request_schema:
{
  "external_id": "order_id",
  "address": "delivery_address",
  "lat": "coordinates.latitude",
  "lng": "coordinates.longitude"
}
```

**Tasks:**
- [ ] Migration: `create_business_payload_schemas_table`
- [ ] Model: `BusinessPayloadSchema.php`
- [ ] Factory: `BusinessPayloadSchemaFactory.php`
- [ ] Service: `PayloadSchemaTransformer.php`
- [ ] Feature tests

---

### 1.9 Ledger System (Double-Entry Accounting)
```
Independent financial tracking.

Tables:
1. ledger_accounts (Chart of Accounts)
   - id, code, name, type(asset/liability/equity/revenue/expense), parent_id, is_active

2. journal_entries (Transaction headers)
   - id, reference_type, reference_id, entry_date, description, created_by

3. journal_entry_items (Debit/Credit lines)
   - id, journal_entry_id, ledger_account_id, entity_type, entity_id, debit, credit, description
```

**Tasks:**
- [ ] Migration: `create_ledger_accounts_table`
- [ ] Migration: `create_journal_entries_table`
- [ ] Migration: `create_journal_entry_items_table`
- [ ] Model: `LedgerAccount.php`
- [ ] Model: `JournalEntry.php`
- [ ] Model: `JournalEntryItem.php`
- [ ] Enum: `LedgerAccountType.php`
- [ ] Service: `LedgerService.php`
- [ ] Seeder: Chart of Accounts
- [ ] Feature tests

---

## Phase 2: Business Logic Services

### 2.1 Pricing Service
```php
// Calculate cost for a delivery request
$cost = $pricingService->calculate($totalKm, $business);
```

**Tasks:**
- [ ] Service: `PricingService.php`
- [ ] Unit tests for pricing calculations

---

### 2.2 Route Optimizer Service
```php
// Optimize route via Google Directions API
$result = $routeOptimizer->optimize($destinations);
// Returns: optimized_order, total_distance, polyline
```

**Tasks:**
- [ ] Config: `config/google-maps.php`
- [ ] Service: `GoogleMaps/RouteOptimizer.php`
- [ ] Unit tests (with mocked API responses)

---

### 2.3 Distance Calculator Service
```php
// Calculate distances via Google Distance Matrix API
$distances = $distanceCalculator->calculate($origins, $destinations);
```

**Tasks:**
- [ ] Service: `GoogleMaps/DistanceCalculator.php`
- [ ] Unit tests

---

### 2.4 Payload Schema Transformer
```php
// Transform incoming request using business's schema
$normalized = $transformer->transformIncoming($data, $schema);

// Transform outgoing callback
$callback = $transformer->transformCallback($destination, $schema);
```

**Tasks:**
- [ ] Service: `PayloadSchema/SchemaTransformer.php`
- [ ] Unit tests

---

### 2.5 Delivery Callback Service
```php
// Send callback to business when destination is completed
$callbackService->sendDeliveryCallback($destination);
```

**Tasks:**
- [ ] Service: `Callback/DeliveryCallbackService.php`
- [ ] Job: `SendDeliveryCallbackJob.php` (queued)
- [ ] Unit tests

---

## Phase 3: API Endpoints

### 3.1 Business API (ERP Integration)

```
POST   /api/v1/delivery-requests          Create delivery request
GET    /api/v1/delivery-requests/{id}     Get request status
GET    /api/v1/delivery-requests/{id}/route  Get optimized route
DELETE /api/v1/delivery-requests/{id}     Cancel request
```

**Tasks:**
- [ ] DeliveryRequestController methods
- [ ] Business authentication middleware
- [ ] Feature tests

---

### 3.2 Driver API (Flutter App)

```
GET    /api/v1/driver/trips/today                    Today's assigned trips
GET    /api/v1/driver/trips/{id}                     Trip details
POST   /api/v1/driver/trips/{id}/start               Start trip
POST   /api/v1/driver/trips/{id}/arrive/{dest}       Arrived at destination
POST   /api/v1/driver/trips/{id}/complete/{dest}     Complete delivery
POST   /api/v1/driver/trips/{id}/fail/{dest}         Mark failed delivery
GET    /api/v1/driver/navigation/{dest}              Get navigation URL
POST   /api/v1/driver/trips/{id}/location            Update GPS location
POST   /api/v1/driver/trips/{id}/complete            Complete entire trip
```

**Tasks:**
- [ ] Controller: `Driver/TripController.php`
- [ ] Controller: `Driver/NavigationController.php`
- [ ] Middleware: Driver authentication check
- [ ] Feature tests

---

### 3.3 Admin API

```
Standard CRUD for all resources:
- /api/v1/admin/businesses
- /api/v1/admin/vehicles
- /api/v1/admin/drivers
- /api/v1/admin/pricing-tiers
- /api/v1/admin/delivery-requests
- /api/v1/admin/trips
- /api/v1/admin/ledger/accounts
- /api/v1/admin/ledger/entries
```

**Tasks:**
- [ ] Admin namespace controllers
- [ ] Admin role/permission check
- [ ] Feature tests

---

## Phase 4: Admin Panel (Filament)

### 4.1 Filament Resources

**Tasks:**
- [ ] Resource: BusinessResource
- [ ] Resource: VehicleResource
- [ ] Resource: DriverResource
- [ ] Resource: PricingTierResource
- [ ] Resource: DeliveryRequestResource
- [ ] Resource: TripResource
- [ ] Resource: LedgerAccountResource
- [ ] Resource: JournalEntryResource

---

### 4.2 Filament Dashboard Widgets

**Tasks:**
- [ ] Widget: Today's trips overview
- [ ] Widget: Revenue this month
- [ ] Widget: Active deliveries map
- [ ] Widget: Driver status

---

## Phase 5: Flutter Driver App

### 5.1 App Setup

**Tasks:**
- [ ] Flutter project structure
- [ ] API client setup
- [ ] Auth flow (login/logout)
- [ ] State management (Riverpod/Provider)

---

### 5.2 Core Screens

**Tasks:**
- [ ] Login screen
- [ ] Today's trips list
- [ ] Trip details with route
- [ ] Destination details
- [ ] Navigation integration (open Google Maps)
- [ ] Mark arrived/completed actions
- [ ] GPS tracking service

---

## Testing Strategy

**For each model/feature:**
```
1. Factory         → Fake data generation
2. Feature tests   → Full HTTP request/response cycle
3. Unit tests      → Complex service logic only
```

**Run tests frequently:**
```bash
make test              # All tests
make test-group name=delivery-requests  # By group
```

---

## Quick Reference: Make Commands

```bash
# Model creation
make model name=Business flags="-mfs"

# V1 API components
make v1-controller name=BusinessController
make v1-request name=StoreBusinessRequest
make v1-resource name=BusinessResource
make v1-crud name=Business  # All of the above

# Database
make migrate
make fresh
make tinker

# Testing
make test
make test-filter t=BusinessTest

# Filament
php artisan make:filament-resource Business --generate
```

---

## Immediate Next Steps

Start with Phase 1.1 (Business model) and work through sequentially:

```bash
# 1. Create the model with migration and factory
make model name=Business flags="-mfs"

# 2. Edit the migration
# 3. Run migration
make migrate

# 4. Create API components
make v1-crud name=Business

# 5. Write feature tests
# 6. Run tests
make test
```

Then move to 1.2 (Vehicle), 1.3 (Driver), etc.

---

## Success Criteria

**Phase 1 Complete When:**
- [ ] All 9 model groups have migrations, factories, tests passing
- [ ] Database schema matches domain model in README

**Phase 2 Complete When:**
- [ ] Can calculate delivery cost given destinations
- [ ] Can get optimized route from Google Maps (mocked in tests)
- [ ] Ledger service can record journal entries

**Phase 3 Complete When:**
- [ ] ERP can POST delivery requests and receive callbacks
- [ ] Driver can see trips, start, arrive, complete via API

**Phase 4 Complete When:**
- [ ] Admin can manage all resources via Filament

**Phase 5 Complete When:**
- [ ] Driver can complete full delivery flow on mobile

---

*Focus on one phase at a time. Ship working software incrementally.*

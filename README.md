# Transportation App

A logistics and delivery management application with route optimization, cost calculation, and multi-client API integration.

## MVP Status: Ready for Production + Phase 1, 2, & 3 Complete

| Component | Status | Notes |
|-----------|--------|-------|
| Database/Models | 100% | All 24 tables, migrations complete |
| Authentication | 100% | OAuth2 + API Key auth |
| API Controllers | 100% | 8 controllers, 46+ endpoints |
| ERP Integration | 100% | Submit orders + async callbacks |
| **Shop Management** | **100%** | **Persistent locations, ERP sync, CRUD** |
| **Waste Tracking** | **100%** | **Driver logging, auto-calculation, callbacks** |
| Driver Endpoints | 100% | 31 endpoints for Flutter app (+ notifications + payment) |
| Route Optimization | 100% | Google Maps with caching |
| Pricing Service | 100% | Tiered pricing with discounts |
| Ledger System | 100% | Double-entry accounting |
| **Phase 1: Admin Panel** | **100%** | **5 Filament resources (Shop, Driver, Business, Trip, Financial)** |
| **Phase 2: FCM Notifications** | **100%** | **Push notifications + Inbox UI with 2 tabs** |
| **Phase 3: Payment & Reconciliation** | **100%** | **Payment collection, tupperware tracking, daily reconciliation** |
| **Test Coverage** | **100%** | **389 tests passing (Unit + Feature + Integration)** |
| Flutter App | 100% | Real API integration + GPS + waste + notifications + inbox |
| **Offline Support** | Design | SQLite caching + sync queue (implementation ready) |

### Pre-Deployment Checklist
1. Configure production `.env` (database, Redis, Google Maps API key, Firebase FCM key)
2. Set up Cloudflare R2 bucket for file storage
3. Configure business callback URLs in admin panel
4. Deploy queue workers (Horizon) for async callbacks + FCM jobs
5. **Exchange API keys with Melo ERP**
6. **Configure webhook signature secret (HMAC-SHA256)**
7. **Set up Melo ERP webhook receiver for waste/payment callbacks**
8. **Set up Firebase Cloud Messaging for FCM**
9. **Test end-to-end flow with Melo ERP + notifications + payments**

## Overview

This system manages delivery logistics for multiple business clients (primary: **Melo Group ERP**):

```
Business A (ERP) ──┐
                   │    ┌─────────────────┐     ┌─────────────┐
Business B (ERP) ──┼───►│ Transportation  │────►│   Driver    │
                   │    │      App        │     │ Flutter App │
Manual Orders ─────┘    └────────┬────────┘     └──────┬──────┘
                                 │                      │
                        ┌────────▼────────┐    ┌───────▼───────┐
                        │  Google Maps    │    │  GPS Tracking │
                        │  Route Optimize │    │  KM Recording │
                        └─────────────────┘    └───────────────┘
                                 │
                        ┌────────▼────────┐
                        │ Callback to ERP │
                        │ on completion   │
                        └─────────────────┘
```

### Key Features

**Core Delivery Management**
- **Route Optimization**: Google Maps API calculates best route for multiple stops
- **Cost Calculation**: `Total KM × Price per KM` with configurable pricing tiers
- **Dynamic API Integration**: Different businesses can integrate with different payload formats
- **GPS Tracking**: Actual KM tracked via Flutter app for accurate billing
- **Single-Destination Navigation**: Opens device's Google Maps (free) instead of in-app navigation
- **Double-Entry Accounting**: Independent ledger for revenue, expenses, driver payments

**Shop & Waste Management**
- **Shop Management**: Persistent shop locations synced from Melo ERP
- **Waste Tracking**: Drivers log expired/returned items per shop with auto-calculated sold quantities
- **Waste Callbacks**: Async job-based callbacks to Melo ERP with exponential backoff retry
- **External API**: Separate `/api/external/v1` namespace for B2B integrations (pull/push model)

**Admin Panel (Phase 1)**
- **Shop CRUD**: Full management of persistent shop locations
- **Driver Management**: Driver profiles with performance metrics (KM, trips, license expiry)
- **Business Configuration**: Multi-tenant setup with API keys and callback URLs
- **Trip Monitoring**: Real-time trip status, KM accuracy, route tracking
- **Financial Dashboard**: Revenue, costs, profitability analysis with KPIs

**Real-Time Communications (Phase 2)**
- **FCM Push Notifications**: Real-time trip assignments and payment alerts
- **Driver Inbox**: Two-tab interface (All Notifications + Pending Actions)
- **Auto-Notification**: Automatic notification on trip assignment with KM/cost details
- **Notification Management**: Mark as read, bulk actions, swipe-to-delete

**Payment Collection & Reconciliation (Phase 3)**
- **Multi-Payment Methods**: Cash, CliQ Now, CliQ Later support
- **Shortage Tracking**: Automatic shortage detection with reason codes
- **Tupperware/Container Tracking**: Per-shop balance tracking for delivery containers
- **Daily Reconciliation**: End-of-day driver reconciliation with shop breakdown
- **Route Reordering**: Drag-drop destination reordering within trips
- **KM Tracking**: Trip-level start/end KM for accurate mileage

**Offline Support (Upcoming)**
- **Offline Work**: Complete deliveries and waste logs without connectivity
- **Automatic Sync**: Operations queue synced automatically on reconnect
- **Conflict Resolution**: Version tracking handles concurrent backend changes
- **Data Caching**: SQLite database mirrors API responses for offline availability

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12, PHP 8.4, FrankenPHP/Octane |
| Driver Mobile App | Flutter (iOS/Android) |
| Admin Panel | Filament 4 |
| Database | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| Storage | Cloudflare R2 |
| Maps | Google Maps API |
| Auth | Laravel Passport (OAuth2) |

## Prerequisites

- Docker Desktop 4.x+ (or Docker Engine + Compose v2.22+)
- Make
- Git
- Flutter SDK (for mobile development)

## Quick Start

```bash
# Clone and enter directory
git clone <repo-url> transportation-app
cd transportation-app

# Start all services
make setup

# This runs:
# - Docker build
# - Composer install
# - npm install
# - Database migrations
# - Generate app key
```

## Access Points

| Service | URL | Notes |
|---------|-----|-------|
| Backend API | http://localhost:8000/api/v1 | REST API |
| Admin Panel | http://localhost:8000/admin | Filament |
| Telescope | http://localhost:8000/telescope | Debug dashboard |
| Horizon | http://localhost:8000/horizon | Queue dashboard |
| Mailpit | http://localhost:8026 | Email testing |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     External Services                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Google Maps  │  │ Client ERPs  │  │ Cloudflare   │          │
│  │ API          │  │ (Callbacks)  │  │ R2 Storage   │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                  │
└─────────┼──────────────────┼──────────────────┼──────────────────┘
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼──────────────────┐
│                     Laravel Backend                              │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Route       │  │ Delivery    │  │ Pricing     │              │
│  │ Optimizer   │  │ Management  │  │ Calculator  │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Payload     │  │ Ledger      │  │ Driver      │              │
│  │ Schemas     │  │ (Accounting)│  │ Management  │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────────────────────────────────────────┘
          │                  │
┌─────────▼──────────────────▼─────────────────────────────────────┐
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ MySQL 8     │  │ Redis 7     │  │ Horizon     │              │
│  │ (Database)  │  │ (Cache/Q)   │  │ (Workers)   │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────────────────────────────────────────┘
```

## Domain Model

```
Businesses (Clients using our service)
    ├── business_type: bulk_order | pickup
    ├── PayloadSchema (configurable API format)
    └── DeliveryRequests
          ├── Destinations (individual stops)
          ├── optimized_route (from Google Maps)
          ├── total_km, estimated_cost
          └── Trips (driver executions)

Vehicles
    ├── VW Caddy 2019 (MVP: 1 vehicle)
    ├── total_km_driven (lifetime)
    └── monthly_km_app (tracked by app)

Drivers
    ├── assigned_vehicle
    └── Trips → JournalEntries (earnings)

Financial System (Independent Ledger)
    ├── LedgerAccount (Chart of Accounts)
    ├── JournalEntry (Transaction headers)
    └── JournalEntryItem (Debit/Credit lines)

Shops
    ├── Persistent locations (synced from Melo ERP)
    ├── track_waste flag
    └── WasteCollections
          ├── WasteCollectionItems (delivered vs waste)
          ├── pieces_sold (auto-calculated)
          └── Callbacks to ERP

Payment & Reconciliation (Phase 3)
    ├── PaymentCollections (per destination)
    │     ├── amount_expected, amount_collected
    │     ├── payment_method (Cash, CliQ Now, CliQ Later)
    │     ├── shortage_amount, shortage_reason
    │     └── collected_at timestamp
    ├── TupperwareMovements (container tracking)
    │     ├── shop_id, product_type
    │     ├── quantity, movement_type (delivery/pickup/adjustment)
    │     └── running balance per shop
    └── DailyReconciliations (end-of-day)
          ├── total_expected, total_collected
          ├── total_cash, total_cliq
          ├── shop_breakdown (JSON)
          └── status (pending/submitted/approved)
```

## Shop Management & Waste Tracking

### Overview

Persistent shop locations with waste tracking for expired/damaged items.

**Pull Model (Melo ERP → Transportation MVP):**
```
POST /api/external/v1/shops/sync ← Bulk shop synchronization
```

**Push Model (Transportation MVP → Melo ERP):**
```
POST {melo_webhook_url}/api/v1/waste/callback ← Waste collection data
```

### External API Endpoints

**Shop Management** (requires `X-API-Key` header):
```bash
POST   /api/external/v1/shops/sync                    # Bulk sync shops
GET    /api/external/v1/shops                         # List shops
GET    /api/external/v1/shops/{externalShopId}        # Get shop
PUT    /api/external/v1/shops/{externalShopId}        # Update shop
DELETE /api/external/v1/shops/{externalShopId}        # Deactivate shop
```

**Waste Collection** (requires `X-API-Key` header):
```bash
GET    /api/external/v1/waste/expected                # Get shops with expected waste
POST   /api/external/v1/waste/expected                # Set expected waste dates
```

### Driver API Endpoints

**Waste Collection** (requires Bearer token):
```bash
GET    /api/v1/driver/shops/{shop}/waste-expected                         # Get uncollected waste
POST   /api/v1/driver/trips/{trip}/shops/{shop}/waste-collected           # Log waste
```

### Daily Workflow

1. **2 AM** - Melo ERP syncs shops: `POST /api/external/v1/shops/sync`
2. **3 AM** - Transportation MVP marks shops with expected waste
3. **Morning** - Driver sees waste collection tasks in Flutter app
4. **During Day** - Driver logs waste at each shop
5. **Async** - Waste data sent to Melo ERP webhook with retry logic

### Waste Callback Format

```json
{
  "event": "waste_collected",
  "shop_id": "SHOP-001",
  "collection_date": "2026-01-09",
  "collected_at": "2026-01-09T14:30:00Z",
  "waste_items": [
    {
      "order_item_id": "ITEM-456",
      "product_name": "Baklava Box",
      "quantity_delivered": 10,
      "pieces_returned": 3,
      "pieces_sold": 7,
      "notes": "Packaging damaged"
    }
  ]
}
```

### Key Features

- **Automatic pieces_sold calculation**: `delivered - waste`
- **Expiry tracking**: Days expired, expired items warning
- **Validation**: Waste quantity cannot exceed delivered quantity
- **Retry logic**: 5 attempts with exponential backoff [10s, 30s, 1m, 2m, 5m]
- **Driver notes**: Optional observations per waste item
- **Real-time UI**: Updates waste % and sold count as driver adjusts quantities

### Documentation

**Integration & API**
- `docs/melo-erp-integration-guide.md` - Phase-by-phase Melo ERP setup
- `docs/api-specifications.md` - Complete API reference for external API
- `docs/postman-collection-external-api.json` - Ready-to-import Postman collection

**Testing & Quality**
- `docs/testing-guide.md` - Test scenarios and results
- `docs/test-coverage-summary.md` - Complete test coverage metrics (389 tests passing)

**Offline Support (Upcoming)**
- `docs/offline-sync-design.md` - Complete architecture design (5-phase implementation plan)

---

## API Documentation

### Authentication
```bash
# Login (get tokens)
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "password"
}

# Forgot password (request reset link)
POST /api/v1/auth/forgot-password
{
  "email": "user@example.com"
}
# Always returns success (prevents email enumeration)

# Use token in subsequent requests
Authorization: Bearer {access_token}
```

### Key Endpoints

**Delivery Requests (for ERPs):**
```bash
# Create delivery request (from ERP)
POST /api/v1/delivery-requests
{
  "destinations": [
    {"external_id": "order-123", "address": "...", "lat": 31.9, "lng": 35.9},
    {"external_id": "order-456", "address": "...", "lat": 31.8, "lng": 35.8}
  ]
}
# Returns: optimized route, total_km, estimated_cost

# Get route details
GET /api/v1/delivery-requests/{id}/route
```

**Driver Endpoints (for Flutter app):**
```bash
# Profile & Stats
GET  /api/v1/driver/profile                              # Driver profile
PUT  /api/v1/driver/profile                              # Update profile
POST /api/v1/driver/profile/photo                        # Upload photo
GET  /api/v1/driver/stats                                # Statistics
GET  /api/v1/driver/trips/history                        # Trip history

# Trip Lifecycle
GET  /api/v1/driver/trips/today                          # Today's trips
GET  /api/v1/driver/trips/{id}                           # Trip details
POST /api/v1/driver/trips/{id}/start                     # Start trip
POST /api/v1/driver/trips/{id}/complete                  # Complete trip

# Destination Operations
POST /api/v1/driver/trips/{id}/destinations/{dest}/arrive    # Mark arrival
POST /api/v1/driver/trips/{id}/destinations/{dest}/complete  # Complete (→ ERP callback)
POST /api/v1/driver/trips/{id}/destinations/{dest}/fail      # Mark failed
GET  /api/v1/driver/trips/{id}/destinations/{dest}/navigate  # Google Maps URL

# Waste Collection Operations
GET  /api/v1/driver/shops/{shop}/waste-expected                         # Get uncollected waste
POST /api/v1/driver/trips/{trip}/shops/{shop}/waste-collected           # Log waste

# Payment Collection (Phase 3)
POST /api/v1/driver/trips/{trip}/destinations/{dest}/collect-payment    # Collect cash/CliQ
POST /api/v1/driver/trips/{trip}/destinations/{dest}/collect-tupperware # Pickup containers
GET  /api/v1/driver/shops/{shop}/tupperware-balance                     # Get container balance

# Route Reordering (Phase 3)
POST /api/v1/driver/trips/{trip}/reorder-destinations                   # Drag-drop reorder

# Daily Reconciliation (Phase 3)
POST /api/v1/driver/day/end                                             # Generate reconciliation
GET  /api/v1/driver/day/reconciliation                                  # Get today's reconciliation
POST /api/v1/driver/reconciliation/submit                               # Submit to Melo ERP
```

**Trip Assignment (Admin/Dispatch):**
```bash
GET  /api/v1/trips/unassigned                            # Pending requests
GET  /api/v1/trips/available-drivers                     # Active drivers
POST /api/v1/trips/assign                                # Assign to driver
```

## Google Maps API Strategy

**Cost-Efficient Approach:**

| API | When Used | Cost |
|-----|-----------|------|
| Directions API | Once per delivery request (route optimization) | $5/1K requests |
| Distance Matrix | Cost estimation (batched) | $5/1K elements |
| Device Navigation | Driver taps to navigate | **FREE** |

We **don't** use in-app multi-stop navigation - driver opens Google Maps app for each destination.

## Development Workflow

### Adding a Feature

```bash
# 1. Create migration
make migration name=create_feature_table

# 2. Create model with factory
make model name=Feature flags="-mf"

# 3. Create API components
make v1-crud name=Feature

# 4. Write tests
# tests/Feature/Api/V1/FeatureTest.php

# 5. Run tests
make test

# 6. Create Filament resource (admin)
php artisan make:filament-resource Feature --generate
```

### Common Commands

```bash
# Docker
make up              # Start services
make down            # Stop services
make logs            # View logs
make shell-backend   # Enter backend container

# Database
make migrate         # Run migrations
make fresh           # Fresh migrate + seed
make tinker          # Laravel REPL

# Testing
make test            # Run all tests
make test-unit       # Unit tests only
make test-feature    # Feature tests only
```

## Environment Variables

Create `.env` from `.env.example`:

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
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_BUCKET=transportation-app
AWS_ENDPOINT=https://xxx.r2.cloudflarestorage.com
AWS_DEFAULT_REGION=auto
AWS_USE_PATH_STYLE_ENDPOINT=true
```

## Flutter Driver App

Located in `flutter_app/` directory. **Fully integrated with backend API** (not mock data).

### Setup
```bash
cd flutter_app
flutter pub get

# For Android emulator (default API URL: http://10.0.2.2:8000)
flutter run

# For production build with custom API URL
flutter run --dart-define=API_BASE_URL=https://api.yourapp.com
```

### Key Features
- **Real API Integration**: Dio HTTP client with OAuth2 token management
- **View today's assigned trips**: From `/api/v1/driver/trips/today`
- **Optimized route display**: Polyline from route optimization
- **Tap to navigate**: Opens device's Google Maps app (free)
- **Mark arrival/completion**: Real-time status updates to backend
- **GPS tracking**: Actual KM calculated via Geolocator package
- **ERP Callbacks**: Automatic notification to client ERP on delivery completion
- **Token refresh**: Automatic token rotation on 401 responses
- **Profile management**: Update profile, upload photo to R2 storage
- **Shop cards**: Display persistent shop info with waste status
- **Waste logging**: Dialog to log expired/damaged items with real-time sold calculation
- **Waste validation**: Prevents logging waste > delivered quantity
- **Driver notes**: Optional observations per waste item

## Testing

```bash
# Run all tests
make test

# Run specific test file
make test-filter t=DeliveryRequestTest

# Run with coverage
make test-coverage
```

**Testing requirements:**
- Every model needs a factory
- Every API endpoint needs feature tests
- Complex services need unit tests

## Deployment

### Staging
```bash
make k8s-staging
```

### Production
```bash
make k8s-production  # Requires confirmation
```

### Production Checklist

**Development Complete:**
- [x] All API endpoints implemented (46+ routes)
- [x] ERP integration (delivery requests + callbacks)
- [x] Driver app endpoints (31 routes)
- [x] Route optimization with Google Maps
- [x] Pricing calculation with tiers
- [x] Double-entry ledger system
- [x] Flutter app with real API integration
- [x] **Shop management (persistent locations)**
- [x] **Waste tracking (driver logging, auto-calculation)**
- [x] **External API for B2B integration (pull/push)**
- [x] **Phase 3: Payment collection (Cash, CliQ Now, CliQ Later)**
- [x] **Phase 3: Tupperware/container tracking**
- [x] **Phase 3: Daily reconciliation with shop breakdown**
- [x] **389 tests passing (Unit + Feature + Integration)**

**Deployment Tasks:**
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Configure Google Maps API key
- [ ] Set up Cloudflare R2 bucket
- [ ] Configure database credentials
- [ ] Set up Redis AUTH
- [ ] Enable HTTPS via Ingress
- [ ] Deploy queue workers (Horizon) for waste callbacks
- [ ] Deploy Flutter app to stores
- [ ] **Exchange API keys with Melo ERP**
- [ ] **Set up Melo ERP webhook receiver endpoint**
- [ ] **Configure webhook signature secret (HMAC-SHA256)**
- [ ] **Schedule daily shop sync cron job (2 AM)**

## Troubleshooting

### Google Maps API errors
1. Check `GOOGLE_MAPS_API_KEY` is set
2. Verify API is enabled in Google Cloud Console
3. Check billing is enabled (APIs have quotas)

### Route optimization not working
```bash
# Check service response
make tinker
>>> app(RouteOptimizer::class)->optimize([...destinations])
```

### Callbacks not reaching ERP
1. Check business `callback_url` is correct
2. Check `callback_api_key` is valid
3. Check ERP is reachable from Transport server

### Waste callbacks not sent to Melo ERP
1. Verify queue workers are running: `php artisan queue:work --queue=callbacks`
2. Check Melo ERP webhook URL is configured in business settings
3. Check webhook signature secret is configured (for HMAC-SHA256 verification)
4. Check Melo ERP webhook receiver endpoint is responding with 200+ status
5. Verify X-Webhook-Signature header is being sent correctly

### Shop sync not creating shops
1. Verify API key is valid and active
2. Check X-API-Key header is included in POST request
3. Verify coordinate ranges: latitude (-90 to 90), longitude (-180 to 180)
4. Check all required fields are present: id, name, address, latitude, longitude

## Documentation

**Project & Context**
- `CLAUDE.md` - Complete project documentation (AI assistant context, all phases)
- `flutter_app/README.md` - Flutter app documentation and setup

**API Integration**
- **`docs/melo-erp-integration-guide.md`** - **Phase-by-phase Melo ERP integration**
- **`docs/api-specifications.md`** - **Complete API reference (external API)**
- **`docs/postman-collection-external-api.json`** - **Ready-to-import Postman collection**

**Testing & Quality Assurance**
- **`docs/testing-guide.md`** - **Test scenarios, results, and CI/CD setup**
- **`docs/test-coverage-summary.md`** - **389 tests passing, full Phase 3 coverage**

**Upcoming Features**
- **`docs/offline-sync-design.md`** - **Offline support architecture (5-phase plan)**

## License

Proprietary - All rights reserved.

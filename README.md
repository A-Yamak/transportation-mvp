# Transportation App

A logistics and delivery management application with route optimization, cost calculation, and multi-client API integration.

## MVP Status: ~30% Complete

| Component | Status | Notes |
|-----------|--------|-------|
| Database/Models | 100% | Schema complete |
| Authentication | 100% | OAuth2 working |
| API Controllers | 5% | Only AuthController |
| ERP Integration | 0% | **BLOCKING** - Priority 1 |
| Driver Endpoints | 0% | Priority 2 |
| Flutter UI | 85% | Stuck on mock data |

### MVP Priority Order
1. **ERP Integration** - Receive orders from Melo ERP, send delivery callbacks
2. **Driver Endpoints** - Connect Flutter app to real API
3. **Ledger/Billing** - Lower priority, post-MVP

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

- **Route Optimization**: Google Maps API calculates best route for multiple stops
- **Cost Calculation**: `Total KM × Price per KM` with configurable pricing tiers
- **Dynamic API Integration**: Different businesses can integrate with different payload formats
- **GPS Tracking**: Actual KM tracked via Flutter app for accurate billing
- **Single-Destination Navigation**: Opens device's Google Maps (free) instead of in-app navigation
- **Double-Entry Accounting**: Independent ledger for revenue, expenses, driver payments

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
```

## API Documentation

### Authentication
```bash
# Login (get tokens)
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "password"
}

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
GET  /api/v1/driver/trips/today                    # Today's trips
POST /api/v1/driver/trips/{id}/start               # Start trip
POST /api/v1/driver/trips/{id}/arrive/{dest}       # Arrived at stop
POST /api/v1/driver/trips/{id}/complete/{dest}     # Complete delivery
GET  /api/v1/driver/navigation/{dest}              # Navigation URL
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

Located in `flutter_app/` directory.

### Setup
```bash
cd flutter_app
flutter pub get
flutter run
```

### Key Features
- View today's assigned trips
- Optimized route display
- Tap to navigate (opens Google Maps)
- Mark arrival/completion
- GPS tracking for actual KM
- Optional signature/photo capture

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
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Configure Google Maps API key
- [ ] Set up Cloudflare R2 bucket
- [ ] Configure database credentials
- [ ] Set up Redis AUTH
- [ ] Enable HTTPS via Ingress
- [ ] Deploy Flutter app to stores

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

## Documentation

- `CLAUDE.md` - AI assistant context (for Claude Code)
- `flutter_app/README.md` - Flutter app documentation
- `docs/` - Additional documentation (added as needed)

## License

Proprietary - All rights reserved.

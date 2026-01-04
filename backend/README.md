# Al-Sabiqoon Backend

> Laravel 12 API with FrankenPHP/Octane

## Quick Start

```bash
# From project root
make up
make shell-backend
php artisan migrate
```

## URLs (Development)

| Service | URL | Credentials |
|---------|-----|-------------|
| API | http://localhost:8000 | - |
| Telescope | http://localhost:8000/telescope | - |
| Horizon | http://localhost:8000/horizon | - |
| Filament Admin | http://localhost:8000/admin | admin@alsabiqoon.com / password |

## Health Endpoints

| Endpoint | Purpose | K8s Probe |
|----------|---------|-----------|
| `/up` | Laravel startup | startupProbe |
| `/health` | App alive (no deps) | livenessProbe |
| `/ready` | App ready (checks DB, Redis) | readinessProbe |

## Key Configuration Files

| File | Purpose |
|------|---------|
| `config/dashboards.php` | Telescope & Horizon auth, recording modes |
| `config/telescope.php` | Telescope watchers, ignored paths |
| `config/horizon.php` | Queue workers, environments |
| `config/cors.php` | CORS configuration for frontend |

## Telescope & Horizon

### Authorization

Access is controlled via `DASHBOARD_AUTHORIZED_EMAILS` env variable:

```env
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com,dev@alsabiqoon.com
```

### Recording Modes

| Mode | Records | Environment |
|------|---------|-------------|
| `all` | Everything | Local, Staging |
| `errors_only` | Exceptions, failed requests/jobs, logs, mail | Production |
| `important_only` | Critical errors only | High-traffic prod |

```env
TELESCOPE_RECORDING_MODE=all          # Local/Staging
TELESCOPE_RECORDING_MODE=errors_only  # Production
```

### Auto-Pruning

Telescope entries are automatically pruned after 48 hours (configurable):

```env
TELESCOPE_PRUNE_HOURS=48
```

## Redis Isolation

When sharing Redis across environments, use different database numbers:

| Environment | Default DB | Cache DB | Session DB |
|-------------|------------|----------|------------|
| Local | 0 | 1 | 2 |
| Staging | 3 | 4 | 5 |
| Production | 6 | 7 | 8 |

```env
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_PREFIX=alsabiqoon_local_
CACHE_PREFIX=alsabiqoon_local_cache_
HORIZON_PREFIX=alsabiqoon_local_horizon:
```

## Common Commands

```bash
# From project root using make
make migrate         # Run migrations
make fresh           # Fresh migrate + seed
make tinker          # Laravel REPL
make test            # Run tests
make pail            # Stream logs (pretty)

# V1 API generators
make v1-controller name=UserController
make v1-request name=StoreUserRequest
make v1-resource name=UserResource
make v1-crud name=User
```

## API Structure

```
routes/api/
├── v1.php          # /api/v1/* routes
├── v2.php          # /api/v2/* routes (future)
└── v3.php          # /api/v3/* routes (future)

app/Http/
├── Controllers/Api/V1/
│   └── AuthController.php
├── Requests/Api/V1/
│   └── Auth/
│       ├── LoginRequest.php
│       ├── RegisterRequest.php
│       └── RefreshTokenRequest.php
└── Resources/Api/V1/
    ├── UserResource.php
    └── AuthTokenResource.php
```

## Authentication (OAuth2 Password Grant)

Uses Laravel Passport with Password Grant for API authentication.

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/register` | POST | Register new user |
| `/api/v1/auth/login` | POST | Login with email/password |
| `/api/v1/auth/refresh` | POST | Refresh access token |
| `/api/v1/auth/logout` | POST | Logout (revokes tokens) |
| `/api/v1/auth/user` | GET | Get authenticated user |

### Token Expiration

| Environment | Access Token | Refresh Token |
|-------------|--------------|---------------|
| Local | 60 min | 7 days |
| Staging | 60 min | 7 days |
| Production | 15 min | 7 days |

### Running Tests

```bash
# Run all auth tests
php artisan test --filter=Auth

# Run specific test
php artisan test tests/Feature/Auth/LoginTest.php
```

## Documentation

See [backend/docs/v1/](./docs/v1/) for detailed documentation.

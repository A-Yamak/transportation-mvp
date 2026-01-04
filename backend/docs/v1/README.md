# Al-Sabiqoon Backend Documentation v1

> **Last Updated:** December 2025
> **Folder:** `/backend/`
> **Framework:** Laravel 12 + PHP 8.4

## Overview

The backend is a Laravel 12 API application running on FrankenPHP with Octane for high-performance request handling. It provides RESTful APIs consumed by the Vue.js frontend.

---

## Technology Stack

| Package | Version | Purpose |
|---------|---------|---------|
| PHP | 8.4 | Server-side language |
| Laravel Framework | 12.x | Application framework |
| Laravel Octane | 2.x | Persistent application server |
| Laravel Horizon | 5.x | Queue monitoring |
| Laravel Passport | 13.x | OAuth2 authentication |
| Laravel Telescope | 5.x | Debug assistant (dev only) |
| Filament | 4.x | Admin panel |

---

## Folder Structure

```
backend/
├── app/
│   ├── Console/           # Artisan commands
│   ├── Exceptions/        # Exception handlers
│   ├── Http/
│   │   ├── Controllers/   # API controllers
│   │   ├── Middleware/    # Request middleware
│   │   └── Requests/      # Form requests (validation)
│   ├── Models/            # Eloquent models
│   ├── Policies/          # Authorization policies
│   ├── Providers/         # Service providers
│   └── Services/          # Business logic
│
├── bootstrap/             # Framework bootstrap
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories (testing)
│   ├── migrations/        # Database migrations
│   └── seeders/           # Data seeders
│
├── public/                # Web root
├── resources/
│   ├── lang/              # Language files
│   └── views/             # Blade templates (emails, etc.)
│
├── routes/
│   ├── api.php            # API routes
│   ├── console.php        # Artisan commands
│   └── web.php            # Web routes (admin panel)
│
├── storage/               # Generated files, logs
├── tests/                 # PHPUnit tests
│   ├── Feature/           # Feature/integration tests
│   └── Unit/              # Unit tests
│
├── docs/v1/               # This documentation
├── composer.json          # PHP dependencies
└── phpunit.xml            # Test configuration
```

---

## Key Concepts for Beginners

### Laravel Octane

**What it is:** Octane supercharges your Laravel application by keeping it in memory between requests, eliminating the bootstrap overhead.

**The problem it solves:** Normally, PHP loads your entire application for every single request, then discards it. With a big Laravel app, this can take 50-100ms just to boot. Octane keeps everything in memory, reducing response times to 2-5ms.

```bash
# Start Octane in development (with hot-reload)
php artisan octane:start --watch

# Start Octane in production
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
```

**Important considerations:**
```php
// ❌ BAD: Static properties persist between requests
class UserService {
    private static $count = 0;  // This accumulates!
}

// ✅ GOOD: Use request-scoped data
class UserService {
    public function __construct(private Request $request) {}
}
```

### FrankenPHP

**What it is:** A modern PHP server written in Go that's designed for production. It's the default Octane server in Laravel 12.

**Benefits:**
- Built-in HTTPS with automatic Let's Encrypt
- HTTP/2 and HTTP/3 support
- Early Hints (103 responses)
- Worker mode for Octane

### Laravel Horizon

**What it is:** A beautiful dashboard and configuration system for Laravel's Redis-based queues.

**The problem it solves:** Without Horizon, managing background jobs is a black box. Horizon gives you:
- Real-time metrics
- Job throughput graphs
- Failed job management
- Auto-scaling workers

```bash
# Start Horizon
php artisan horizon

# Access dashboard at /horizon (auth required in non-local environments)
```

**Authorization:** Configured via `DASHBOARD_AUTHORIZED_EMAILS` env variable:
```env
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com,dev@alsabiqoon.com
```

**Configuration:** `config/horizon.php`
```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'maxProcesses' => 10,
            'minProcesses' => 2,
            'queue' => ['high', 'default', 'low'],
        ],
    ],
    'local' => [
        'supervisor-default' => [
            'maxProcesses' => 3,
        ],
    ],
],
```

### Laravel Tinker

**What it is:** An interactive PHP REPL (Read-Eval-Print Loop) for your Laravel application.

**The problem it solves:** Sometimes you need to quickly test something - check a model, run a query, or debug an issue. Tinker lets you interact with your application in real-time.

```bash
# Start Tinker
php artisan tinker

# Inside Tinker:
>>> User::count()
=> 42

>>> $user = User::find(1)
=> App\Models\User {#1234}

>>> $user->posts()->count()
=> 5

>>> Mail::raw('Test', fn($m) => $m->to('test@example.com'))
=> null  // Email sent!
```

### PHPUnit Testing

**What it is:** The de-facto testing framework for PHP. Laravel wraps it with helpful testing methods.

**Test types:**
- **Unit tests:** Test a single class/method in isolation
- **Feature tests:** Test complete features through HTTP requests

```php
// tests/Feature/UserTest.php
class UserTest extends TestCase
{
    use RefreshDatabase;  // Reset database for each test

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
        ]);
    }
}
```

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter=UserTest

# Run with coverage
php artisan test --coverage
```

### Laravel Passport

**What it is:** OAuth2 server implementation for Laravel. It issues access tokens for API authentication.

**The flow:**
1. User logs in with email/password
2. Server validates credentials
3. Server returns access token
4. Frontend stores token
5. Frontend sends token with every API request
6. Server validates token and processes request

```php
// Login endpoint returns tokens
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $request->user()->createToken('api-token');

    return response()->json([
        'access_token' => $token->accessToken,
        'token_type' => 'Bearer',
    ]);
}
```

```bash
# Frontend sends token in header
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Redis

**What it is:** An in-memory data store used for caching, sessions, and queues.

**Why we use it:**
- **Cache:** Store computed results to avoid recalculating
- **Sessions:** Store user sessions (faster than database)
- **Queues:** Background job storage for Horizon

```php
// Caching example
$users = Cache::remember('users:active', 3600, function () {
    return User::where('active', true)->get();
});

// Queue example
dispatch(new SendWelcomeEmail($user));  // Runs in background via Redis
```

**Redis Isolation:** When sharing Redis across environments (e.g., k3s cluster), use different database numbers and prefixes:

| Environment | Default DB | Cache DB | Session DB | Prefix |
|-------------|------------|----------|------------|--------|
| Local | 0 | 1 | 2 | `alsabiqoon_local_` |
| Staging | 3 | 4 | 5 | `alsabiqoon_staging_` |
| Production | 6 | 7 | 8 | `alsabiqoon_prod_` |

```env
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_PREFIX=alsabiqoon_local_
```

### Artisan Commands

**What it is:** Laravel's command-line interface for common tasks.

```bash
# Most useful commands
php artisan serve              # Start dev server (use Octane instead)
php artisan migrate            # Run database migrations
php artisan migrate:fresh      # Drop all tables and re-migrate
php artisan db:seed            # Run database seeders
php artisan make:model User    # Generate a model
php artisan make:controller    # Generate a controller
php artisan make:migration     # Generate a migration
php artisan route:list         # Show all routes
php artisan config:cache       # Cache configuration (production)
php artisan cache:clear        # Clear application cache
php artisan queue:work         # Process queue jobs (Horizon is better)
php artisan tinker             # Interactive PHP shell
```

---

## API Structure

### Route Organization

```
routes/api.php

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Protected routes (require authentication)
    Route::middleware('auth:api')->group(function () {
        Route::get('/user', [UserController::class, 'show']);
        Route::apiResource('posts', PostController::class);
    });
});
```

### Response Format

All API responses follow a consistent structure:

```json
// Success response
{
    "data": { ... },
    "message": "Resource created successfully"
}

// Error response
{
    "message": "Validation failed",
    "errors": {
        "email": ["The email field is required."]
    }
}

// Paginated response
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

---

## Development Workflow

### Running Locally

```bash
# With Docker (recommended)
make up                          # Start all services
make shell-backend               # Enter container
php artisan migrate              # Run migrations

# Without Docker (requires local PHP 8.4, MySQL, Redis)
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan octane:start --watch
```

### Common Tasks

```bash
# Create a new model with migration, factory, and controller
php artisan make:model Post -mfc

# Create a form request for validation
php artisan make:request StorePostRequest

# Run database migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh database with seeders
php artisan migrate:fresh --seed

# Clear all caches
php artisan optimize:clear
```

---

## Configuration Files

| File | Purpose |
|------|---------|
| `config/app.php` | Application settings |
| `config/auth.php` | Authentication guards |
| `config/database.php` | Database + Redis connections |
| `config/cache.php` | Cache stores (redis, sessions) |
| `config/queue.php` | Queue connections |
| `config/octane.php` | Octane/FrankenPHP settings |
| `config/horizon.php` | Horizon workers, environments |
| `config/telescope.php` | Telescope watchers, ignored paths |
| `config/dashboards.php` | Telescope/Horizon auth & recording modes |
| `config/cors.php` | CORS for frontend SPA |
| `config/passport.php` | OAuth2 settings |

---

## Testing

### Test Structure

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   └── RegisterTest.php
│   └── Api/
│       └── V1/
│           └── PostTest.php
└── Unit/
    ├── Models/
    │   └── UserTest.php
    └── Services/
        └── UserServiceTest.php
```

### Running Tests

```bash
# All tests
php artisan test

# With coverage
php artisan test --coverage

# Specific test
php artisan test --filter=test_user_can_login

# Specific file
php artisan test tests/Feature/Auth/LoginTest.php

# Parallel execution (faster)
php artisan test --parallel
```

---

## Environment Variables

Key variables in `.env`:

```bash
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Octane
OCTANE_SERVER=frankenphp

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=alsabiqoon

# Redis (with isolation for shared environments)
REDIS_HOST=redis
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_PREFIX=alsabiqoon_local_

# Cache/Sessions/Queues
CACHE_STORE=redis
CACHE_PREFIX=alsabiqoon_local_cache_
SESSION_DRIVER=redis
SESSION_STORE=sessions
QUEUE_CONNECTION=redis

# Telescope & Horizon
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com
TELESCOPE_ENABLED=true
TELESCOPE_RECORDING_MODE=all
TELESCOPE_PRUNE_HOURS=48
HORIZON_PREFIX=alsabiqoon_local_horizon:

# Mail (Mailpit for development)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

---

## Debugging

### Telescope

Access at `/telescope`. In non-local environments, requires authentication via `DASHBOARD_AUTHORIZED_EMAILS`.

**Authorization:**
```env
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com,dev@alsabiqoon.com
```

**Recording Modes:**
| Mode | Records | Use Case |
|------|---------|----------|
| `all` | Everything | Local, Staging |
| `errors_only` | Exceptions, failed requests/jobs, logs, mail | Production |
| `important_only` | Critical errors only | High-traffic prod |

```env
TELESCOPE_RECORDING_MODE=all          # Local/Staging
TELESCOPE_RECORDING_MODE=errors_only  # Production
```

**Auto-Pruning:** Entries older than 48 hours are automatically deleted:
```env
TELESCOPE_PRUNE_HOURS=48
```

**Shows:**
- Requests
- Exceptions
- Logs
- Queries (slow queries highlighted)
- Mail
- Redis commands
- Jobs
- Cache operations

### Logs

```bash
# View logs in real-time
tail -f storage/logs/laravel.log

# Or with Pail (prettier)
php artisan pail
```

### Debugging in Code

```php
// Dump and die
dd($variable);

// Dump without stopping
dump($variable);

// Log to file
Log::info('Something happened', ['user' => $user->id]);

// Log query
DB::enableQueryLog();
// ... queries ...
dd(DB::getQueryLog());
```

---

## Related Documentation

- **Root:** [/docs/v1/](../../docs/v1/)
- **Infrastructure:** [/infrastructure/docs/v1/](../../infrastructure/docs/v1/)
- **Frontend:** [/frontend/docs/v1/](../../frontend/docs/v1/)

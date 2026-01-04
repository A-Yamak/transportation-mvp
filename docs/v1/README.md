# Al-Sabiqoon Project Documentation v1

> **Last Updated:** December 2025
> **Status:** Active Development

## Overview

Al-Sabiqoon is a 20-year strategic plan to build a global full-service accelerator. This documentation covers the complete technical architecture and implementation details.

**Domain:** alsabiqoon.com (frontend), api.alsabiqoon.com (backend)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           PRODUCTION                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   ┌───────────────┐         ┌───────────────┐                          │
│   │   Cloudflare  │         │   Cloudflare  │                          │
│   │     CDN       │         │      R2       │                          │
│   │  (Frontend)   │         │   (Storage)   │                          │
│   └───────┬───────┘         └───────────────┘                          │
│           │                                                             │
│   ┌───────▼───────┐                                                    │
│   │  Kubernetes   │                                                    │
│   │    Cluster    │                                                    │
│   │               │                                                    │
│   │  ┌─────────┐  │   ┌─────────┐   ┌─────────┐   ┌─────────┐        │
│   │  │Frontend │  │   │ Backend │   │ Horizon │   │  Redis  │        │
│   │  │ (Nginx) │  │   │(Octane) │   │(Worker) │   │ (Cache) │        │
│   │  └─────────┘  │   └────┬────┘   └─────────┘   └─────────┘        │
│   │               │        │                                           │
│   └───────────────┘        │        ┌─────────┐                       │
│                            └────────│  MySQL  │                       │
│                                     │(Managed)│                       │
│                                     └─────────┘                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Technology Stack

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4 | Server-side language |
| Laravel | 12 | PHP framework |
| FrankenPHP | 1.x | High-performance PHP server |
| Laravel Octane | 2.x | Persistent application for speed |
| Laravel Horizon | 5.x | Queue dashboard & management |
| Laravel Passport | 13.x | OAuth2 API authentication |
| Laravel Telescope | 5.x | Debug dashboard (all environments) |
| Filament | 4.x | Admin panel |
| MySQL | 8.0 | Primary database |
| Redis | 7.x | Cache, sessions, queues |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| Vue | 3.5 | Reactive UI framework |
| TypeScript | 5.9 | Type-safe JavaScript |
| Vite | 7.x | Build tool & dev server |
| Tailwind CSS | 4.x | Utility-first CSS |
| Pinia | 3.x | State management |
| Vue Router | 4.x | Client-side routing |
| Vitest | 4.x | Unit testing |

### Infrastructure
| Technology | Version | Purpose |
|------------|---------|---------|
| Docker | 24+ | Containerization |
| Docker Compose | 2.22+ | Local development |
| Kubernetes | 1.28+ | Container orchestration |
| Kustomize | Built-in | K8s configuration management |
| Nginx | Alpine | Frontend static server |

---

## Project Structure

```
al-sabiqoon/
├── backend/                    # Laravel 12 API
│   ├── app/                    # Application code
│   │   ├── Http/Controllers/   # API controllers
│   │   ├── Models/             # Eloquent models
│   │   └── Services/           # Business logic
│   ├── config/                 # Configuration files
│   ├── database/               # Migrations & seeders
│   ├── routes/                 # API routes
│   ├── tests/                  # PHPUnit tests
│   └── docs/v1/                # Backend documentation
│
├── frontend/                   # Vue 3 SPA
│   ├── src/
│   │   ├── api/                # API client
│   │   ├── assets/             # CSS & static files
│   │   ├── components/         # Vue components
│   │   ├── composables/        # Reusable logic
│   │   ├── router/             # Vue Router config
│   │   ├── stores/             # Pinia stores
│   │   └── views/              # Page components
│   └── docs/v1/                # Frontend documentation
│
├── infrastructure/             # DevOps configuration
│   ├── docker/
│   │   ├── backend/            # PHP/Octane Dockerfiles
│   │   └── frontend/           # Node/Nginx Dockerfiles
│   ├── kubernetes/
│   │   ├── base/               # K8s base manifests
│   │   └── overlays/           # Environment patches
│   └── docs/v1/                # Infrastructure documentation
│
├── docs/v1/                    # Root documentation (this file)
├── compose.yaml                # Docker Compose config
└── Makefile                    # Development commands
```

---

## Key Concepts

### Laravel Octane with FrankenPHP

**What it is:** Octane keeps your Laravel application in memory between requests, eliminating the bootstrap overhead on every request.

**Why FrankenPHP:** It's written in Go, provides automatic HTTPS with Let's Encrypt, HTTP/2 & HTTP/3 support, and Early Hints.

```bash
# Development uses Octane with --watch for hot-reload
php artisan octane:start --server=frankenphp --watch

# Production runs without --watch for maximum performance
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
```

**Memory considerations:** Since the app stays in memory, be careful with:
- Static properties (they persist between requests)
- Singletons (reset them if needed)
- Large objects (they won't be garbage collected until worker restart)

### Laravel Horizon

**What it is:** Dashboard and configuration system for Laravel's Redis queues.

**Key features:**
- Real-time metrics
- Job monitoring & retries
- Auto-scaling workers
- Failed job management

```bash
# Start Horizon (usually via supervisor in production)
php artisan horizon

# Access dashboard at: /horizon (auth required in non-local)
```

**Authorization:** Access controlled via `DASHBOARD_AUTHORIZED_EMAILS`:
```env
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com
```

### Laravel Telescope

**What it is:** Debug dashboard for Laravel applications, enabled in all environments.

**Recording modes:**
- `all` - Everything (local, staging)
- `errors_only` - Exceptions, failed requests/jobs (production)
- `important_only` - Critical errors only

```env
TELESCOPE_RECORDING_MODE=all          # Local/Staging
TELESCOPE_RECORDING_MODE=errors_only  # Production
TELESCOPE_PRUNE_HOURS=48              # Auto-delete after 48h
```

### Redis Isolation

When sharing Redis across environments in k3s:

| Environment | DB Numbers | Prefix |
|-------------|------------|--------|
| Local | 0, 1, 2 | `alsabiqoon_local_` |
| Staging | 3, 4, 5 | `alsabiqoon_staging_` |
| Production | 6, 7, 8 | `alsabiqoon_prod_` |

### Dev/Prod Parity

Both development and production use the **same FrankenPHP + Octane** setup. This ensures:
- Memory leaks are caught during development
- Behavior is consistent across environments
- No surprises when deploying

---

## Development Workflow

### Quick Start

```bash
# Clone the repository
git clone <repo-url> al-sabiqoon
cd al-sabiqoon

# Start all services
make up

# Wait for services to be healthy, then access:
# Frontend: http://localhost:5173
# Backend:  http://localhost:8000
# Mailpit:  http://localhost:8026
```

### Essential Commands

```bash
# Docker
make up              # Start all services
make down            # Stop all services
make logs            # View all logs
make rebuild         # Rebuild without cache

# Backend
make shell-backend   # Enter backend container
make migrate         # Run migrations
make fresh           # Fresh migrate + seed
make tinker          # Laravel REPL
make test            # Run tests

# Frontend
make shell-frontend  # Enter frontend container
make lint-frontend   # Run ESLint
make format          # Run Prettier
```

### Docker Compose Watch

For efficient development, use Docker Compose Watch (requires v2.22+):

```bash
# Start with watch mode
docker compose watch

# Or via make
make watch
```

This syncs file changes without rebuilding containers.

---

## URLs & Ports

### Development
| Service | URL | Notes |
|---------|-----|-------|
| Frontend | http://localhost:5173 | Vite HMR |
| Backend API | http://localhost:8000 | FrankenPHP/Octane |
| Horizon | http://localhost:8000/horizon | Queue dashboard (auth in non-local) |
| Telescope | http://localhost:8000/telescope | Debug dashboard (auth in non-local) |
| Mailpit | http://localhost:8026 | Email testing UI |
| MySQL | localhost:3307 | Port 3307 to avoid conflicts |

### Health Check Endpoints
| Endpoint | Purpose | K8s Probe |
|----------|---------|-----------|
| `/up` | Laravel startup | startupProbe |
| `/health` | App liveness (no deps) | livenessProbe |
| `/ready` | App readiness (checks DB, Redis) | readinessProbe |

### Production
| Service | URL | Notes |
|---------|-----|-------|
| Frontend | https://alsabiqoon.com | Via Cloudflare CDN |
| Backend API | https://api.alsabiqoon.com | Via Kubernetes Ingress |

---

## Security Considerations

### Implemented
- Non-root Docker containers in production
- CSP headers configured
- OAuth2 (Passport) for API authentication
- Redis-backed sessions with isolation
- CORS configuration
- Input validation via Laravel
- Telescope/Horizon dashboard authorization
- Sensitive data masking in Telescope (passwords, OTPs, tokens, cards)

### Recommended Before Production
- Enable HSTS in nginx.conf
- Configure Redis AUTH password
- Set up secrets management (e.g., Sealed Secrets)
- Enable rate limiting middleware
- Configure backup strategy
- Set `DASHBOARD_AUTHORIZED_EMAILS` for Telescope/Horizon access

---

## Related Documentation

- **Infrastructure:** [/infrastructure/docs/v1/](../infrastructure/docs/v1/)
- **Backend:** [/backend/docs/v1/](../backend/docs/v1/)
- **Frontend:** [/frontend/docs/v1/](../frontend/docs/v1/)

---

## Changelog

### v1.0.0 (December 2025)
- Initial project setup
- Laravel 12 with FrankenPHP/Octane
- Vue 3 with Tailwind CSS 4
- Docker + Kubernetes infrastructure
- Comprehensive documentation

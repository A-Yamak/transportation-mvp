# Al-Sabiqoon Infrastructure Documentation v1

> **Last Updated:** December 2025
> **Folder:** `/infrastructure/`

## Overview

This folder contains all infrastructure-as-code for the Al-Sabiqoon platform, including Docker configurations for local development and Kubernetes manifests for production deployment.

---

## Folder Structure

```
infrastructure/
├── docker/
│   ├── backend/
│   │   ├── Dockerfile           # Production: FrankenPHP + Octane
│   │   ├── Dockerfile.dev       # Development: FrankenPHP + watch mode
│   │   ├── entrypoint.sh        # Container initialization script
│   │   └── php.ini              # Production PHP settings
│   └── frontend/
│       ├── Dockerfile           # Production: Multi-stage Node + Nginx
│       ├── Dockerfile.dev       # Development: Node with Vite HMR
│       └── nginx.conf           # Nginx configuration for SPA
│
├── kubernetes/
│   ├── base/                    # Shared K8s manifests
│   │   ├── namespace.yaml
│   │   ├── kustomization.yaml
│   │   ├── backend/             # Backend deployment, service, HPA, PVC
│   │   ├── frontend/            # Frontend deployment, service, HPA
│   │   ├── workers/             # Horizon deployment
│   │   └── redis/               # Redis deployment, service, PVC
│   └── overlays/
│       ├── staging/             # Staging-specific patches
│       └── production/          # Production-specific patches
│
└── docs/v1/                     # This documentation
```

---

## Docker Concepts

### Images vs Containers

```
┌─────────────────┐     docker run     ┌─────────────────┐
│     IMAGE       │ ─────────────────► │    CONTAINER    │
│  (Blueprint)    │                    │   (Instance)    │
│                 │                    │                 │
│ - Read-only     │                    │ - Running       │
│ - Layers        │                    │ - Read-write    │
│ - Shareable     │                    │ - Isolated      │
└─────────────────┘                    └─────────────────┘
```

**Image:** A read-only template with your application code and dependencies.
**Container:** A running instance of an image with its own filesystem and network.

### Multi-Stage Builds

Our Dockerfiles use multi-stage builds to create small, secure production images:

```dockerfile
# Stage 1: Install dependencies (large image with build tools)
FROM composer:2 AS composer
COPY composer.json composer.lock ./
RUN composer install --no-dev

# Stage 2: Build assets (if needed)
FROM node:22-alpine AS assets
RUN npm ci && npm run build

# Stage 3: Production image (small, no build tools)
FROM dunglas/frankenphp:1-php8.4-alpine
COPY --from=composer /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build
```

Benefits:
- Final image doesn't include npm, composer, or build tools
- Smaller image size (faster deployments)
- Reduced attack surface

---

## Docker Files Explained

### Backend Dockerfile.dev

```dockerfile
FROM dunglas/frankenphp:1-php8.4-alpine

# Key points:
# - Uses same base as production (FrankenPHP)
# - OPcache validates timestamps (sees code changes)
# - Xdebug installed but disabled by default
# - Runs as root for volume mount permissions

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--watch"]
```

The `--watch` flag enables hot-reload during development.

### Backend Dockerfile (Production)

```dockerfile
FROM dunglas/frankenphp:1-php8.4-alpine

# Key points:
# - Multi-stage build for minimal image size
# - Non-root user (alsabiqoon) for security
# - OPcache configured for maximum performance
# - No Xdebug or dev tools

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
```

### Frontend Nginx Config

```nginx
server {
    listen 80;
    root /usr/share/nginx/html;

    # SPA routing - critical for Vue Router
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Security headers
    add_header Content-Security-Policy "..." always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # Static asset caching (1 year for hashed files)
    location ~* \.(?:css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## Docker Compose Configuration

### compose.yaml Overview

```yaml
name: alsabiqoon

services:
  backend:     # FrankenPHP + Octane
  frontend:    # Vite dev server
  horizon:     # Queue worker
  mysql:       # Database
  redis:       # Cache/Sessions/Queues
  mailpit:     # Email testing

volumes:
  mysql-data:
  redis-data:
  backend-storage:

networks:
  alsabiqoon:
```

### Service Dependencies

```
                    ┌─────────────┐
                    │   backend   │
                    │  (Octane)   │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │  mysql  │  │  redis  │  │ mailpit │
        │(healthy)│  │(healthy)│  │         │
        └─────────┘  └─────────┘  └─────────┘
              │            │
              │            │
              └────────────┘
                    │
              ┌─────▼─────┐
              │  horizon  │
              │ (worker)  │
              └───────────┘
```

### Docker Compose Watch

Modern Compose includes a `develop.watch` feature for efficient file sync:

```yaml
services:
  backend:
    develop:
      watch:
        - action: sync
          path: ./backend/app
          target: /app/app
        - action: rebuild
          path: ./backend/composer.json
```

Run with: `docker compose watch`

---

## Kubernetes Concepts

### Basic Architecture

```
┌───────────────────────────────────────────────────────────────┐
│                         CLUSTER                                │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                    NAMESPACE: alsabiqoon                 │ │
│  │                                                         │ │
│  │  ┌─────────────────┐    ┌─────────────────┐           │ │
│  │  │   DEPLOYMENT    │    │    SERVICE      │           │ │
│  │  │   (backend)     │◄───│   (ClusterIP)   │◄── Ingress│ │
│  │  │                 │    └─────────────────┘           │ │
│  │  │  ┌───┐ ┌───┐   │                                   │ │
│  │  │  │Pod│ │Pod│   │    replicas: 2                    │ │
│  │  │  └───┘ └───┘   │                                   │ │
│  │  └─────────────────┘                                   │ │
│  │                                                         │ │
│  │  ┌─────────────────┐                                   │ │
│  │  │      HPA        │    Auto-scales pods               │ │
│  │  │ (2-10 replicas) │    based on CPU/memory            │ │
│  │  └─────────────────┘                                   │ │
│  │                                                         │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

### Key Resources

| Resource | Purpose | File |
|----------|---------|------|
| Namespace | Isolates resources | `namespace.yaml` |
| Deployment | Manages pod replicas | `*/deployment.yaml` |
| Service | Internal load balancer | `*/service.yaml` |
| HPA | Auto-scaling | `*/hpa.yaml` |
| PVC | Persistent storage | `*/pvc.yaml` |
| Ingress | External access | `overlays/*/ingress.yaml` |
| ConfigMap | Non-secret config | `overlays/*/configmap.yaml` |
| Secret | Sensitive data | Managed externally |

### Kustomize Structure

```
kubernetes/
├── base/                      # Shared configuration
│   ├── kustomization.yaml     # Lists all base resources
│   ├── backend/
│   │   ├── deployment.yaml
│   │   ├── service.yaml
│   │   ├── hpa.yaml
│   │   └── pvc.yaml
│   └── ...
│
└── overlays/
    ├── staging/
    │   ├── kustomization.yaml   # patches: [...]
    │   ├── ingress.yaml
    │   └── configmap.yaml
    │
    └── production/
        ├── kustomization.yaml
        ├── ingress.yaml
        └── configmap.yaml
```

Deploy with:
```bash
kubectl apply -k infrastructure/kubernetes/overlays/staging
kubectl apply -k infrastructure/kubernetes/overlays/production
```

---

## Health Checks

### Backend Health Endpoints

| Endpoint | Purpose | K8s Probe |
|----------|---------|-----------|
| `/up` | Laravel startup check | startupProbe |
| `/health` | Liveness (no dependencies) | livenessProbe |
| `/ready` | Readiness (checks DB, Redis) | readinessProbe |

```yaml
# Kubernetes probes
startupProbe:
  httpGet:
    path: /up
    port: 8000
  failureThreshold: 30
  periodSeconds: 5

livenessProbe:
  httpGet:
    path: /health
    port: 8000
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /ready
    port: 8000
  periodSeconds: 5
```

### Frontend Health

```nginx
location /health {
    return 200 "OK\n";
    add_header Content-Type text/plain;
}
```

---

## Storage Configuration

### Backend Storage

| Mount Path | Volume Type | Purpose |
|------------|-------------|---------|
| `/app/storage/app` | PVC | User uploads (persistent) |
| `/app/storage/framework/cache` | emptyDir | Cache (ephemeral) |
| `/app/storage/framework/sessions` | emptyDir | Sessions (use Redis!) |
| `/app/storage/framework/views` | emptyDir | Compiled views |

**Important:** For production, use Redis for sessions and cache. The emptyDir volumes are for fallback only.

### Redis Storage

```yaml
# redis/pvc.yaml
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 1Gi
```

### Redis Isolation (Shared Redis in k3s)

When sharing a single Redis instance across environments, use different database numbers and prefixes to avoid conflicts:

| Environment | Default DB | Cache DB | Session DB | Prefix |
|-------------|------------|----------|------------|--------|
| Local | 0 | 1 | 2 | `alsabiqoon_local_` |
| Staging | 3 | 4 | 5 | `alsabiqoon_staging_` |
| Production | 6 | 7 | 8 | `alsabiqoon_prod_` |

**ConfigMap/Secret values per environment:**

```env
# Staging
REDIS_DB=3
REDIS_CACHE_DB=4
REDIS_SESSION_DB=5
REDIS_PREFIX=alsabiqoon_staging_
CACHE_PREFIX=alsabiqoon_staging_cache_
HORIZON_PREFIX=alsabiqoon_staging_horizon:

# Production
REDIS_DB=6
REDIS_CACHE_DB=7
REDIS_SESSION_DB=8
REDIS_PREFIX=alsabiqoon_prod_
CACHE_PREFIX=alsabiqoon_prod_cache_
HORIZON_PREFIX=alsabiqoon_prod_horizon:
```

---

## Deployment Commands

### Local Development

```bash
make up              # Start services
make down            # Stop services
make logs            # View logs
make rebuild         # Rebuild images
make clean           # Remove volumes
```

### Kubernetes

```bash
make k8s-staging     # Deploy to staging
make k8s-production  # Deploy to production (with confirmation)
make k8s-status      # Check deployment status
```

---

## Security Notes

### Production Checklist

- [x] Non-root containers (implemented)
- [x] CSP headers enabled (implemented)
- [x] Telescope/Horizon dashboard authorization (implemented)
- [x] Sensitive data masking in Telescope (implemented)
- [ ] Secrets via K8s Secrets (configure externally)
- [ ] Network policies (add if needed)
- [ ] Pod security standards (add if needed)
- [ ] RBAC configuration (add if needed)

### Telescope & Horizon in Production

Both dashboards require authentication in non-local environments. Configure via ConfigMap/Secret:

```env
# Comma-separated list of authorized emails
DASHBOARD_AUTHORIZED_EMAILS=admin@alsabiqoon.com

# Telescope recording mode (errors_only for production)
TELESCOPE_ENABLED=true
TELESCOPE_RECORDING_MODE=errors_only
TELESCOPE_PRUNE_HOURS=48
```

### nginx Security Headers

```nginx
add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header Content-Security-Policy "...";
add_header Permissions-Policy "camera=(), microphone=()";
# HSTS - enable after confirming HTTPS works
# add_header Strict-Transport-Security "max-age=31536000";
```

---

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker compose logs backend

# Check if image builds
docker compose build backend

# Enter container for debugging
docker compose run --rm backend sh
```

### Database Connection Issues

```bash
# Verify MySQL is healthy
docker compose ps mysql

# Test connection
docker compose exec mysql mysqladmin ping -h localhost
```

### Permission Issues

Development runs as root to avoid volume permission problems. If you see permission errors:

```bash
# Reset volumes
make clean
make up
```

---

## Related Documentation

- **Root:** [/docs/v1/](../../docs/v1/)
- **Backend:** [/backend/docs/v1/](../../backend/docs/v1/)
- **Frontend:** [/frontend/docs/v1/](../../frontend/docs/v1/)

#!/bin/sh
# ==============================================================================
# Al-Sabiqoon - Container Entrypoint Script
# ==============================================================================
# Performs initialization tasks before starting the main application.
# This script runs on every container start.
#
# IMPORTANT: Config Caching
# -------------------------
# In production, we cache all config to avoid reading .env on every request.
# This provides a massive performance boost (50-100ms saved per request).
#
# Rules for config caching:
#   - NEVER use env() directly in app code (controllers, services, models)
#   - ALWAYS create config files and use config() helper
#   - env() should ONLY be used in config/*.php files
#
# Example:
#   BAD:  $key = env('API_KEY');              // Returns NULL when cached!
#   GOOD: $key = config('services.api_key');  // Always works!
#
# ==============================================================================

set -e

echo "============================================================"
echo " Al-Sabiqoon Backend - Container Initialization"
echo "============================================================"

# ------------------------------------------------------------------------------
# Environment Check
# ------------------------------------------------------------------------------

if [ -z "$APP_KEY" ]; then
    echo "[WARN] APP_KEY is not set. Application may not function correctly."
fi

echo "[INFO] Environment: ${APP_ENV:-production}"
echo "[INFO] Server: ${OCTANE_SERVER:-frankenphp}"

# ------------------------------------------------------------------------------
# Storage & Cache Directories
# ------------------------------------------------------------------------------
# Ensure storage directories exist and are writable.
# These may be mounted volumes in Kubernetes.

echo "[INFO] Ensuring storage directories exist..."

mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache

# Set permissions if running as root (shouldn't happen in production)
if [ "$(id -u)" = "0" ]; then
    chown -R alsabiqoon:alsabiqoon /app/storage /app/bootstrap/cache 2>/dev/null || true
fi

chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

# ------------------------------------------------------------------------------
# Laravel Optimization (Production & Staging)
# ------------------------------------------------------------------------------
# CRITICAL: These caching commands provide massive performance improvements.
#
# What each command does:
#   config:cache  - Compiles all config/*.php into bootstrap/cache/config.php
#                   After this, .env is NO LONGER READ (50-100ms saved/request)
#   route:cache   - Compiles all routes into bootstrap/cache/routes-v7.php
#                   Faster route matching (especially with many routes)
#   view:cache    - Pre-compiles all Blade templates
#                   First render of each view is faster
#   event:cache   - Caches event-listener mappings
#                   Faster event dispatching
#
# IMPORTANT: If you use env() in app code, it will return NULL after config:cache!
#            Always use config() helper instead.
#
# TODO: In Kubernetes, consider using ConfigMaps for non-sensitive config
#       and Secrets for sensitive values (API keys, passwords, etc.)
#       This eliminates the need for .env files entirely in containers.
# ------------------------------------------------------------------------------

if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "[INFO] Running production optimizations..."
    echo "[INFO] Note: After config:cache, env() returns NULL - use config() instead!"

    # Clear any stale caches first
    php artisan config:clear --no-interaction 2>/dev/null || true
    php artisan route:clear --no-interaction 2>/dev/null || true
    php artisan view:clear --no-interaction 2>/dev/null || true
    php artisan event:clear --no-interaction 2>/dev/null || true

    # Cache everything for maximum performance
    echo "[INFO] Caching configuration..."
    php artisan config:cache --no-interaction || echo "[WARN] Config cache failed, continuing..."

    echo "[INFO] Caching routes..."
    php artisan route:cache --no-interaction || echo "[WARN] Route cache failed, continuing..."

    echo "[INFO] Caching views..."
    php artisan view:cache --no-interaction || echo "[WARN] View cache failed, continuing..."

    echo "[INFO] Caching events..."
    php artisan event:cache --no-interaction || echo "[WARN] Event cache failed, continuing..."

    # Optimize autoloader
    echo "[INFO] Optimizing autoloader..."
    composer dump-autoload --optimize --no-dev --classmap-authoritative 2>/dev/null || true

    echo "[OK] Production optimizations complete"
fi

# ------------------------------------------------------------------------------
# Database Migrations (Optional)
# ------------------------------------------------------------------------------
# Run migrations if MIGRATE_ON_START is set.
#
# WARNING: Be careful with this in multi-replica deployments!
# Multiple pods might try to run migrations simultaneously.
#
# Better approach for Kubernetes:
#   - Use an init container that runs migrations
#   - Or use a separate Job for migrations before deployment
# ------------------------------------------------------------------------------

if [ "$MIGRATE_ON_START" = "true" ]; then
    echo "[INFO] Running database migrations..."
    php artisan migrate --force --no-interaction || echo "[WARN] Migration failed, continuing..."
fi

# ------------------------------------------------------------------------------
# Passport Keys Check
# ------------------------------------------------------------------------------
# Verify Passport keys exist for API authentication
#
# TODO: In Kubernetes, store Passport keys in Secrets:
#   - PASSPORT_PRIVATE_KEY: Base64 encoded private key
#   - PASSPORT_PUBLIC_KEY: Base64 encoded public key
# ------------------------------------------------------------------------------

if [ "$APP_ENV" = "production" ] && [ -z "$PASSPORT_PRIVATE_KEY" ]; then
    echo "[WARN] Passport keys not configured. API authentication may fail."
    echo "[HINT] Set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY env vars"
fi

# ------------------------------------------------------------------------------
# Start Application
# ------------------------------------------------------------------------------

echo "============================================================"
echo " Initialization complete. Starting application..."
echo "============================================================"

# Execute the main command (passed from Dockerfile CMD or docker-compose command)
exec "$@"

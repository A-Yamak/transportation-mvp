# ==============================================================================
# Al-Sabiqoon Backend - Production Dockerfile
# ==============================================================================
# Multi-stage build for Laravel 12 with FrankenPHP and Octane
# Base: PHP 8.4 with FrankenPHP for high-performance request handling
#
# Build: docker build -t alsabiqoon/backend:latest -f infrastructure/docker/backend/Dockerfile .
# Run:   docker run -p 8000:8000 alsabiqoon/backend:latest
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# ------------------------------------------------------------------------------
# Install PHP dependencies in a separate stage to leverage Docker cache

FROM composer:2 AS composer

WORKDIR /app

# Copy only composer files first for better cache utilization
COPY backend/composer.json backend/composer.lock ./

# Install dependencies without dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy the rest of the application
COPY backend/ .

# Generate optimized autoloader
RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative

# ------------------------------------------------------------------------------
# Stage 2: Frontend Assets (if any backend assets exist)
# ------------------------------------------------------------------------------

FROM node:22-alpine AS assets

WORKDIR /app

# Copy package files
COPY backend/package*.json ./

# Install dependencies (use npm install instead of ci due to rollup optional deps bug)
RUN npm install --omit=dev --prefer-offline --no-audit && \
    npm install --no-save @rollup/rollup-linux-x64-musl

# Copy source files
COPY backend/ .

# Build assets
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 3: Production Image with FrankenPHP
# ------------------------------------------------------------------------------

FROM dunglas/frankenphp:1-php8.4-alpine AS production

# Set working directory
WORKDIR /app

# Install additional PHP extensions required by Laravel
RUN install-php-extensions \
    pdo_mysql \
    redis \
    pcntl \
    zip \
    gd \
    intl \
    opcache \
    bcmath \
    exif

# Copy custom PHP configuration
COPY infrastructure/docker/backend/php.ini /usr/local/etc/php/conf.d/99-alsabiqoon.ini

# Create non-root user for security
RUN addgroup -g 1000 -S alsabiqoon && \
    adduser -u 1000 -S alsabiqoon -G alsabiqoon

# Copy application from composer stage
COPY --from=composer /app/vendor /app/vendor
COPY --chown=alsabiqoon:alsabiqoon backend/ /app

# Copy built assets
COPY --from=assets /app/public/build /app/public/build

# Create required directories with proper permissions
RUN mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache && \
    chown -R alsabiqoon:alsabiqoon /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Copy entrypoint script
COPY infrastructure/docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Environment variables for Octane
ENV APP_ENV=production
ENV OCTANE_SERVER=frankenphp

# Expose port for Octane
EXPOSE 8000

# Health check endpoint (Laravel's default /up route)
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Switch to non-root user
USER alsabiqoon

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Default command: Start Octane with FrankenPHP
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]

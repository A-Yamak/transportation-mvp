# ==============================================================================
# Al-Sabiqoon Frontend - Production Dockerfile
# ==============================================================================
# Multi-stage build for Vue 3 SPA with Nginx for serving static files
#
# Build: docker build -t alsabiqoon/frontend:latest -f infrastructure/docker/frontend/Dockerfile ./frontend
# Run:   docker run -p 80:80 alsabiqoon/frontend:latest
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Build Vue Application
# ------------------------------------------------------------------------------

FROM node:22-alpine AS builder

WORKDIR /app

# Copy package files for dependency caching
COPY package*.json ./

# Install all dependencies (including devDependencies for build)
RUN npm ci

# Copy source files
COPY . .

# Build arguments for environment configuration
ARG VITE_API_URL=https://api.alsabiqoon.com
ARG VITE_APP_NAME="Al-Sabiqoon"

# Set environment variables for build
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_APP_NAME=$VITE_APP_NAME

# Build the application
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 2: Production Nginx Server
# ------------------------------------------------------------------------------

FROM nginx:alpine AS production

# Install curl for health checks
RUN apk add --no-cache curl

# Remove default nginx config
RUN rm /etc/nginx/conf.d/default.conf

# Copy custom nginx configuration
COPY infrastructure/docker/frontend/nginx.conf /etc/nginx/conf.d/alsabiqoon.conf

# Copy built assets from builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Create non-root user
RUN addgroup -g 1000 -S alsabiqoon && \
    adduser -u 1000 -S alsabiqoon -G alsabiqoon && \
    chown -R alsabiqoon:alsabiqoon /usr/share/nginx/html && \
    chown -R alsabiqoon:alsabiqoon /var/cache/nginx && \
    chown -R alsabiqoon:alsabiqoon /var/log/nginx && \
    touch /var/run/nginx.pid && \
    chown -R alsabiqoon:alsabiqoon /var/run/nginx.pid

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Switch to non-root user
USER alsabiqoon

# Start nginx
CMD ["nginx", "-g", "daemon off;"]

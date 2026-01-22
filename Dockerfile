# ============================================
# Stage 1: Builder - Install dependencies
# ============================================
FROM php:8.3-alpine AS builder

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP extensions needed for composer dependencies
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring

# Set working directory
WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies with optimal settings
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --classmap-authoritative

# Copy application source files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --classmap-authoritative --no-dev

# ============================================
# Stage 2: Runtime - Production image
# ============================================
FROM php:8.3-alpine

# Pin yt-dlp version for better layer caching
ARG YT_DLP_VERSION=2024.12.23

# Install runtime dependencies (no cache, minimal layers)
RUN apk add --no-cache \
    python3 \
    ffmpeg

# Install PHP extensions and clean up installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring \
    && rm /usr/local/bin/install-php-extensions

# Download yt-dlp with pinned version
RUN wget -q "https://github.com/yt-dlp/yt-dlp/releases/download/${YT_DLP_VERSION}/yt-dlp" -O /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

# Copy RoadRunner binary
COPY --from=ghcr.io/roadrunner-server/roadrunner:latest /usr/bin/rr /usr/local/bin/rr

# Set up php user and directories
RUN addgroup -g 1000 -S php \
    && adduser --system --gecos "" --ingroup php --uid 1000 php \
    && mkdir -p /var/run/rr /app/downloads /app/logs \
    && chown -R php:php /var/run/rr /app

# Set working directory
WORKDIR /app

# Copy vendor from builder stage
COPY --from=builder --chown=php:php /app/vendor ./vendor

# Copy application files
COPY --chown=php:php . .

# Switch to non-root user
USER php

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "echo 'OK';" || exit 1

# Start RoadRunner
CMD ["rr", "serve"]

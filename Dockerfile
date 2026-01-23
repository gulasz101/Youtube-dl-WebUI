# ============================================
# Stage 1: Builder - Install dependencies
# ============================================
FROM composer:latest AS composer
FROM php:8.5-alpine AS builder

# Copy composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Install PHP extensions for builder
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring

# Set working directory
WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies
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
FROM php:8.5-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    python3 \
    ffmpeg \
    wget

# Install PHP extensions and clean up installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring \
    && rm /usr/local/bin/install-php-extensions

# Download yt-dlp latest version
RUN wget -q "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp" -O /usr/local/bin/yt-dlp \
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

# Create config.php from example (config.php is in .gitignore)
RUN cp config/config.php.example config/config.php \
    && chown php:php config/config.php

# Switch to non-root user
USER php

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "echo 'OK';" || exit 1

# Start RoadRunner
CMD ["rr", "serve"]

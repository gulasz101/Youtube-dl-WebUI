# ============================================
# Stage 1: Builder - Install dependencies
# ============================================
FROM composer:latest AS composer
FROM php:8.3-alpine AS builder

# Copy composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Install build tools for OpenSwoole
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    openssl-dev

# Install PHP extensions for builder (including Swoole)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring swoole

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
FROM php:8.3-alpine

# Install runtime dependencies and build tools for OpenSwoole
RUN apk add --no-cache \
    python3 \
    ffmpeg \
    wget \
    deno \
    autoconf \
    g++ \
    make \
    openssl-dev \
    php83-dev

# Install PHP extensions including Swoole
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring swoole \
    && rm /usr/local/bin/install-php-extensions

# Download yt-dlp latest version
RUN wget -q "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp" -O /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

# Set up php user and directories
RUN addgroup -g 1000 -S php \
    && adduser --system --gecos "" --ingroup php --uid 1000 php \
    && mkdir -p /app/downloads /app/logs \
    && chown -R php:php /app

# Set working directory
WORKDIR /app

# Copy vendor from builder stage
COPY --from=builder --chown=php:php /app/vendor ./vendor

# Copy application files
COPY --chown=php:php . .

# Copy custom php.ini
COPY --chown=php:php php.ini /usr/local/etc/php/conf.d/custom.ini

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

# Start OpenSwoole server
CMD ["php", "swoole-server.php"]

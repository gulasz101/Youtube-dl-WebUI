FROM php:8.3-alpine

# Install system dependencies with cache
RUN --mount=type=cache,target=/var/cache/apk \
  apk add --no-cache \
    git \
    curl \
    python3 \
    ffmpeg

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets zip mbstring

# Download yt-dlp
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
  && chmod a+rx /usr/local/bin/yt-dlp

# Copy RoadRunner and Composer from their images
COPY --from=ghcr.io/roadrunner-server/roadrunner:latest /usr/bin/rr /usr/local/bin/rr
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set up user and directories
RUN addgroup -g "1000" -S php \
  && adduser --system --gecos "" --ingroup "php" --uid "1000" php \
  && mkdir /var/run/rr \
  && chown php /var/run/rr

WORKDIR /app
USER php

CMD ["rr", "serve"]

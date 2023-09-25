FROM ghcr.io/roadrunner-server/roadrunner:2.10.1 AS roadrunner
FROM composer:latest AS composer
FROM php:8.2-alpine

RUN apk add \
  git \
  curl \
  ffmpeg

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
  sockets \
  zip \
  mbstring


RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
RUN chmod a+rx /usr/local/bin/yt-dlp

WORKDIR /app

RUN addgroup -g "1000" -S php \
  && adduser --system --gecos "" --ingroup "php" --uid "1000" php \
  && mkdir /var/run/rr \
  && chown php /var/run/rr

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer /usr/bin/composer /usr/bin/composer

CMD ["rr", "serve"]

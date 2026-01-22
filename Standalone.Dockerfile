FROM ghcr.io/roadrunner-server/roadrunner:latest AS roadrunner
FROM composer:latest AS composer
FROM php:8.3-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apk add \
  git \
  curl \
  python3 \
  ffmpeg

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
  sockets \
  zip \
  mbstring

RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
RUN chmod a+rx /usr/local/bin/yt-dlp

WORKDIR /app

RUN git clone https://github.com/gulasz101/Youtube-dl-WebUI.git .
RUN composer install

RUN addgroup -g "1000" -S php \
  && adduser --system --gecos "" --ingroup "php" --uid "1000" php \
  && mkdir /var/run/rr \
  && chown php /var/run/rr

EXPOSE 8080

CMD ["rr", "serve"]

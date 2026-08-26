# syntax=docker/dockerfile:1

FROM node:22-alpine AS web
WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-cli-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY api/ ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=web /web/dist ./public/app
COPY scripts/demo-start.sh /usr/local/bin/fanabe-start
COPY scripts/demo-router.php /usr/local/bin/fanabe-router.php
RUN chmod +x /usr/local/bin/fanabe-start \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    CACHE_STORE=file \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync

EXPOSE 8000
CMD ["fanabe-start"]

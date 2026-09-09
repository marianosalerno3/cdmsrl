# Immagine di produzione per portale-api (Laravel).
# Base serversideup/php: nginx + php-fpm + s6, ottimizzata per Laravel, non-root.
# Build context = root del repo:  docker build -f infra/api.Dockerfile .

# ---- stage 1: dipendenze composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY portale-api/composer.json portale-api/composer.lock ./
RUN composer install \
      --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# ---- stage 2: runtime ----
FROM serversideup/php:8.3-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true

USER root
RUN install-php-extensions intl gd bcmath pdo_mysql redis pcntl
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data portale-api/ .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan filament:assets \
 && php artisan storage:link || true

# health check applicativo (Laravel espone /up)
HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=5 \
  CMD curl -fsS http://127.0.0.1:8080/up || exit 1

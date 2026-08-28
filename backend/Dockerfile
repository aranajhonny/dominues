# ---- Dominues backend (Laravel) ----
# Run `composer create-project laravel/laravel:^11.0 .` here first.

# Stage 1: Composer dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --no-scripts --ignore-platform-reqs

# Stage 2: Runtime — PHP CLI (artisan serve), lightweight for homelab
FROM php:8.3-cli-alpine
RUN apk add --no-cache icu-dev libzip-dev && \
    docker-php-ext-install pdo_mysql intl zip bcmath opcache && \
    docker-php-ext-enable opcache

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Storage structure + write perms (container runs as root in homelab)
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache && \
    ln -sf /var/www/html/storage /var/www/html/public/storage

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint"]
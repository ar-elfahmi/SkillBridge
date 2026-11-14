# syntax=docker/dockerfile:1
# Image backend (PHP-FPM + artisan). Dipakai untuk service app, worker, dan migrate.
# Build context: root repositori. Tidak ada secret/.env di dalam image.

FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath opcache \
    && apk del .build-deps \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY backend/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM base AS final
COPY deploy/docker/php/skillbridge.ini "$PHP_INI_DIR/conf.d/zz-skillbridge.ini"
COPY deploy/docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-skillbridge.conf
COPY deploy/docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app ./
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

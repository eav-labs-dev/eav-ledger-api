FROM composer:2.8 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --no-interaction --optimize

FROM php:8.3-cli-alpine AS runtime

RUN apk add --no-cache libpq oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev postgresql-dev \
    && docker-php-ext-install mbstring pcntl pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=dependencies --chown=www-data:www-data /app /var/www/html
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/ledger-entrypoint

RUN chmod 0755 /usr/local/bin/ledger-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

ENTRYPOINT ["ledger-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]

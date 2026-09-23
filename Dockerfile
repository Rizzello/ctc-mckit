FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.5-fpm-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install bcmath intl pcntl pdo_mysql zip \
    && apk del .build-deps

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

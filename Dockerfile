FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM node:24.14.0-alpine AS frontend

WORKDIR /var/www/html/frontend

RUN npm install --global npm@11.12.0

COPY frontend/package.json frontend/package-lock.json ./

RUN npm ci --ignore-scripts

COPY frontend ./

RUN npm run postinstall && npm run build:pwa

FROM php:8.5-fpm-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install bcmath intl pdo_mysql zip \
    && apk del .build-deps

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend /var/www/html/frontend/dist/pwa ./public

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

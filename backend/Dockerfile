FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libpq-dev \
    libsodium-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) intl pdo pdo_pgsql opcache sodium

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Cache is cleared at runtime via entrypoint or make cc; skip here to avoid DB-dependency

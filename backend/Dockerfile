FROM php:8.5.6-fpm-alpine

RUN apk add --no-cache \
    git \
    icu-dev \
    libpq-dev \
    libsodium-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql sodium

# Hinweis: Zend OPcache ist im offiziellen php:8.5-Image bereits eingebaut
# (statisch gelinkt) und darf nicht via docker-php-ext-install installiert werden.

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Cache is cleared at runtime via entrypoint or make cc; skip here to avoid DB-dependency

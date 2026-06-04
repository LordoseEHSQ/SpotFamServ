FROM php:8.5.6-fpm-alpine

RUN apk add --no-cache \
    git \
    icu-dev \
    libpq-dev \
    libsodium-dev \
    zip \
    unzip \
    ffmpeg \
    python3 \
    curl \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql sodium

# Audio-Extractor: yt-dlp als self-update-fähiges Binary (zipapp). Plan D-B/Update-Modus:
# das offizielle Release-Binary unterstützt `yt-dlp -U` (in-place-Self-Update über das
# Web-Interface), ist architekturunabhängig (reines Python-zipapp → braucht nur python3,
# läuft auf x86_64 und arm64/Pi) und veraltet nicht wie das apk-Paket. ffmpeg (oben)
# transkodiert nach MP3/WAV. Verzeichnis gehört www-data, damit der php-fpm-Worker
# beim Self-Update das Binary ersetzen darf.
RUN mkdir -p /opt/yt-dlp /data/audio \
    && curl -fsSL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /opt/yt-dlp/yt-dlp \
    && chmod 0755 /opt/yt-dlp/yt-dlp \
    && chown -R www-data:www-data /opt/yt-dlp /data/audio \
    && /opt/yt-dlp/yt-dlp --version

# Hinweis: Zend OPcache ist im offiziellen php:8.5-Image bereits eingebaut
# (statisch gelinkt) und darf nicht via docker-php-ext-install installiert werden.

# Firmware-Upload (Provisioning): Default upload_max_filesize=2M / post_max_size=8M
# sind zu klein fuer Firmware-Artefakte (bis 8 MB). 16M gibt multipart-Overhead Luft;
# das App-Limit (FIRMWARE_UPLOAD_MAX_BYTES = 8 MB) bleibt die harte Grenze.
RUN { \
        echo 'upload_max_filesize=16M'; \
        echo 'post_max_size=16M'; \
    } > /usr/local/etc/php/conf.d/zz-spotfam-uploads.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Cache is cleared at runtime via entrypoint or make cc; skip here to avoid DB-dependency

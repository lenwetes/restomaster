# =========================================================================
# STAGE 1: Frontend Build (Vite + TailwindCSS)
# =========================================================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ resources/
COPY public/ public/
COPY vite.config.js tailwind.config.js postcss.config.js ./

RUN npm run build

# =========================================================================
# STAGE 2: Composer Dependencies
# =========================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# =========================================================================
# STAGE 3: Production Runtime (PHP 8.3 FPM + Nginx + Supervisord)
# =========================================================================
FROM php:8.3-fpm-alpine

LABEL maintainer="Sushixpress <soporte@sushixpress.com>"
LABEL description="Sushixpress Enterprise POS & Management Container for Coolify"

# Configure Alpine repositories to use HTTP (prevents TLS handshake timeouts in BuildKit)
# and install base system dependencies including ca-certificates & runtime libraries
RUN sed -i 's/https/http/g' /etc/apk/repositories && \
    apk add --no-cache \
        ca-certificates \
        nginx \
        supervisor \
        curl \
        postgresql-client \
        libpq \
        freetype \
        libjpeg-turbo \
        libpng \
        libzip \
        icu-libs && \
    update-ca-certificates && \
    (addgroup nginx www-data || true)

# Compile PHP extensions natively from source and purge build dependencies
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        icu-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        zip \
        intl \
        sockets \
        pcntl && \
    (docker-php-ext-enable opcache || true) && \
    apk del --no-network .build-deps

# Copy composer binary from composer stage for maintenance tasks
COPY --from=composer-builder /usr/bin/composer /usr/bin/composer

# Configure Nginx, PHP and Supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh && \
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html

# Copy vendor from composer-builder
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy application source code
COPY . /var/www/html

# Copy compiled frontend assets from frontend-builder
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Run package discovery and publish Livewire assets
RUN php artisan package:discover --ansi && \
    php artisan livewire:publish --assets

# Setup permissions and log folders
RUN mkdir -p /var/log/supervisor /var/log/nginx /var/run \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/logs \
             /var/www/html/storage/app/backups \
             /var/www/html/storage/app/public \
             /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/vendor && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Port 80 exposed internally (Coolify maps this to host port 8004)
EXPOSE 80

# Production Healthcheck
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

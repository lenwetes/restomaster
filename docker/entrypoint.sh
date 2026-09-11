#!/bin/sh
set -e

echo "==> Sushixpress Production Container Starting..."

# Ensure proper directory permissions for storage and bootstrap/cache
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/backups \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure storage link exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Creating storage link..."
    php /var/www/html/artisan storage:link || true
fi

# Run database migrations if AUTO_MIGRATE is enabled
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    echo "==> Checking database connection and running migrations..."
    # Wait for PostgreSQL to be ready
    max_retries=30
    counter=0
    until php -r "
        try {
            \$pdo = new PDO('pgsql:host=' . env('DB_HOST', 'postgres') . ';port=' . env('DB_PORT', 5432) . ';dbname=' . env('DB_DATABASE', 'sushixpress'), env('DB_USERNAME', 'postgres'), env('DB_PASSWORD', ''));
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        counter=$((counter + 1))
        if [ $counter -gt $max_retries ]; then
            echo "==> Warning: Database not reachable after $max_retries seconds. Continuing anyway..."
            break
        fi
        echo "==> Waiting for PostgreSQL ($counter/$max_retries)..."
        sleep 2
    done

    echo "==> Running php artisan migrate --force..."
    php /var/www/html/artisan migrate --force || true

    # Run database seeds if AUTO_SEED is enabled
    if [ "${AUTO_SEED:-false}" = "true" ]; then
        echo "==> AUTO_SEED is enabled. Seeding demo and essential data (php artisan db:seed --force)..."
        php /var/www/html/artisan db:seed --force || true
    fi
fi

# Optimize Laravel for production if enabled
if [ "${OPTIMIZE_CACHE:-true}" = "true" ]; then
    echo "==> Caching routes, config and views..."
    php /var/www/html/artisan optimize || true
fi

echo "==> Starting Supervisord (Nginx + PHP-FPM + Queue Worker + Scheduler)..."
exec "$@"

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

touch /var/www/html/storage/logs/laravel.log

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 1. Guarantee valid APP_KEY is present
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = '""' ] || [ "$APP_KEY" = "''" ]; then
    echo "==> APP_KEY not provided. Generating new application encryption key..."
    export APP_KEY=$(php /var/www/html/artisan key:generate --show)
    echo "==> Generated APP_KEY: $APP_KEY"
fi

# Ensure .env exists with APP_KEY so all worker/FPM processes inherit it
if [ ! -f /var/www/html/.env ]; then
    echo "APP_KEY=${APP_KEY}" > /var/www/html/.env
else
    if ! grep -q "APP_KEY=" /var/www/html/.env; then
        echo "APP_KEY=${APP_KEY}" >> /var/www/html/.env
    else
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" /var/www/html/.env
    fi
fi
chown www-data:www-data /var/www/html/.env
chmod 640 /var/www/html/.env

# 2. Clear old cached config before migration
echo "==> Clearing stale configuration caches..."
php /var/www/html/artisan config:clear || true
php /var/www/html/artisan cache:clear || true

# 3. Ensure storage link exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Creating storage link..."
    php /var/www/html/artisan storage:link || true
fi

# 4. Run database migrations if AUTO_MIGRATE is enabled
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    echo "==> Checking database connection and running migrations..."
    max_retries=30
    counter=0
    until php -r "
        \$host = getenv('DB_HOST') ?: 'postgres';
        \$port = getenv('DB_PORT') ?: 5432;
        \$db   = getenv('DB_DATABASE') ?: 'sushixpress';
        \$user = getenv('DB_USERNAME') ?: 'postgres';
        \$pass = getenv('DB_PASSWORD') ?: '';
        try {
            \$pdo = new PDO(\"pgsql:host={\$host};port={\$port};dbname={\$db}\", \$user, \$pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]);
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    "; do
        counter=$((counter + 1))
        if [ $counter -gt $max_retries ]; then
            echo "==> Warning: Database not reachable after $max_retries attempts. Continuing anyway..."
            break
        fi
        echo "==> Waiting for PostgreSQL ($counter/$max_retries)..."
        sleep 2
    done

    echo "==> Running php artisan migrate --force..."
    php /var/www/html/artisan migrate --force || echo "==> Migrations failed or DB not ready"

    # Run database seeds if AUTO_SEED is enabled
    if [ "${AUTO_SEED:-false}" = "true" ]; then
        echo "==> AUTO_SEED is enabled. Seeding demo and essential data (php artisan db:seed --force)..."
        php /var/www/html/artisan db:seed --force || echo "==> Seed failed or already seeded"
    fi
fi

# 5. Optimize Laravel for production if enabled
if [ "${OPTIMIZE_CACHE:-true}" = "true" ]; then
    echo "==> Caching routes and views..."
    php /var/www/html/artisan route:cache || true
    php /var/www/html/artisan view:cache || true
fi

# Re-ensure permissions after artisan commands
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> Starting Supervisord (Nginx + PHP-FPM + Queue Worker + Scheduler)..."
exec "$@"

#!/bin/sh
set -e

echo "==> Sushixpress Production Container Starting..."

# Ensure proper directory permissions for storage, cache, and bootstrap
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/backups \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

touch /var/www/html/storage/logs/laravel.log

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 1. Guarantee valid APP_KEY is present
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = '""' ] || [ "$APP_KEY" = "''" ]; then
    echo "==> APP_KEY not provided. Generating new application encryption key..."
    export APP_KEY=$(php /var/www/html/artisan key:generate --show)
    echo "==> Application encryption key generated successfully."
fi

# 2. Write runtime environment variables to /var/www/html/.env so PHP-FPM workers and Dotenv always have them
echo "==> Writing runtime configuration to /var/www/html/.env..."
cat <<EOF > /var/www/html/.env
APP_NAME="${APP_NAME:-RestoMaster}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost:8004}"
LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
LOG_LEVEL="${LOG_LEVEL:-debug}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-restomaster}"
DB_USERNAME="${DB_USERNAME:-adminresto}"
DB_PASSWORD="${DB_PASSWORD}"

SESSION_DRIVER="${SESSION_DRIVER:-database}"
SESSION_LIFETIME="${SESSION_LIFETIME:-120}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
CACHE_STORE="${CACHE_STORE:-file}"

DEMO_USERS_PASSWORD="${DEMO_USERS_PASSWORD}"
EOF

chown www-data:www-data /var/www/html/.env
chmod 644 /var/www/html/.env

# Ensure PHP-FPM passes environment variables to workers
if [ -d /usr/local/etc/php-fpm.d ]; then
    if ! grep -q "clear_env = no" /usr/local/etc/php-fpm.d/* 2>/dev/null; then
        echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf
    fi
fi

# 3. Clear old cached config before migration
echo "==> Clearing stale configuration caches..."
php /var/www/html/artisan config:clear || true
php /var/www/html/artisan cache:clear || true

# 4. Ensure storage link exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Creating storage link..."
    php /var/www/html/artisan storage:link || true
fi

# 5. Run database migrations if AUTO_MIGRATE is enabled
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
        php /var/www/html/artisan db:seed --force || echo "==> Seed finished or partially seeded"
    fi
fi

# 6. Publish Livewire assets to ensure they are physically present on disk
echo "==> Publishing Livewire assets..."
php /var/www/html/artisan livewire:publish --assets || true

# 7. Optimize Laravel for production
if [ "${OPTIMIZE_CACHE:-true}" = "true" ]; then
    echo "==> Caching routes and views..."
    php /var/www/html/artisan route:cache || true
    php /var/www/html/artisan view:cache || true
fi

# Final permission check
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/vendor

echo "==> Starting Supervisord (Nginx + PHP-FPM + Queue Worker + Scheduler)..."
exec "$@"

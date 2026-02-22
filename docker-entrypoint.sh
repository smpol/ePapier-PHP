#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"
APP_DEBUG="${APP_DEBUG:-0}"
DB_DIR="/app/var/db"
DB_PATH="$DB_DIR/data.db"

mkdir -p "$DB_DIR"
chown -R www-data:www-data /app/var
chmod -R 775 /app/var

# 1. Check if vendor directory exists, if not run composer install
if [ ! -d "/app/vendor" ]; then
    echo "Vendor directory not found. Installing dependencies..."
    composer install --no-dev --no-interaction --optimize-autoloader
fi

# 2. Clean cache on every startup to ensure git pull changes are picked up
echo "Clearing cache..."
php /app/bin/console cache:clear --env="$APP_ENV" --no-warmup

# 3. Always run schema update to catch new migrations
echo "Updating database schema..."
php /app/bin/console doctrine:schema:update --force --no-interaction --complete

# 4. Optional: Rebuild assets if requested or if missing
if [ "$REBUILD_ASSETS" = "1" ] || [ ! -d "/app/public/assets" ]; then
    echo "Installing assets..."
    php /app/bin/console assets:install public
    php /app/bin/console importmap:install
fi

# 5. Seed default timezone if missing
echo "Seeding default timezone..."
php /app/bin/console doctrine:query:sql "INSERT OR IGNORE INTO timezone (id, timezone) VALUES (1, 'Europe/Warsaw')" 2>/dev/null || true

exec frankenphp run --config /etc/caddy/Caddyfile
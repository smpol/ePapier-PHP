#!/bin/sh
set -e

# Development specific entrypoint
echo "🔧 Dev entrypoint starting..."

# Install dependencies if vendor is missing or empty
if [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Installing composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Ensure var directory exists and is writable (important for named volumes)
mkdir -p var/cache var/log var/sessions
chmod -R 777 var

# Run database updates
echo "🗄️ Updating database schema..."
php bin/console doctrine:schema:update --force --no-interaction || true

# Clear cache in case files changed
echo "🧹 Clearing Symfony cache..."
php bin/console cache:clear --no-warmup || true

echo "🚀 Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile

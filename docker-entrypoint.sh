#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"
APP_DEBUG="${APP_DEBUG:-0}"
DB_PATH="/app/var/data.db"

mkdir -p /app/var

# Always run schema update to catch new migrations
echo "Updating database schema..."
APP_ENV="$APP_ENV" APP_DEBUG="$APP_DEBUG" php /app/bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

# Seed default timezone if missing
echo "Seeding default timezone..."
APP_ENV="$APP_ENV" APP_DEBUG="$APP_DEBUG" php /app/bin/console doctrine:query:sql "INSERT OR IGNORE INTO timezone (id, timezone) VALUES (1, 'Europe/Warsaw')" 2>/dev/null || true

exec frankenphp run --config /etc/caddy/Caddyfile
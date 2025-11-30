#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"
APP_DEBUG="${APP_DEBUG:-0}"
DB_PATH="/app/var/data.db"

mkdir -p /app/var

if [ ! -f "$DB_PATH" ]; then
  echo "Initializing database schema at $DB_PATH"
  APP_ENV="$APP_ENV" APP_DEBUG="$APP_DEBUG" php /app/bin/console doctrine:schema:update --force --no-interaction
fi

exec frankenphp run --config /etc/caddy/Caddyfile

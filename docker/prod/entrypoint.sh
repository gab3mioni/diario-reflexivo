#!/bin/sh
set -e

echo "[entrypoint] Starting $(date -Iseconds)"

if [ -d "/shared-public" ]; then
    echo "[entrypoint] Syncing public/ to shared volume..."
    cp -a /var/www/html/public/. /shared-public/
fi

if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Waiting for MySQL at $DB_HOST:${DB_PORT:-3306}..."
    timeout=30
    while ! mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" --silent 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ "$timeout" -le 0 ]; then
            echo "[entrypoint] ERROR: MySQL not reachable after 30s"
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] MySQL is up"
fi

echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

echo "[entrypoint] Warming up caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan storage:link 2>/dev/null || true

echo "[entrypoint] Ready — handing off to: $@"
exec "$@"

#!/bin/sh
set -e

echo "[entrypoint] Starting $(date -Iseconds)"

# Sincronizar public/ para o volume compartilhado com nginx.
# O volume app-public é montado em /shared-public dentro do app container.
# Nginx monta o mesmo volume read-only como seu root.
if [ -d "/shared-public" ]; then
    echo "[entrypoint] Syncing public/ to shared volume..."
    cp -a /var/www/html/public/. /shared-public/
fi

# Aguarda o MySQL estar acessível (timeout 30s).
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

# Migrations (idempotente — --force pula confirmação em produção)
echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

# Cache warmup — acelera bootstrap em ~40%
echo "[entrypoint] Warming up caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Storage link (idempotente)
php artisan storage:link 2>/dev/null || true

echo "[entrypoint] Ready — handing off to: $@"
exec "$@"

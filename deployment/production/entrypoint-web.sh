#!/bin/bash
set -e

cd /var/www/html

php artisan config:clear
php artisan config:cache
php artisan route:cache

if ! php artisan list | grep -q "octane:start"; then
  echo "[web] Octane 尚未安裝，請先執行: composer require laravel/octane && php artisan octane:install"
  exit 1
fi

exec php artisan octane:start \
  --server="${OCTANE_SERVER:-frankenphp}" \
  --host=0.0.0.0 \
  --port="${OCTANE_PORT:-8000}" \
  --workers="${OCTANE_WORKERS:-4}" \
  --task-workers="${OCTANE_TASK_WORKERS:-2}" \
  --max-requests="${OCTANE_MAX_REQUESTS:-500}"

#!/bin/bash
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[web] vendor/ 不存在，執行 composer install..."
  composer install --no-dev --optimize-autoloader --no-interaction
fi

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ ! -f vendor/laravel/octane/src/Commands/StartCommand.php ]; then
  echo "[web] Octane 尚未安裝，請先執行: composer require laravel/octane && php artisan octane:install"
  exit 1
fi

if [ ! -f public/bootstrap.php ] || [ ! -f public/frankenphp-worker.php ]; then
  echo "[web] 缺少 Octane worker 檔案，嘗試執行 octane:install --server=frankenphp"
  php artisan octane:install --server=frankenphp --no-interaction
fi

if [ ! -f public/bootstrap.php ] && [ -f vendor/laravel/octane/bin/bootstrap.php ]; then
  echo "[web] 從 vendor 複製 public/bootstrap.php"
  cp vendor/laravel/octane/bin/bootstrap.php public/bootstrap.php
fi

if [ ! -f public/frankenphp-worker.php ] && [ -f vendor/laravel/octane/bin/frankenphp-worker.php ]; then
  echo "[web] 從 vendor 複製 public/frankenphp-worker.php"
  cp vendor/laravel/octane/bin/frankenphp-worker.php public/frankenphp-worker.php
fi

if [ ! -s public/frankenphp-worker.php ]; then
  echo "[web] public/frankenphp-worker.php 不存在或為空檔"
  exit 1
fi

exec php artisan octane:start \
  --server="${OCTANE_SERVER:-frankenphp}" \
  --host=0.0.0.0 \
  --port="${OCTANE_PORT:-8000}" \
  --workers="${OCTANE_WORKERS:-4}" \
  --task-workers="${OCTANE_TASK_WORKERS:-2}" \
  --max-requests="${OCTANE_MAX_REQUESTS:-500}"

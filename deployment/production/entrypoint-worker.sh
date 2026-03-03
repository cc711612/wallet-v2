#!/bin/bash
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[worker] vendor/ 不存在，請先在 host 執行: composer install --no-dev --optimize-autoloader"
  exit 1
fi

php artisan config:clear
php artisan config:cache

mkdir -p /var/log/supervisor

exec /usr/bin/supervisord -n -c /var/www/html/deployment/production/supervisor/supervisord.conf

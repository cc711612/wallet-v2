#!/bin/bash
set -e

cd /var/www/html

# shellcheck source=preflight.sh
source /var/www/html/deployment/production/preflight.sh
preflight_check worker

if [ ! -f vendor/autoload.php ]; then
  echo "[worker] vendor/ 不存在，執行 composer install..."
  composer install --no-dev --optimize-autoloader --no-interaction
fi

php artisan config:clear
php artisan config:cache

mkdir -p /var/log/supervisor

exec /usr/bin/supervisord -n -c /var/www/html/deployment/production/supervisor/supervisord.conf

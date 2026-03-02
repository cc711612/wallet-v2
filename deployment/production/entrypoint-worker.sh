#!/bin/bash
set -e

cd /var/www/html

php artisan config:clear
php artisan config:cache

mkdir -p /var/log/supervisor

exec /usr/bin/supervisord -n -c /var/www/html/deployment/production/supervisor/supervisord.conf

#!/bin/sh
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Clearing config cache..."
php artisan config:clear
php artisan cache:clear

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisord.conf

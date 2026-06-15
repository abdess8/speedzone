#!/usr/bin/env bash
set -e

PORT="${PORT:-8080}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
php artisan storage:link 2>/dev/null || true

if [ -n "${APP_KEY}" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec apache2-foreground

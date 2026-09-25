#!/bin/sh
set -e

cd /var/www/html

# Generate an APP_KEY on first boot if none was provided via Railway variables
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php artisan key:generate --show)
fi

# If DB_CONNECTION is sqlite, make sure the file exists (fresh volume / container)
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

php artisan config:clear
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"

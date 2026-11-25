#!/bin/sh
set -e
# Regenera las cachés de Laravel con el .env montado y deja storage escribible.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache
php artisan optimize --ansi || true
chown -R www-data:www-data bootstrap/cache storage/framework
exec "$@"

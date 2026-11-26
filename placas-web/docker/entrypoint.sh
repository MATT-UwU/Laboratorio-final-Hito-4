#!/bin/sh
# © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4
set -e
# Regenera las cachés de Laravel con el .env montado y deja storage escribible.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache
php artisan optimize --ansi || true
chown -R www-data:www-data bootstrap/cache storage/framework
exec "$@"

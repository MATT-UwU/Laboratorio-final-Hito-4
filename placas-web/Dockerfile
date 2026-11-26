# © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4
# syntax=docker/dockerfile:1
# Imagen de producción del panel web del peaje (Laravel 12, Blade, MySQL).
# La inferencia de IA corre en Google Colab: este contenedor no lleva Python ni modelos.

FROM php:8.3-fpm-bookworm AS php-base
RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip exif pcntl gd opcache \
 && apt-get purge -y --auto-remove libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
 && apt-get install -y --no-install-recommends libicu72 libzip4 libpng16-16 libjpeg62-turbo libwebp7 libfreetype6 \
 && apt-get clean && find /var/lib/apt/lists -type f -delete
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
WORKDIR /var/www/html

FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && composer dump-autoload --optimize --no-dev --no-scripts \
 && php artisan package:discover --ansi

FROM php-base AS app
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
 && ln -sfn ../storage/app/public public/storage
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

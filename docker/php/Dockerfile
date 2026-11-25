# syntax=docker/dockerfile:1.7
#
# One Dockerfile, three targets:
#   dev        -> used by compose.yaml (code is mounted from your machine)
#   production -> PHP-FPM with the code, vendor/ and built assets baked in (app, horizon, scheduler, reverb)
#   web        -> nginx with the public/ folder baked in, talks to "app" on port 9000
#
# compose.prod.yaml builds production + web for you (./deploy.sh). By hand:
#   docker build -f docker/php/Dockerfile --target production -t laravel13-app:latest .
#   docker build -f docker/php/Dockerfile --target web        -t laravel13-app-web:latest .

ARG PHP_VERSION=8.4

# ---------------------------------------------------------------- base
FROM php:${PHP_VERSION}-fpm-bookworm AS base

# mlocati/php-extension-installer handles the system libraries for each extension.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql redis gd intl zip bcmath pcntl opcache exif sockets \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client unzip git curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html

# ---------------------------------------------------------------- dev
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the container user to your machine user so files you create are yours.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data \
    && mkdir -p /home/www-data && chown www-data:www-data /home/www-data && usermod -d /home/www-data www-data

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
USER www-data
CMD ["php-fpm"]

# ---------------------------------------------------------------- composer dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs

# ---------------------------------------------------------------- front-end build
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY app/Modules ./app/Modules
# Tailwind also scans Laravel's pagination view for class names.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------------------------------------------------------------- production
FROM base AS production

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -f public/hot \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------- web (nginx)
FROM nginx:1.27-alpine AS web

COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public
# Uploaded files live in the shared "storage" volume (see compose.prod.yaml).
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 CMD wget -qO /dev/null http://127.0.0.1/up || exit 1

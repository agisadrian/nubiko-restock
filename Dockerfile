# ---- Stage 1: build frontend assets (Vite/Vue) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig.json ./
RUN npm run build

# ---- Stage 2: PHP application ----
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        git unzip libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev oniguruma-dev sqlite-dev postgresql-dev libxml2-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql pdo_pgsql pdo_sqlite \
        zip gd intl mbstring bcmath xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

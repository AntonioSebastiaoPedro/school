# Alvos: app (php-fpm com o código) e web (nginx com o frontend compilado). O contexto é a raiz do repositório.
FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache postgresql-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip bcmath opcache pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM base AS vendor
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM node:22-alpine AS assets
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend ./
RUN npm run build

FROM base AS app
COPY backend ./
COPY --from=vendor /var/www/html/vendor ./vendor
# Sem --no-scripts: o package:discover regenera o cache de providers só com as dependências de produção.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

FROM nginx:stable-alpine AS web
COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY backend/public /var/www/html/public
COPY --from=assets /frontend/dist /var/www/frontend

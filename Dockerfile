# Multi-stage Dockerfile for ESP-v2 Production Platform
FROM node:22-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.3-fpm-alpine AS backend
WORKDIR /var/www/html

RUN apk add --no-gc --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    supervisor \
    nginx

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY backend/composer*.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY backend/ ./
COPY --from=frontend-builder /app/frontend/dist /var/www/html/public

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 9000
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

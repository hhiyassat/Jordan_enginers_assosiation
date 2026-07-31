# CS-09 · ESP-v2 Docker image
#
# Multi-stage: node builds the SPA, php-fpm-alpine ships the runtime.
# The runtime image bakes nginx + supervisord alongside php-fpm so
# `docker compose up` on this image gives you a full HTTP surface
# out of the box. A dedicated queue-worker service reuses the same
# image but overrides the CMD (see docker-compose.yml).

FROM node:22-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM php:8.3-fpm-alpine AS backend
WORKDIR /var/www/html

# System deps + php extensions. `--no-cache` (was `--no-gc`, a typo
# that caused apk to warn on every build).
RUN apk add --no-cache \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        supervisor \
        nginx \
        curl \
        bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd opcache zip pcntl \
    && apk add --no-cache --virtual .build-deps autoconf g++ make linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Prime the composer cache. Application code copied after so that
# a code-only change reuses the vendor layer.
COPY backend/composer*.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Application source + built SPA assets.
COPY backend/ ./
COPY --from=frontend-builder /app/frontend/dist /var/www/html/public

# Bake supervisord + nginx configs shipped in the repo. This is
# what the previous Dockerfile was missing — the image referenced
# /etc/supervisor/supervisord.conf without ever COPYing one in.
COPY deployment/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY deployment/supervisor/queue-worker-only.conf /etc/supervisor/queue-worker-only.conf
COPY deployment/supervisor/scheduler.conf /etc/supervisor/scheduler.conf
COPY deployment/nginx/default.conf /etc/nginx/http.d/default.conf
COPY deployment/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# Finalize composer autoload / discover packages / etc.
RUN composer dump-autoload --optimize --no-scripts \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R a+rX /var/www/html \
    && mkdir -p /run/nginx

# Container-relative healthcheck: nginx must answer on :80.
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=6 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

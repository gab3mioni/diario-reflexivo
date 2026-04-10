# =============================================================================
# Dockerfile de PRODUÇÃO — Laravel + Inertia/React
#
# Arquitetura:
#   Stage 1 (composer-deps): deps PHP de produção
#   Stage 2 (node-build):    assets Vite (CSS + JS)
#   Stage 3 (runtime):       PHP-FPM Alpine, non-root, OPcache tuned
#
# Nginx roda em container separado (docker-compose.prod.yml).
#
# Build:
#   docker build -f docker/prod/Dockerfile -t diario-reflexivo:latest .
# =============================================================================

FROM composer:2 AS composer-deps
WORKDIR /build

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

COPY . .
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

FROM node:22-alpine AS node-build
WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit

COPY resources ./resources
COPY public ./public
COPY tsconfig.json vite.config.ts components.json ./
RUN npm run build

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev oniguruma-dev libzip-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring gd intl zip opcache bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && apk add --no-cache \
        libpng libjpeg-turbo freetype icu-libs oniguruma libzip \
        curl mysql-client \
    && rm -rf /tmp/pear /var/cache/apk/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=64'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.jit=1255'; \
    echo 'opcache.jit_buffer_size=128M'; \
} > "$PHP_INI_DIR/conf.d/opcache-prod.ini"

RUN { \
    echo 'expose_php=Off'; \
    echo 'memory_limit=256M'; \
    echo 'post_max_size=20M'; \
    echo 'upload_max_filesize=10M'; \
    echo 'max_execution_time=60'; \
    echo 'date.timezone=America/Sao_Paulo'; \
} > "$PHP_INI_DIR/conf.d/app-prod.ini"

RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 4'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 6'; \
    echo 'pm.max_requests = 1000'; \
    echo 'pm.status_path = /fpm-status'; \
    echo 'ping.path = /fpm-ping'; \
    echo 'catch_workers_output = yes'; \
    echo 'decorate_workers_output = no'; \
    echo 'clear_env = no'; \
} > /usr/local/etc/php-fpm.d/zz-prod.conf

RUN addgroup -g 1000 laravel \
    && adduser -u 1000 -G laravel -s /bin/sh -D laravel

WORKDIR /var/www/html

COPY --chown=laravel:laravel . .
COPY --from=composer-deps --chown=laravel:laravel /build/vendor ./vendor
COPY --from=node-build --chown=laravel:laravel /build/public/build ./public/build

RUN mkdir -p storage/logs storage/framework/{cache,sessions,testing,views} bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY --chown=laravel:laravel docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER laravel

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "--nodaemonize"]

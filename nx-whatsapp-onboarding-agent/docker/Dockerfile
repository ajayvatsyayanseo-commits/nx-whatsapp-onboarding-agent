FROM php:8.2-fpm-bookworm AS base

ARG APP_DIR=/var/www/html
ENV APP_DIR=${APP_DIR}
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        nginx \
        supervisor \
        unzip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        zip \
    && docker-php-ext-install intl opcache pcntl pdo pdo_mysql pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR ${APP_DIR}

COPY . ${APP_DIR}
COPY nx-whatsapp-onboarding-agent/docker/nginx.conf /etc/nginx/nginx.conf
COPY nx-whatsapp-onboarding-agent/docker/supervisor-worker.conf /etc/supervisor/conf.d/worker.conf
COPY nx-whatsapp-onboarding-agent/docker/entrypoint.sh /usr/local/bin/nxtutors-entrypoint

RUN chmod +x /usr/local/bin/nxtutors-entrypoint \
    && if [ -f composer.json ]; then composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; fi \
    && mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

EXPOSE 80

ENTRYPOINT ["nxtutors-entrypoint"]
CMD ["web"]

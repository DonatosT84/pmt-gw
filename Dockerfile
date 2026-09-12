# Dev / CI image. Builds the full project (source + Composer dependencies).
# Not intended for production deployment.
#
# `docker compose build` handles DNS via `build.network: host` in
# docker-compose.yml. For a bare `docker build`, add `--network=host` on hosts
# where the Docker bridge network has no working resolver.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# 1. Dependencies first, so this layer is cached until the lock file changes.
#    Dev dependencies are kept on purpose: `vendor/bin/phpunit` runs in this image.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

# 2. Application source (.dockerignore keeps vendor/, var/, .env.local, .git out).
COPY . .
RUN composer dump-autoload --optimize --no-scripts \
    && mkdir -p var && chmod -R ug+w var

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD ["php", "-r", "exit(@file_get_contents('http://localhost:8000/pay') !== false ? 0 : 1);"]

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]

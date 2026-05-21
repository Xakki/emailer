# Reusable image for running the test/static-analysis tool-chain locally,
# mirroring the CI matrix. Build with --build-arg PHP_VERSION=8.4 or 8.5.
#
#   docker build -f docker/ci/Dockerfile --build-arg PHP_VERSION=8.5 -t emailer-test:8.5 .
#   docker run --rm -v "$PWD":/app -w /app emailer-test:8.5 \
#       sh -c "composer install && composer cs-check && composer phpstan && composer test"
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev libssl-dev libsqlite3-dev pkg-config \
 && docker-php-ext-configure intl \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite intl mbstring zip bcmath sockets \
 && pecl install redis && docker-php-ext-enable redis \
 && pecl install xdebug && docker-php-ext-enable xdebug \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV XDEBUG_MODE=off
WORKDIR /app

FROM php:8.4-cli

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        kcat \
        libpq-dev \
        unzip \
        zip \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && git config --global --add safe.directory /var/www/html \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

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
    && pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ARG WWWGROUP
ARG WWWUSER

RUN if [ -n "${WWWGROUP}" ] && [ -n "${WWWUSER}" ]; then \
        groupadd --force --gid "${WWWGROUP}" sail \
        && useradd --uid "${WWWUSER}" --gid "${WWWGROUP}" --create-home --shell /bin/bash sail; \
    else \
        groupadd --force sail \
        && useradd --gid sail --create-home --shell /bin/bash sail; \
    fi \
    && mkdir -p /home/sail/.composer \
    && chown -R sail:sail /home/sail /var/www/html

USER sail

RUN git config --global --add safe.directory /var/www/html

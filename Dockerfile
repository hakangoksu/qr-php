FROM composer:2.10.3 AS composer

FROM dunglas/frankenphp:1.13-php8.4-trixie AS base

RUN install-php-extensions pdo_mysql zip opcache gd

COPY --from=composer /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

FROM base AS test

ENV APP_ENV=test

COPY composer.json composer.lock symfony.lock ./
# No plugins and no scripts: the packages are downloaded, nothing from them runs.
RUN composer install --no-plugins --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload

CMD ["php", "bin/phpunit"]

FROM base AS prod

ENV APP_ENV=prod \
    SERVER_NAME=:8080

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY .env ./
COPY bin bin
COPY config config
COPY docker docker
COPY migrations migrations
COPY public public
COPY src src
COPY templates templates

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && rm /usr/bin/composer

RUN useradd --uid 1000 --no-create-home app \
    && setcap -r /usr/local/bin/frankenphp \
    && chown -R app:app /config/caddy /data/caddy /app/var
USER app

EXPOSE 8080
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

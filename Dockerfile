FROM composer:2.10.3 AS composer

FROM dunglas/frankenphp:1.13-php8.4-trixie

RUN install-php-extensions pdo_mysql zip opcache gd

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    SERVER_NAME=:8080 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
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

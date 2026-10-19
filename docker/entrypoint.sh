#!/bin/sh
# Applies pending Doctrine migrations, then hands over to the image's own entrypoint,
# which starts FrankenPHP. Same idea as Database.Migrate() at startup in the .NET app.
set -e

if [ "${MIGRATE_ON_STARTUP:-1}" = "1" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"

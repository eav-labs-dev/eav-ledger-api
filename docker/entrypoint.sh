#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
    echo "APP_KEY was not provided; using an ephemeral key for this container run."
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"

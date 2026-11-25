#!/bin/sh
# Production entrypoint: cache config/routes/views on start, run migrations
# only when RUN_MIGRATIONS=true (set it on one container, not on every worker).
set -e

php artisan storage:link --quiet 2>/dev/null || true
php artisan optimize

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force
    php artisan module:sync
fi

exec "$@"

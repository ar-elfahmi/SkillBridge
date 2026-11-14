#!/bin/sh
set -e
# Cache config/route/event memakai env runtime (image tidak membawa .env).
php artisan optimize --no-interaction
exec "$@"

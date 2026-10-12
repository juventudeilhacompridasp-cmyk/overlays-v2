#!/bin/sh
set -e
cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Só o processo web (php-fpm) gera os caches; os demais usam o que já existe.
if [ "$1" = "php-fpm" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"

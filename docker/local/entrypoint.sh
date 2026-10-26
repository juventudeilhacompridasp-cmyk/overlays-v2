#!/bin/sh
set -e
cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    echo "vendor vazio: rode  .\\swarm.ps1 update  no Windows (instala as dependências). Tentando de novo em 20s..." >&2
    sleep 20
    exit 1
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Só o processo web (php-fpm) gera os caches; os demais usam o que já existe. O `swarm.ps1 update` os refaz a cada atualização.
if [ "$1" = "php-fpm" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"

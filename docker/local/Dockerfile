# syntax=docker/dockerfile:1.7
# Imagem de EXECUÇÃO da stack Swarm local (docker-stack.yml): só PHP-FPM + extensões + Composer + Node.
# O código do projeto NÃO entra na imagem: é montado por volume, então esta imagem só precisa ser refeita se este arquivo mudar.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-alpine
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis pcntl posix bcmath intl gd exif zip opcache @composer \
    && apk add --no-cache libstdc++ tzdata git unzip
COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENV COMPOSER_HOME=/tmp/composer COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=0
WORKDIR /var/www/html
USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

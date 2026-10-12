# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4

# ---- base: PHP-FPM + extensões + Node (para o SSR do Inertia) ----
FROM php:${PHP_VERSION}-fpm-alpine AS php-base
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis pcntl posix bcmath intl gd exif zip opcache \
    && apk add --no-cache libstdc++ tzdata
COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
WORKDIR /var/www/html

# ---- dev: usado pelo docker-compose.yml (código montado por volume) ----
FROM php-base AS dev
ARG UID=1000
ARG GID=1000
RUN install-php-extensions @composer && apk add --no-cache git unzip \
    && (getent group ${GID} || addgroup -g ${GID} app) \
    && (getent passwd ${UID} || adduser -D -u ${UID} -G $(getent group ${GID} | cut -d: -f1) app) \
    && sed -i "s/^user = .*/user = $(getent passwd ${UID} | cut -d: -f1)/; s/^group = .*/group = $(getent group ${GID} | cut -d: -f1)/" /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/99-app.ini
USER ${UID}:${GID}

# ---- vendor: dependências PHP de produção ----
FROM php-base AS vendor
RUN install-php-extensions @composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- assets: build do Vite (cliente + SSR) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
RUN npm run build

# ---- prod: imagem única para app, horizon, scheduler, reverb, ssr e migrate ----
FROM php-base AS prod
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --from=assets --chown=www-data:www-data /app/bootstrap/ssr ./bootstrap/ssr
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- nginx: serve estáticos e repassa .php ao app:9000 ----
FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public

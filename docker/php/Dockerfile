# syntax=docker/dockerfile:1
#
# Un solo Dockerfile, dos destinos:
#   --target dev   recarga en caliente, dependencias de desarrollo, sin codigo copiado
#   --target prod  codigo dentro de la imagen, opcache sellado. Es la que va al registro.

# ─────────────────────────────── base ────────────────────────────────
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis gd intl zip bcmath opcache \
    && rm /usr/local/bin/install-php-extensions

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# ──────────────────────────────── dev ────────────────────────────────
FROM base AS dev

ARG UID=1000
ARG GID=1000

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# La recarga en caliente es esto y nada mas: opcache revalida el mtime en cada
# peticion, de modo que el codigo del bind mount surte efecto sin reconstruir
# la imagen ni reiniciar el contenedor.
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\n' \
    > /usr/local/etc/php/conf.d/zz-opcache.ini

# www-data toma el uid del anfitrion: lo que PHP escribe en storage/ queda
# editable desde fuera del contenedor.
RUN apk add --no-cache shadow \
    && groupmod -g "${GID}" www-data \
    && usermod -u "${UID}" -g "${GID}" www-data \
    && apk del shadow

USER www-data

# ─────────────────────────────── assets ──────────────────────────────
FROM node:24-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ──────────────────────────────── prod ───────────────────────────────
FROM base AS prod

# Sin validate_timestamps: en produccion el codigo no cambia bajo los pies del
# proceso, y revalidar el mtime en cada peticion solo cuesta llamadas al sistema.
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=192\nopcache.max_accelerated_files=20000\n' \
    > /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm /usr/local/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache

# ──────────────────────────────── web ────────────────────────────────
# nginx con los estaticos ya construidos. Sale del mismo build que `prod`,
# asi que servidor web y aplicacion nunca sirven versiones distintas.
FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public

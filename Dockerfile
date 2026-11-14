FROM php:8.4.27-fpm-alpine@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d AS php-base
RUN apk add --no-cache libpq icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl bcmath pcntl zip \
    && apk del .build-deps
COPY resources/runtime/php.ini /usr/local/etc/php/conf.d/netclean.ini
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2.9.3@sha256:c404e6f07bdebf8a8c605be5b5fab88eef737f6e4acfba3651d39c824ce224d4 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

FROM node:26.5.0-alpine@sha256:e88a35be04478413b7c71c455cd9865de9b9360e1f43456be5951032d7ac1a66 AS frontend
WORKDIR /app
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
RUN npm ci --ignore-scripts && npm run build

FROM php-base AS app
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
RUN chmod -R u=rwX,go=rX . \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginx:1.29-alpine@sha256:5616878291a2eed594aee8db4dade5878cf7edcb475e59193904b198d9b830de AS web
COPY resources/runtime/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
EXPOSE 80

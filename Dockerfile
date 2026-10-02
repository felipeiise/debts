FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache libxml2 curl oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libxml2-dev curl-dev oniguruma-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install dom simplexml curl mbstring \
    && apk del .build-deps
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
COPY public /var/www/html/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

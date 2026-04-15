FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx supervisor bash \
    icu-dev libzip-dev oniguruma-dev \
    git unzip

RUN docker-php-ext-install pdo_mysql intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

COPY ./docker/nginx.conf /etc/nginx/http.d/default.conf
COPY ./docker/supervisord.conf /etc/supervisord.conf
COPY ./docker/ca-certificate.crt /etc/ssl/certs/ca-certificate.crt

RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY ./docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080
CMD ["/start.sh"]

FROM php:8.2-fpm-alpine

RUN apk add --no-cache bash git curl libzip tzdata unzip mysql-client shadow
RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --optimize-autoloader || true

COPY . /var/www/html

COPY docker/php/php-entrypoint.sh /usr/local/bin/php-entrypoint.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh

EXPOSE 9000
CMD ["/usr/local/bin/php-entrypoint.sh"]

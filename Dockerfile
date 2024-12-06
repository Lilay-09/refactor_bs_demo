FROM php:8.2-cli-alpine

WORKDIR /var/www

RUN apk add --no-cache \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install zip pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . . 
RUN chown -R www-data:www-data /var/www

EXPOSE 8000

USER www-data

CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]

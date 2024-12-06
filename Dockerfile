# Use the official PHP image with Alpine
FROM php:8.2-cli-alpine

# Set working directory
WORKDIR /var/www

# Install dependencies and PHP extensions
RUN apk add --no-cache \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files and set proper permissions
COPY . . 
RUN chown -R www-data:www-data /var/www

# Expose port 8000 to the outside world
EXPOSE 8000

# Set a non-root user for security
USER www-data

# Start PHP's built-in server
CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]

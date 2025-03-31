# Use official PHP CLI image
FROM php:8.2-cli-alpine

# Set working directory
WORKDIR /var/www

# Install dependencies and PHP extensions
RUN apk add --no-cache \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    mysql-client \
    && docker-php-ext-install zip pdo pdo_mysql pdo_pgsql \
    && rm -rf /var/cache/apk/*

# Copy Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Set proper permissions for the project files
RUN chown -R www-data:www-data /var/www

# Expose port
EXPOSE 8001

# Switch to non-root user
USER www-data

# Run PHP built-in server
CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]

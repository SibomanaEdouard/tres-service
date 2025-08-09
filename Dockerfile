# Use an official PHP image as the base image
FROM php:8.2-fpm-alpine

# Set the working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Get the latest Composer version
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the Laravel application files
COPY . .

# Run Composer install
RUN composer install --no-dev --optimize-autoloader

# Set permissions for the Laravel storage and cache directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
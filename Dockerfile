FROM docker.io/library/php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    acl \
    fcgi \
    file \
    gettext \
    git \
    icu-dev \
    libzip-dev \
    zip

# Install PHP extensions
RUN docker-php-ext-configure intl && docker-php-ext-install \
    intl \
    opcache \
    zip

# Install Composer
COPY --from=docker.io/library/composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock symfony.lock ./

# Install dependencies
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# Copy application source
COPY . .

# Run composer scripts (auto-scripts)
RUN composer run-script post-install-cmd

# Permissions
RUN chown -R www-data:www-data var

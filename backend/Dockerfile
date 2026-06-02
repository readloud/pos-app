FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libreadline-dev \
    libredis-dev \
    zip \
    unzip \
    git \
    curl \
    nano \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    gd \
    zip \
    mbstring \
    exif \
    pcntl \
    bcmath \
    intl \
    curl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install OPcache for performance
RUN docker-php-ext-install opcache && docker-php-ext-enable opcache

# Install intl dependencies
RUN apt-get update && apt-get install -y libicu-dev && docker-php-ext-install intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Set environment variable to allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Explicitly require sanctum if needed
RUN composer require laravel/sanctum --no-interaction

# Run Composer scripts
RUN composer run-script post-autoload-dump

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create .env file if not exists
RUN if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ==========================================
# STAGE 1: Build Frontend Assets (Node/Vue)
# ==========================================
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copy package files and install JS dependencies
COPY package*.json ./
RUN npm ci

# Copy application source files needed for asset bundling
COPY resources/ ./resources/
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY public/ ./public/

# Build production frontend assets (Vite)
RUN npm run build


# ==========================================
# STAGE 2: PHP & Nginx Application Image
# ==========================================
FROM php:8.3-fpm-bookworm

# Set working directory
WORKDIR /var/www/html

# 1. Install system dependencies & PHP extensions required by Laravel/PostgreSQL
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libicu-dev \
    git \
    curl \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        bcmath \
        opcache \
        intl \
        zip \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Copy Composer binary directly from the official image (avoids network/curl build failures)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# 3. Copy application files
COPY . .

# 4. Copy built JS/CSS assets from the Node build stage
COPY --from=node-builder /app/public/build ./public/build

# 5. Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. Set appropriate permissions for Laravel storage & cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Configure Production OPcache
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Expose HTTP port
EXPOSE 80

# Start Supervisor to run PHP-FPM and Nginx simultaneously
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
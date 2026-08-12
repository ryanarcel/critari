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

# 6. Copy Nginx configuration
COPY nginx.conf /etc/nginx/sites-available/default
RUN mkdir -p /etc/nginx/sites-enabled && \
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# 7. Copy Supervisor configuration and entrypoint script
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 8. Set appropriate permissions for Laravel storage & cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Configure Production OPcache
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 10. Configure PHP-FPM to log to stdout
RUN sed -i 's/;catch_workers_output = yes/catch_workers_output = yes/' /usr/local/etc/php-fpm.d/docker.conf && \
    sed -i 's/;decorate_workers_output = no/decorate_workers_output = no/' /usr/local/etc/php-fpm.d/docker.conf

# 11. Create supervisor log directory
RUN mkdir -p /var/log/supervisor && chmod 755 /var/log/supervisor

# Expose HTTP port
EXPOSE 80

# Health check to verify container is running properly
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start application with entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
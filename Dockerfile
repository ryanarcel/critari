# Stage 1: Build SPA Frontend Assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: PHP Application & Web Server
FROM php:8.3-fpm-alpine

# Install PostgreSQL build dependencies and PHP extensions
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Install Nginx
RUN apk add --no-cache nginx

WORKDIR /var/www/html
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies without dev packages
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Nginx config & Permissions
COPY nginx.conf /etc/nginx/http.d/default.conf
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
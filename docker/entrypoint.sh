#!/bin/bash
set -e

echo "Starting Laravel application..."

# Wait for database to be ready
if [ -z "$SKIP_DB_WAIT" ]; then
    echo "Waiting for database connection..."
    max_attempts=30
    attempt=1
    while [ $attempt -le $max_attempts ]; do
        if php artisan tinker --execute="exit" 2>/dev/null; then
            echo "Database connection successful"
            break
        fi
        echo "Attempt $attempt/$max_attempts: Database not ready yet, retrying in 2 seconds..."
        sleep 2
        attempt=$((attempt + 1))
    done
    if [ $attempt -gt $max_attempts ]; then
        echo "Warning: Could not connect to database after $max_attempts attempts"
    fi
fi

# Run migrations if DB_MIGRATE is set or true by default
if [ "$DB_MIGRATE" != "false" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || true
fi

# Cache configuration for better performance
echo "Caching configuration..."
php artisan config:cache || true

# Create required directories if they don't exist
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Ensure proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "Application ready. Starting supervisord..."

# Execute supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

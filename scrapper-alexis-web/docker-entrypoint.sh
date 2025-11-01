#!/bin/bash
set -e

echo "========================================="
echo "Starting Web Container"
echo "========================================="

# Set correct permissions for Laravel directories
echo "Setting Laravel permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Generate Laravel app key if not exists
if [ ! -f /var/www/.env ]; then
    echo "Creating .env file..."
    if [ -f /var/www/.env.example ]; then
        cp /var/www/.env.example /var/www/.env
    else
        # Create basic .env
        cat > /var/www/.env << EOF
APP_NAME="Scraper Admin"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=/app/data/scraper.db
EOF
    fi
fi

# Generate app key if empty
if grep -q "APP_KEY=$" /var/www/.env || grep -q "APP_KEY=\"\"" /var/www/.env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration
echo "Optimizing Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
# Skip view:cache as it may fail with custom components

# Create SQLite database directory if it doesn't exist (mounted volume)
if [ ! -d "/app/data" ]; then
    echo "Warning: /app/data volume not mounted!"
fi

# Bugfix: Ensure /app/auth directory has correct permissions for file uploads
if [ -d "/app/auth" ]; then
    echo "Setting permissions for /app/auth..."
    chown -R www-data:www-data /app/auth
    chmod 755 /app/auth
    echo "✓ /app/auth permissions set"
fi

# Set database path environment variable for Laravel
export DB_DATABASE=/app/data/scraper.db

# Initialize database if it doesn't exist
if [ ! -f "$DB_DATABASE" ]; then
    echo "Database not found, creating new database..."
    touch "$DB_DATABASE"
    chmod 666 "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

# Run migrations (always, for both new and existing databases)
echo "Running migrations..."
php artisan migrate --force

# Seed admin user (always, seeder is idempotent with updateOrCreate)
echo "Seeding admin user..."
php artisan db:seed --class=AdminUserSeeder --force || echo "Warning: AdminUserSeeder failed"

# Link storage to scraper data if not already linked
if [ ! -L /var/www/public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link || true
fi

# Link message_images to scraper data directory
if [ -L /var/www/public/message_images ]; then
    echo "Removing old message_images symlink..."
    rm /var/www/public/message_images
fi
if [ ! -L /var/www/public/message_images ]; then
    echo "Creating message_images symlink to /app/data/message_images..."
    ln -s /app/data/message_images /var/www/public/message_images
    echo "✓ Message images symlink created"
fi

echo ""
echo "========================================="
echo "Web Container Ready!"
echo "========================================="
echo "Database: $DB_DATABASE"
echo "Web server: http://localhost:80"
echo ""
echo "Starting Nginx and PHP-FPM..."
echo "========================================="

# Start supervisor (which manages nginx and php-fpm)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf


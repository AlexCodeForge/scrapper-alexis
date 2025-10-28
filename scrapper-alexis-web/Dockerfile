# Base image with PHP 8.2 FPM
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # PHP extensions dependencies
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    sqlite3 \
    libsqlite3-dev \
    # Nginx
    nginx \
    # Node.js and npm
    curl \
    gnupg \
    # Utilities
    git \
    unzip \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Docker CLI for container management
RUN curl -fsSL https://get.docker.com | sh \
    && usermod -aG docker www-data

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_sqlite \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Create Laravel required directories BEFORE composer install
RUN mkdir -p \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/storage/app/livewire-tmp \
    /var/www/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm install && npm run build

# Set permissions for Laravel
RUN chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache

# Copy Nginx configuration
RUN cp /var/www/nginx.conf /etc/nginx/sites-available/default

# Create supervisor configuration for running both nginx and php-fpm
RUN echo "[supervisord]\n\
nodaemon=true\n\
user=root\n\
\n\
[program:php-fpm]\n\
command=/usr/local/sbin/php-fpm --nodaemonize\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/storage/logs/php-fpm.log\n\
stderr_logfile=/var/www/storage/logs/php-fpm-error.log\n\
\n\
[program:nginx]\n\
command=/usr/sbin/nginx -g 'daemon off;'\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/storage/logs/nginx.log\n\
stderr_logfile=/var/www/storage/logs/nginx-error.log\n\
" > /etc/supervisor/conf.d/supervisord.conf

# Expose port 80
EXPOSE 80

# Make entrypoint executable
RUN chmod +x /var/www/docker-entrypoint.sh

# Run entrypoint
ENTRYPOINT ["/var/www/docker-entrypoint.sh"]


# Scraper Admin Panel

Laravel 12 + Livewire 3 admin panel for managing the Facebook Scraper application.

## Features

- **Dashboard**: View statistics and recent messages, manually trigger scraper scripts
- **Image Gallery**: Browse, search, download, and delete generated images
- **Settings**: Configure cron intervals, Facebook/Twitter accounts, and proxy settings

## Installation

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & NPM
- SQLite (already set up with scraper)

### Setup

1. Install PHP dependencies:
```bash
cd /var/www/scrapper-alexis-web
composer install
```

2. Install Node dependencies and build assets:
```bash
npm install
npm run build
```

3. Ensure proper permissions:
```bash
# Give www-data (or your web server user) permission to access the scraper database
chmod 664 /var/www/scrapper-alexis/data/scraper.db
chmod 775 /var/www/scrapper-alexis/data
chmod 775 /var/www/scrapper-alexis/data/message_images

# Laravel storage permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

4. Database is already configured to use the scraper's SQLite database at:
   `/var/www/scrapper-alexis/data/scraper.db`

5. Admin user is already created:
   - Email: `admin@scraper.local`
   - Password: `password`

## Web Server Configuration

### Nginx

```nginx
server {
    listen 80;
    server_name scraper-admin.local;
    root /var/www/scrapper-alexis-web/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName scraper-admin.local
    DocumentRoot /var/www/scrapper-alexis-web/public

    <Directory /var/www/scrapper-alexis-web/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/scraper-admin-error.log
    CustomLog ${APACHE_LOG_DIR}/scraper-admin-access.log combined
</VirtualHost>
```

## Development Server

For testing, you can use Laravel's built-in server:

```bash
cd /var/www/scrapper-alexis-web
php artisan serve --host=0.0.0.0 --port=8000
```

Access at: `http://localhost:8000`

## Usage

### Dashboard
- View real-time statistics (total messages, posted tweets, generated images, active profiles)
- See the 10 most recent scraped messages
- Manually trigger any of the three main scripts (Facebook scraper, Twitter poster, Image generator)

### Image Gallery
- Browse all generated images in a responsive grid
- Search images by message text
- Select individual or multiple images
- Download selected images as a ZIP file
- Delete images (removes file and updates database)
- Click any image to view full size with message details and Twitter link

### Settings
- **Cron Schedule**: Set intervals for Facebook scraper (hours) and Twitter poster (minutes)
- **Facebook Account**: Configure email, password, and profile URLs to scrape
- **Twitter Account**: Configure email and password for posting
- **Proxy Settings**: Configure proxy server for Twitter access

All settings are saved to `/var/www/scrapper-alexis/copy.env` and the crontab is automatically updated.

## Security Notes

- Change the default admin password after first login
- Ensure the scraper's `copy.env` file has proper permissions (not world-readable)
- Consider setting up HTTPS for production use
- The admin panel should only be accessible from trusted networks

## Troubleshooting

### "Permission denied" errors
Ensure web server user has read/write access to:
- `/var/www/scrapper-alexis/data/scraper.db`
- `/var/www/scrapper-alexis/data/message_images/`
- `/var/www/scrapper-alexis/copy.env`
- `/var/www/scrapper-alexis-web/storage/`

### Cron updates not working
The web server user needs permission to run `crontab`. You may need to:
```bash
# Add www-data to crontab users (if restricted)
echo "www-data" >> /etc/cron.allow
```

### Images not displaying
Check that image paths in the database match actual file locations:
```bash
ls -la /var/www/scrapper-alexis/data/message_images/
```

### Scripts not running
Ensure bash scripts are executable:
```bash
chmod +x /var/www/scrapper-alexis/run_*.sh
```

## File Structure

```
/var/www/scrapper-alexis-web/
├── app/
│   ├── Http/Controllers/
│   │   └── AuthController.php
│   ├── Livewire/
│   │   ├── Dashboard.php
│   │   ├── ImageGallery.php
│   │   └── Settings.php
│   ├── Models/
│   │   ├── Message.php
│   │   ├── Profile.php
│   │   └── ScrapingSession.php
│   └── helpers.php
├── resources/views/
│   ├── auth/
│   │   └── login.blade.php
│   ├── layouts/
│   │   └── app.blade.php
│   └── livewire/
│       ├── dashboard.blade.php
│       ├── image-gallery.blade.php
│       └── settings.blade.php
└── routes/
    └── web.php
```

## License

Private project - All rights reserved

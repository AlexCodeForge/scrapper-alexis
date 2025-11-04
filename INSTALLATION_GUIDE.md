# Installation Guide - Alexis Scraper Project

Complete guide to install and configure the Alexis Scraper project on a fresh VPS from Git.

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Initial Server Setup](#initial-server-setup)
3. [Install Dependencies](#install-dependencies)
4. [Clone Project from Git](#clone-project-from-git)
5. [Configure Nginx](#configure-nginx)
6. [Configure PHP-FPM](#configure-php-fpm)
7. [Laravel Application Setup](#laravel-application-setup)
8. [Python Scraper Setup](#python-scraper-setup)
9. [File Permissions (CRITICAL)](#file-permissions-critical)
10. [Database Setup](#database-setup)
11. [Verify Installation](#verify-installation)
12. [Troubleshooting](#troubleshooting)

---

## System Requirements

- **OS**: Debian 11+ / Ubuntu 20.04+
- **Web Server**: Nginx
- **PHP**: 8.3+
- **Database**: SQLite 3
- **Python**: 3.10+
- **Node.js**: 18+ (for Laravel Mix/Vite)
- **Memory**: Minimum 2GB RAM (4GB recommended)
- **Disk Space**: Minimum 10GB

---

## Initial Server Setup

### 1. Update System Packages

```bash
apt update && apt upgrade -y
```

### 2. Create Application Directory

```bash
mkdir -p /var/www/alexis-scrapper-docker
cd /var/www/alexis-scrapper-docker
```

---

## Install Dependencies

### 1. Install Nginx

```bash
apt install nginx -y
systemctl enable nginx
systemctl start nginx
```

### 2. Install PHP 8.3 + Extensions

```bash
# Add PHP repository if needed
apt install software-properties-common -y
add-apt-repository ppa:ondrej/php -y  # Ubuntu
# For Debian: https://packages.sury.org/php/

apt update
apt install php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-sqlite3 \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-intl -y

systemctl enable php8.3-fpm
systemctl start php8.3-fpm
```

### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### 4. Install Node.js & NPM

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install nodejs -y
node --version
npm --version
```

### 5. Install Python 3 + Dependencies

```bash
apt install python3 python3-pip python3-venv sqlite3 -y
python3 --version
```

### 6. Install Git

```bash
apt install git -y
```

### 7. Install System Dependencies for Playwright

```bash
# Required for Firefox/Chromium browsers
apt install libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 \
    libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 \
    libxfixes3 libxrandr2 libgbm1 libasound2 libpango-1.0-0 \
    libcairo2 libatspi2.0-0 -y
```

---

## Clone Project from Git

### 1. Clone Repository

```bash
cd /var/www/alexis-scrapper-docker
git clone <YOUR_GIT_REPOSITORY_URL> .

# Or if pulling updates:
git pull origin main
```

### 2. Verify Directory Structure

```bash
ls -la
# Should see:
# - scrapper-alexis-web/  (Laravel app)
# - scrapper-alexis/      (Python scraper)
```

---

## Configure Nginx

### 1. Create Nginx Site Configuration

```bash
nano /etc/nginx/sites-available/alexis-scraper
```

**Paste the following configuration:**

```nginx
server {
    listen 8006;
    server_name YOUR_SERVER_IP;  # Replace with your IP or domain
    root /var/www/alexis-scrapper-docker/scrapper-alexis-web/public;

    index index.php index.html;

    # Logging
    access_log /var/log/nginx/alexis-scraper-access.log;
    error_log /var/log/nginx/alexis-scraper-error.log;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 2. Enable Site & Test Configuration

```bash
# Create symlink
ln -s /etc/nginx/sites-available/alexis-scraper /etc/nginx/sites-enabled/

# Test configuration
nginx -t

# Reload Nginx
systemctl reload nginx
```

---

## Configure PHP-FPM

### 1. Adjust PHP-FPM Pool Settings

```bash
nano /etc/php/8.3/fpm/pool.d/www.conf
```

**Ensure these settings:**

```ini
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

### 2. Restart PHP-FPM

```bash
systemctl restart php8.3-fpm
```

---

## Laravel Application Setup

### 1. Navigate to Laravel Directory

```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web
```

### 2. Install PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Copy & Configure Environment File

```bash
cp .env.example .env
nano .env
```

**Configure the following:**

```env
APP_NAME="Scraper Admin"
APP_ENV=production
APP_KEY=  # Will be generated in next step
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP:8006

LOG_CHANNEL=stack
LOG_LEVEL=INFO

# SQLite Database
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# File Storage
FILESYSTEM_DISK=local
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Create SQLite Database

```bash
touch database/database.sqlite
```

### 6. Run Migrations

```bash
php artisan migrate --force
```

### 7. Create Admin User (Optional)

```bash
php artisan tinker
```

```php
// In tinker console:
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@scraper.local',
    'password' => bcrypt('password')
]);
exit
```

### 8. Install Frontend Assets (If Applicable)

```bash
npm install
npm run build  # or npm run production
```

---

## Python Scraper Setup

### 1. Navigate to Scraper Directory

```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis
```

### 2. Create Python Virtual Environment

```bash
python3 -m venv venv
source venv/bin/activate
```

### 3. Install Python Dependencies

```bash
pip install --upgrade pip
pip install -r requirements.txt
```

### 4. Install Playwright Browsers (AS ROOT)

**CRITICAL: Playwright browsers MUST be installed as root for proper operation**

```bash
# While still in venv:
playwright install firefox chromium

# Install system dependencies for Playwright
playwright install-deps
```

### 5. Configure Environment File

```bash
nano .env
```

**Configure the following:**

```env
# Database Path (CRITICAL - must point to Laravel's SQLite database)
DATABASE_PATH=/var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite

# Logging
LOG_LEVEL=INFO

# Browser Settings
HEADLESS=true
SLOW_MO=0

# Scraping Settings (will be overridden by database)
FACEBOOK_EMAIL=
FACEBOOK_PASSWORD=
TWITTER_USERNAME=
TWITTER_PASSWORD=
```

### 6. Deactivate Virtual Environment

```bash
deactivate
```

---

## File Permissions (CRITICAL)

**⚠️ THESE PERMISSIONS ARE ESSENTIAL - MOST ERRORS COME FROM INCORRECT OWNERSHIP**

### 1. Set Ownership for Laravel Application

```bash
# Set www-data as owner for the Laravel app
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis-web

# Ensure storage and bootstrap/cache are writable
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis-web/bootstrap/cache
```

### 2. Set Database Permissions (CRITICAL)

```bash
# Database directory must be owned by www-data and writable
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis-web/database
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis-web/database

# Specific database file
chown www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
chmod 664 /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
```

### 3. Set Python Scraper Permissions

```bash
# Set www-data as owner for scraper directories
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis

# Ensure logs directory is writable
mkdir -p /var/www/alexis-scrapper-docker/scrapper-alexis/logs
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis/logs
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/logs

# Ensure debug_output directory is writable
mkdir -p /var/www/alexis-scrapper-docker/scrapper-alexis/debug_output
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis/debug_output
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/debug_output

# Ensure auth directory is writable
mkdir -p /var/www/alexis-scrapper-docker/scrapper-alexis/auth
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis/auth
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/auth
```

### 4. Verify Permissions

```bash
# Check database directory
ls -la /var/www/alexis-scrapper-docker/scrapper-alexis-web/database
# Should show: drwxrwxr-x www-data www-data

# Check database file
ls -la /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
# Should show: -rw-rw-r-- www-data www-data

# Check scraper directories
ls -la /var/www/alexis-scrapper-docker/scrapper-alexis/
# logs, debug_output, auth should all be: drwxrwxr-x www-data www-data
```

---

## Database Setup

### 1. Configure Scraper Settings via Web Interface

Navigate to: `http://YOUR_SERVER_IP:8006/`

Login with the admin credentials you created earlier.

### 2. Configure Required Settings

Go to **Settings** page and configure:

1. **Facebook Account**:
   - Email/Username
   - Password (will be encrypted)
   - Profile URLs to scrape
   - Upload authentication file if available

2. **Twitter Account**:
   - Username
   - Password (will be encrypted)

3. **Proxy Configuration** (MANDATORY):
   - Proxy Host
   - Proxy Port
   - Proxy Username (if required)
   - Proxy Password (if required)
   - Enable proxy

4. **Cron Schedule** (Optional):
   - Enable/Disable Facebook scraper
   - Enable/Disable Twitter poster
   - Enable/Disable Page poster
   - Set interval ranges

---

## Verify Installation

### 1. Check Web Interface

```bash
# Visit in browser:
http://YOUR_SERVER_IP:8006/
```

You should see the login page.

### 2. Test Manual Execution

From the dashboard:
1. Click "Ejecutar Scraper Facebook" button
2. Go to **Logs** page
3. Click "Manual Runs" tab
4. Check the latest log file for execution status

### 3. Check Logs for Errors

```bash
# Laravel logs
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/alexis-scraper-error.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log

# Scraper logs
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis/logs/*.log
```

---

## Troubleshooting

### Error: "SQLSTATE[HY000]: General error: 8 attempt to write a readonly database"

**Cause**: Database directory or file not owned by `www-data` or not writable.

**Fix**:
```bash
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis-web/database
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis-web/database
```

---

### Error: "PermissionError: [Errno 13] Permission denied: 'debug_output/...'"

**Cause**: Scraper directories not owned by `www-data`.

**Fix**:
```bash
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/debug_output
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/logs
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/auth
```

---

### Error: "BrowserType.launch: Executable doesn't exist at ...ms-playwright/firefox..."

**Cause**: Playwright browsers not installed for the correct user.

**Fix**:
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis
source venv/bin/activate
playwright install firefox chromium
playwright install-deps
deactivate
```

---

### Error: "BrowserType.launch: Timeout 180000ms exceeded. DBus/X server errors"

**Cause**: Firefox accessibility issues in headless server environment.

**Fix**: The application is configured to run as root via `sudo -u root` in the Laravel Artisan commands. This should be automatic. If issues persist:

```bash
# Check Laravel command execution in logs
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log
```

---

### Error: "No such table: scraper_settings" or "No such table: posting_settings"

**Cause**: Migrations not run or database corrupted.

**Fix**:
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web
php artisan migrate:fresh --force
```

**⚠️ WARNING**: `migrate:fresh` will drop all tables and data. Use `migrate` instead if you want to preserve data.

---

### Error: "Illuminate\Contracts\Encryption\DecryptException: The payload is invalid"

**Cause**: Encrypted passwords stored before `APP_KEY` was set or `APP_KEY` changed.

**Fix**: Re-enter passwords via the web interface Settings page. The app will re-encrypt them with the current `APP_KEY`.

---

### Error: "Class 'SQLite3' not found"

**Cause**: PHP SQLite extension not installed.

**Fix**:
```bash
apt install php8.3-sqlite3 -y
systemctl restart php8.3-fpm
```

---

### Error: 500 Internal Server Error (Nginx)

**Cause**: Multiple possible causes.

**Debug Steps**:

1. Check Nginx error logs:
```bash
tail -f /var/log/nginx/alexis-scraper-error.log
```

2. Check Laravel logs:
```bash
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log
```

3. Check PHP-FPM logs:
```bash
tail -f /var/log/php8.3-fpm.log
```

4. Verify file permissions:
```bash
ls -la /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage
ls -la /var/www/alexis-scrapper-docker/scrapper-alexis-web/bootstrap/cache
```

---

### Manual Logs Not Showing at /logs Page

**Cause**: Log directory path mismatch or permissions.

**Fix**:
```bash
# Ensure logs directory exists and is writable
mkdir -p /var/www/alexis-scrapper-docker/scrapper-alexis/logs
chown -R www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis/logs
chmod -R 775 /var/www/alexis-scrapper-docker/scrapper-alexis/logs
```

---

### Proxy Not Working / Connection Refused

**Cause**: Proxy not configured in database or invalid credentials.

**Fix**:
1. Go to Settings page
2. Open "Configuración Proxy" section
3. Enter correct proxy details
4. Ensure "Enable" is toggled ON
5. Save settings

**Verify proxy in database**:
```bash
sqlite3 /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
SELECT proxy_enabled, proxy_host, proxy_port FROM scraper_settings;
.exit
```

---

## Security Recommendations

### 1. Firewall Configuration

```bash
# Install UFW
apt install ufw -y

# Allow SSH (CRITICAL - don't lock yourself out!)
ufw allow 22/tcp

# Allow port 8006 (application)
ufw allow 8006/tcp

# Enable firewall
ufw enable
```

### 2. Change Default Credentials

**IMPORTANT**: Change the default admin credentials immediately:

1. Login to web interface
2. Go to user management (if available)
3. Change password

Or via tinker:
```bash
php artisan tinker
```
```php
$user = \App\Models\User::where('email', 'admin@scraper.local')->first();
$user->password = bcrypt('YOUR_STRONG_PASSWORD');
$user->save();
exit
```

### 3. Disable Directory Listing

Already configured in Nginx. Verify:
```bash
# Should deny access to hidden files
curl -I http://YOUR_SERVER_IP:8006/.env
# Should return 403 Forbidden
```

### 4. Regular Updates

```bash
# Update system packages
apt update && apt upgrade -y

# Update Composer dependencies
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web
composer update

# Update npm packages
npm update

# Update Python packages
cd /var/www/alexis-scrapper-docker/scrapper-alexis
source venv/bin/activate
pip install --upgrade -r requirements.txt
deactivate
```

---

## Backup & Recovery

### Backup Database

```bash
# Create backup directory
mkdir -p /var/backups/alexis-scraper

# Backup SQLite database
cp /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite \
   /var/backups/alexis-scraper/database_$(date +%Y%m%d_%H%M%S).sqlite

# Backup auth files
tar -czf /var/backups/alexis-scraper/auth_$(date +%Y%m%d_%H%M%S).tar.gz \
   /var/www/alexis-scrapper-docker/scrapper-alexis/auth/
```

### Restore Database

```bash
# Stop services
systemctl stop php8.3-fpm
systemctl stop nginx

# Restore database
cp /var/backups/alexis-scraper/database_YYYYMMDD_HHMMSS.sqlite \
   /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite

# Fix permissions
chown www-data:www-data /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
chmod 664 /var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite

# Restart services
systemctl start php8.3-fpm
systemctl start nginx
```

---

## Additional Notes

### Browser Selection

The scraper is configured to use **Firefox ONLY** as per requirements. Chromium is installed as a fallback but not actively used.

### Proxy Requirements

**PROXIES ARE MANDATORY**. The scraper will not function without a properly configured proxy. Ensure you have:
- Valid proxy host and port
- Proxy credentials (if required)
- Proxy properly enabled in settings

### Running as Root

The Laravel Artisan commands are configured to execute Python scripts using `sudo -u root` to bypass DBus and accessibility issues. This is intentional and necessary for Firefox to work in the server environment.

### Cron Scheduling

Cron scheduling is **database-driven**. Enable/disable crons via the web interface, not crontab. The application manages its own scheduling internally.

---

## Post-Installation Checklist

- [ ] Web interface accessible at `http://YOUR_SERVER_IP:8006/`
- [ ] Admin login working
- [ ] Settings page loads without errors
- [ ] Logs page loads without errors
- [ ] Facebook credentials configured
- [ ] Twitter credentials configured (if applicable)
- [ ] **Proxy configured and enabled**
- [ ] Manual execution test successful
- [ ] Database file owned by `www-data`
- [ ] All scraper directories (`logs`, `auth`, `debug_output`) writable
- [ ] Playwright browsers installed
- [ ] Firewall configured
- [ ] Default credentials changed
- [ ] Backup strategy implemented

---

## Support & Maintenance

### Log Locations

- **Laravel Application**: `/var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log`
- **Python Scraper**: `/var/www/alexis-scrapper-docker/scrapper-alexis/logs/`
- **Nginx Access**: `/var/log/nginx/alexis-scraper-access.log`
- **Nginx Error**: `/var/log/nginx/alexis-scraper-error.log`
- **PHP-FPM**: `/var/log/php8.3-fpm.log`

### Service Management

```bash
# Restart all services
systemctl restart nginx
systemctl restart php8.3-fpm

# Check service status
systemctl status nginx
systemctl status php8.3-fpm

# View service logs
journalctl -u nginx -f
journalctl -u php8.3-fpm -f
```

---

**Last Updated**: November 3, 2025
**Version**: 1.0.0

For additional support, refer to Laravel documentation (https://laravel.com/docs) and Playwright Python documentation (https://playwright.dev/python/docs/intro).




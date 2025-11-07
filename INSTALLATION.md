# Alexis Scraper - Installation Guide

Complete guide for installing on a new VPS via Git.

---

## 📋 Requirements

- Ubuntu 20.04+ or Debian 11+
- PHP 8.2+, Python 3.9+, Composer, Node.js 18+, Nginx
- 2GB RAM minimum, 5GB disk space
- **System timezone MUST be set to Mexico (America/Mexico_City)**

---

## 🚀 Quick Installation

```bash
# 1. Clone repository
cd /var/www
sudo git clone <YOUR_GIT_REPO_URL> alexis-scrapper-docker
cd alexis-scrapper-docker

# 2. Run installer
sudo ./install.sh

# 3. Access web interface
# http://your-server-ip
# Login: admin@scraper.local / password
# ⚠️ CHANGE PASSWORD IMMEDIATELY!
```

---

## 🔐 Critical: File Permissions & Deletion

**Why this matters:** The web app needs to DELETE images/logs created by the Python scraper.

### The Rule
- **ALL processes MUST run as `www-data` user**
- This includes: cron jobs, web app, Python scripts
- Files created by `www-data` can be deleted by `www-data`

### Cron Setup (CRITICAL)
```bash
# Cron MUST run as www-data (NOT root!)
sudo crontab -e -u www-data

# Add this line:
* * * * * cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1
```

### Fix Permissions Anytime
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web
sudo ./setup.sh
```

This fixes:
- All directory permissions (775)
- All file ownership (www-data:www-data)
- **Existing images/logs ownership** (so web app can delete them)

### Permission Reference

| Path | Permission | Owner | Purpose |
|------|------------|-------|---------|
| `scrapper-alexis-web/storage/` | 775 | www-data:www-data | Laravel logs/cache |
| `scrapper-alexis-web/database/database.sqlite` | 664 | www-data:www-data | Database |
| `scrapper-alexis/data/message_images/` | 775 | www-data:www-data | Generated images |
| **Images inside (*.png, *.jpg)** | **664** | **www-data:www-data** | **For deletion!** |
| `scrapper-alexis/logs/` | 775 | www-data:www-data | Scraper logs |
| **Log files (*.log)** | **664** | **www-data:www-data** | **For deletion!** |
| `scrapper-alexis/run_*.sh` | 755 | www-data:www-data | Shell scripts |

---

## 🐛 Common Issues

### Issue: Cannot delete images from web interface

**Symptom:** "Permission denied" when deleting images

**Cause:** Files have wrong owner (not www-data)

**Fix:**
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis

# Check ownership
ls -la data/message_images/ | head -5

# Fix it
sudo chown -R www-data:www-data data/message_images/
sudo find data/message_images -type f -exec chmod 664 {} \;

# Verify (should show www-data:www-data)
ls -la data/message_images/ | head -5
```

**Prevent:** Make sure cron runs as www-data (not root!)

### Issue: Cron jobs not running

**Fix:**
```bash
# Check cron is configured for www-data
sudo crontab -l -u www-data

# If empty, add it:
sudo crontab -e -u www-data
# Add: * * * * * cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1

# Test manually
sudo -u www-data bash -c 'cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run'
```

### Issue: 500 error on web interface

**Fix:**
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web

# Check logs
tail -50 storage/logs/laravel.log

# Fix permissions
sudo ./setup.sh

# Clear cache
php artisan cache:clear
php artisan config:clear

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

### Issue: Database locked errors

**Fix:**
```bash
cd /var/www/alexis-scrapper-docker/scrapper-alexis-web

# Fix database permissions
sudo chmod 664 database/database.sqlite
sudo chmod 775 database/
sudo chown www-data:www-data database/database.sqlite

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

---

## 📝 Manual Installation Steps

If you can't use `install.sh`, follow these steps:

### 1. Install System Packages & Set Timezone
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl wget unzip software-properties-common sqlite3 xvfb

# Set timezone to Mexico (REQUIRED - must match application)
sudo timedatectl set-timezone America/Mexico_City

# Verify timezone
timedatectl
# Should show: Time zone: America/Mexico_City (CST, -0600)
```

### 2. Install PHP 8.2
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-sqlite3 \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath
```

### 3. Install Python 3.9+
```bash
sudo apt install -y python3 python3-pip python3-venv xvfb
```

### 4. Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 5. Install Node.js 18
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 6. Install Nginx
```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 7. Clone Repository
```bash
cd /var/www
sudo git clone <YOUR_GIT_REPO_URL> alexis-scrapper-docker
cd alexis-scrapper-docker
```

### 8. Install Python Dependencies
```bash
cd scrapper-alexis
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
playwright install firefox
playwright install-deps firefox
deactivate
```

### 9. Install PHP Dependencies
```bash
cd ../scrapper-alexis-web
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 10. Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite
```

### 11. Initialize Database
```bash
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
```

### 12. Create Required Directories
```bash
cd ../scrapper-alexis
mkdir -p data/message_images data/auth_states logs debug_output
```

### 13. Set Permissions
```bash
cd ../scrapper-alexis-web
sudo ./setup.sh
```

### 14. Configure Nginx
```bash
sudo cp nginx.conf /etc/nginx/sites-available/scraper-admin
sudo ln -s /etc/nginx/sites-available/scraper-admin /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 15. Configure Cron (as www-data!)
```bash
sudo crontab -e -u www-data
# Add: * * * * * cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1
```

### 16. Start Services
```bash
sudo systemctl restart php8.2-fpm nginx
```

---

## ✅ Verification

```bash
# Check timezone (MUST be Mexico)
timedatectl
# Should show: Time zone: America/Mexico_City (CST, -0600)

# Check web interface
curl -I http://localhost  # Should return 200 OK

# Check cron
sudo crontab -l -u www-data

# Check permissions
cd /var/www/alexis-scrapper-docker/scrapper-alexis
ls -la data/message_images/  # Should show www-data:www-data

# Test scheduler
cd ../scrapper-alexis-web
php artisan schedule:run

# Check logs
tail -20 storage/logs/laravel.log
```

---

## 🔒 Security Checklist

- [ ] **System timezone set to Mexico (America/Mexico_City)**
- [ ] Changed default admin password
- [ ] Set `APP_DEBUG=false` in .env
- [ ] Set `APP_ENV=production` in .env
- [ ] Cron configured for www-data user
- [ ] Firewall configured (ports 22, 80, 443)
- [ ] SSL certificate installed (production)
- [ ] All files owned by www-data
- [ ] Tested image deletion from web interface

---

## 📞 Useful Commands

```bash
# Restart services
sudo systemctl restart nginx php8.2-fpm

# View logs
tail -f scrapper-alexis-web/storage/logs/laravel.log
tail -f scrapper-alexis/logs/manual_run.log

# Fix permissions
cd scrapper-alexis-web && sudo ./setup.sh

# Test scheduler
cd scrapper-alexis-web && php artisan schedule:run

# Check disk space
df -h /var/www
du -sh scrapper-alexis/logs
du -sh scrapper-alexis/data/message_images

# Clear Laravel cache
cd scrapper-alexis-web
php artisan cache:clear
php artisan config:clear

# Check database
cd scrapper-alexis-web
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM messages;"
```

---

## 📚 What's NOT Committed to Git

The repository excludes:
- ❌ Database files (*.db, *.sqlite)
- ❌ Log files (*.log)
- ❌ Generated images (*.png, *.jpg)
- ❌ Debug output
- ❌ Browser auth sessions
- ❌ Environment files (.env)
- ❌ Dependencies (venv/, node_modules/, vendor/)

Fresh installations start with:
- ✅ Clean database
- ✅ Only admin user (admin@scraper.local / password)
- ✅ No old messages or images
- ✅ All permissions set correctly

---

## 🎯 Post-Installation

1. Access: `http://your-server-ip`
2. Login: `admin@scraper.local` / `password`
3. **Change password immediately!**
4. Go to Settings → Configure:
   - Facebook credentials
   - Twitter credentials
   - Facebook profile URLs
   - Scraping intervals
5. Test: Dashboard → "Run Facebook Scraper Now"
6. Check logs: `tail -f scrapper-alexis/logs/manual_run.log`

---

**Version:** 1.0  
**Last Updated:** November 2025


# Deployment Checklist

## ✅ Pre-Deployment

- [x] Laravel 12 installed
- [x] Livewire 3 installed
- [x] Database configured (SQLite)
- [x] Models created (Profile, Message, ScrapingSession)
- [x] Helper functions implemented
- [x] Admin user created
- [x] Frontend assets compiled
- [x] Permissions configured

## 🚀 Development Server

```bash
cd /var/www/scrapper-alexis-web
php artisan serve --host=0.0.0.0 --port=8000
```

Access at: **http://localhost:8000**

## 🌐 Production Deployment

### Step 1: Configure Web Server

#### Option A: Nginx

```bash
# Copy configuration
sudo cp /var/www/scrapper-alexis-web/nginx.conf /etc/nginx/sites-available/scraper-admin

# Enable site
sudo ln -s /etc/nginx/sites-available/scraper-admin /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

#### Option B: Apache

```bash
# Enable mod_rewrite
sudo a2enmod rewrite

# Create virtual host config
sudo nano /etc/apache2/sites-available/scraper-admin.conf
```

Paste:
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

```bash
# Enable site
sudo a2ensite scraper-admin

# Reload Apache
sudo systemctl reload apache2
```

### Step 2: Set Permissions

```bash
cd /var/www/scrapper-alexis-web
./setup.sh
```

### Step 3: Optimize for Production

```bash
cd /var/www/scrapper-alexis-web

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### Step 4: Security Hardening

1. **Change default password:**
   - Login to admin panel
   - Create a new strong password

2. **Update .env file:**
   ```bash
   nano /var/www/scrapper-alexis-web/.env
   ```
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Generate new `APP_KEY` if needed: `php artisan key:generate`

3. **Set proper file permissions:**
   ```bash
   chmod 644 /var/www/scrapper-alexis-web/.env
   chown root:www-data /var/www/scrapper-alexis-web/.env
   ```

4. **Restrict admin panel access** (optional):
   Add to Nginx config:
   ```nginx
   # Allow only specific IPs
   allow 192.168.1.0/24;
   deny all;
   ```

### Step 5: Enable HTTPS (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d scraper-admin.yourdomain.com
```

### Step 6: Test Production Deployment

- [ ] Can access login page
- [ ] Can login with credentials
- [ ] Dashboard loads with correct stats
- [ ] Image gallery displays images
- [ ] Settings page loads
- [ ] Can save settings successfully
- [ ] Manual script triggers work
- [ ] Cron updates work

## 🔧 Post-Deployment

### Monitor Logs

```bash
# Laravel logs
tail -f /var/www/scrapper-alexis-web/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log

# Apache logs
tail -f /var/log/apache2/error.log

# Scraper logs
tail -f /var/www/scrapper-alexis/logs/cron_facebook.log
tail -f /var/www/scrapper-alexis/logs/cron_twitter.log
```

### Verify Cron Jobs

```bash
# Check current crontab
crontab -l

# Should show updated schedule from admin panel
```

### Database Backup

```bash
# Backup SQLite database
cp /var/www/scrapper-alexis/data/scraper.db /var/www/scrapper-alexis/data/scraper.db.backup

# Automated daily backup (add to crontab)
0 2 * * * cp /var/www/scrapper-alexis/data/scraper.db /var/www/scrapper-alexis/data/backups/scraper_$(date +\%Y\%m\%d).db
```

## 🎯 Final Verification

### Functional Tests

- [ ] Login/Logout works
- [ ] Dashboard displays correct statistics
- [ ] Recent messages list populates
- [ ] Manual script triggers execute successfully
- [ ] Image gallery shows all images
- [ ] Search functionality works
- [ ] Image download works
- [ ] Bulk download creates ZIP
- [ ] Image deletion works
- [ ] Settings form saves successfully
- [ ] Cron intervals update correctly
- [ ] Environment file updates correctly

### Performance Tests

- [ ] Page load times < 2 seconds
- [ ] Image gallery pagination works smoothly
- [ ] No console errors in browser
- [ ] Mobile responsive design works
- [ ] All buttons and links functional

### Security Tests

- [ ] Cannot access admin pages without login
- [ ] Session persists correctly
- [ ] Logout clears session
- [ ] CSRF protection active
- [ ] Database file not accessible via web
- [ ] .env file not accessible via web

## 🆘 Rollback Plan

If something goes wrong:

1. **Stop web server:**
   ```bash
   sudo systemctl stop nginx  # or apache2
   ```

2. **Restore database backup:**
   ```bash
   cp /var/www/scrapper-alexis/data/scraper.db.backup /var/www/scrapper-alexis/data/scraper.db
   ```

3. **Disable site:**
   ```bash
   sudo rm /etc/nginx/sites-enabled/scraper-admin  # or a2dissite scraper-admin
   ```

4. **Check logs for errors:**
   ```bash
   tail -100 /var/www/scrapper-alexis-web/storage/logs/laravel.log
   ```

## 📝 Maintenance

### Weekly

- [ ] Check logs for errors
- [ ] Verify cron jobs are running
- [ ] Review disk space usage

### Monthly

- [ ] Update dependencies: `composer update`
- [ ] Rebuild assets: `npm run build`
- [ ] Clear old logs
- [ ] Database backup

### As Needed

- [ ] Add new admin users (if required)
- [ ] Update credentials
- [ ] Adjust cron schedules
- [ ] Clean up old images

---

## ✨ Deployment Complete!

Your Scraper Admin Panel is now ready for production use.

**Support:** Check README.md, QUICKSTART.md, and IMPLEMENTATION_SUMMARY.md for detailed documentation.







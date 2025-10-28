# Quick Start Guide

## 🚀 Start the Development Server

```bash
cd /var/www/scrapper-alexis-web
php artisan serve --host=0.0.0.0 --port=8000
```

**Access the admin panel at:** http://localhost:8000

## 🔐 Login Credentials

- **Email:** `admin@scraper.local`
- **Password:** `password`

⚠️ **Important:** Change the default password after first login!

## 📱 Features Overview

### 1. Dashboard (`/dashboard`)
- View statistics (total messages, posted tweets, images, profiles)
- See recent 10 messages with status badges
- Manually trigger scripts:
  - **Run Facebook Scraper Now** - Scrapes Facebook profiles
  - **Run Twitter Poster Now** - Posts next message to Twitter
  - **Run Image Generator Now** - Generates images for posted tweets

### 2. Image Gallery (`/images`)
- Browse all generated tweet images in a grid
- Search by message text
- Select individual or multiple images
- **Download** selected images as ZIP
- **Delete** images (updates database)
- Click image to view full size with details

### 3. Settings (`/settings`)
- **Cron Intervals:**
  - Facebook scraper interval (1-24 hours)
  - Twitter poster interval (1-60 minutes)
- **Facebook Account:**
  - Email & Password
  - Profile URLs (one per line)
- **Twitter Account:**
  - Email & Password
- **Proxy Settings:**
  - Server, Username, Password

## 🔧 Production Deployment (Nginx)

1. **Copy Nginx configuration:**
```bash
sudo cp /var/www/scrapper-alexis-web/nginx.conf /etc/nginx/sites-available/scraper-admin
sudo ln -s /etc/nginx/sites-available/scraper-admin /etc/nginx/sites-enabled/
```

2. **Test and reload Nginx:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

3. **Update your hosts file** (for local development):
```bash
echo "127.0.0.1 scraper-admin.local" | sudo tee -a /etc/hosts
```

4. **Access at:** http://scraper-admin.local

## 📂 Key Directories

| Directory | Purpose |
|-----------|---------|
| `/var/www/scrapper-alexis-web/` | Laravel application |
| `/var/www/scrapper-alexis/data/scraper.db` | Shared SQLite database |
| `/var/www/scrapper-alexis/data/message_images/` | Generated tweet images |
| `/var/www/scrapper-alexis/copy.env` | Scraper configuration |
| `/var/www/scrapper-alexis/logs/` | Scraper logs |

## 🛠️ Common Commands

### Rebuild Frontend Assets
```bash
cd /var/www/scrapper-alexis-web
npm run build
```

### Clear Laravel Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Re-run Setup (Permissions)
```bash
cd /var/www/scrapper-alexis-web
./setup.sh
```

### View Scraper Logs
```bash
tail -f /var/www/scrapper-alexis/logs/cron_facebook.log
tail -f /var/www/scrapper-alexis/logs/cron_twitter.log
tail -f /var/www/scrapper-alexis/logs/manual_run.log
```

### Check Database
```bash
sqlite3 /var/www/scrapper-alexis/data/scraper.db "SELECT COUNT(*) FROM messages;"
sqlite3 /var/www/scrapper-alexis/data/scraper.db "SELECT COUNT(*) FROM messages WHERE image_generated = 1;"
```

## 🐛 Troubleshooting

### Issue: "Permission denied" on database
```bash
chmod 664 /var/www/scrapper-alexis/data/scraper.db
chown www-data:www-data /var/www/scrapper-alexis/data/scraper.db
```

### Issue: Images not loading
```bash
chmod 775 /var/www/scrapper-alexis/data/message_images
chown -R www-data:www-data /var/www/scrapper-alexis/data/message_images
```

### Issue: Cron updates failing
```bash
# Add www-data to crontab allowed users
echo "www-data" | sudo tee -a /etc/cron.allow
```

### Issue: Scripts not running
```bash
chmod +x /var/www/scrapper-alexis/run_*.sh
```

## 📞 Support

For issues, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Scraper logs: `/var/www/scrapper-alexis/logs/`
3. Nginx error logs: `/var/log/nginx/error.log`

## 🎯 Next Steps

1. ✅ Login to the admin panel
2. ✅ Change the default password
3. ✅ Go to Settings and configure your accounts
4. ✅ Test manual script execution from Dashboard
5. ✅ Browse the Image Gallery
6. ✅ Set up production web server (Nginx/Apache)
7. ✅ Schedule automatic execution via cron (configured in Settings)

---

**Enjoy your Scraper Admin Panel! 🎉**








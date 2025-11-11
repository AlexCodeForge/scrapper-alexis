# Alexis Scraper - Complete Installation Guide

**One-stop guide for installing and verifying the Alexis Scraper on any VPS.**

---

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Quick Installation](#quick-installation)
3. [Post-Installation Verification](#post-installation-verification)
4. [Troubleshooting Common Issues](#troubleshooting-common-issues)
5. [Configuration](#configuration)
6. [Useful Commands](#useful-commands)

---

## 📋 System Requirements

### Minimum Specifications
- **OS**: Ubuntu 20.04+ or Debian 11+
- **RAM**: 2GB minimum (4GB recommended)
- **Disk**: 5GB free space
- **CPU**: 2 cores minimum

### Required Software
All of these will be installed automatically by the install script:
- PHP 8.2+ with extensions (sqlite3, mbstring, xml, curl, zip, bcmath)
- Python 3.9+ with pip and venv
- Composer (PHP dependency manager)
- Node.js 18+ with npm
- Nginx web server
- SQLite3
- Xvfb (for headless browser)
- Cron service

### Network Requirements
- Ports 80 and 443 open for web access
- Port 22 for SSH access
- Internet connectivity for downloading dependencies

---

## 🚀 Quick Installation

### Step 1: Clone the Repository

```bash
# Navigate to web root directory
cd /var/www

# Clone the repository (replace with your Git URL)
sudo git clone <YOUR_GIT_REPO_URL> alexis-scrapper-docker
cd alexis-scrapper-docker
```

**Note:** You can install to any directory, not just `/var/www/alexis-scrapper-docker`. The install script will automatically detect the installation path.

### Step 2: Run the Installer

```bash
# Make the installer executable
sudo chmod +x install.sh

# Run the installation script
sudo ./install.sh
```

The installer will:
1. Install all system packages and dependencies
2. Set system timezone to Mexico (America/Mexico_City)
3. Install and configure PHP, Python, Node.js, and Nginx
4. Set up Python virtual environment and install packages
5. Install PHP and Node.js dependencies
6. Configure Laravel environment
7. Initialize the database with admin user
8. Set up proper file permissions
9. Configure Nginx with the correct paths
10. Set up cron jobs for automated scraping

**Installation takes approximately 10-15 minutes** depending on your server speed.

### Step 3: Access the Web Interface

After installation completes, you'll see the server IP address. Access the admin panel:

```
http://YOUR_SERVER_IP
```

**Default Login Credentials:**
- Email: `admin@scraper.local`
- Password: `password`

**⚠️ IMPORTANT: Change the default password immediately after first login!**

---

## ✅ Post-Installation Verification

Run these checks immediately after installation to ensure everything is working correctly.

### 1. Check System Timezone

The system MUST be set to Mexico timezone for proper scheduling:

```bash
timedatectl
```

**Expected Output:**
```
Time zone: America/Mexico_City (CST, -0600)
```

**If incorrect:**
```bash
sudo timedatectl set-timezone America/Mexico_City
```

---

### 2. Check Web Interface Access

```bash
# Check Nginx is running
sudo systemctl status nginx

# Test web server response
curl -I http://localhost
```

**Expected:** HTTP 200 OK response

---

### 3. Verify Cron Job Configuration

**CRITICAL**: Cron MUST run as `www-data` user (not root!) for proper file permissions.

```bash
# Check cron is configured for www-data
sudo crontab -l -u www-data
```

**Expected Output:**
```
* * * * * cd /path/to/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1
```

**If missing:**
```bash
sudo crontab -e -u www-data
# Add the line above, replacing /path/to with your actual installation path
```

---

### 4. Verify Database Connection

```bash
# Navigate to Laravel directory
cd scrapper-alexis-web

# Test database connection
php artisan tinker --execute="echo 'Messages: ' . \App\Models\Message::count();"
```

**Expected:** Shows count of messages (0 for fresh install)

---

### 5. Check Python Environment

```bash
# Navigate to Python scraper directory
cd scrapper-alexis

# Activate virtual environment
source venv/bin/activate

# Check cryptography module (CRITICAL for proxy authentication)
python3 -c "from cryptography.hazmat.primitives.ciphers import Cipher; print('✅ Cryptography module OK')"

# Check database path
python3 -c "import config; print('Database:', config.DATABASE_PATH)" 2>&1 | grep database.sqlite

# Deactivate
deactivate
```

**Expected:**
- "✅ Cryptography module OK"
- Database path should point to `scrapper-alexis-web/database/database.sqlite`

---

### 6. Verify File Permissions

```bash
# Check Laravel storage permissions
ls -la scrapper-alexis-web/storage | head -5

# Check database permissions
ls -la scrapper-alexis-web/database/database.sqlite

# Check Python data directory
ls -la scrapper-alexis/data
```

**Expected:** All files and directories should be owned by `www-data:www-data`

---

### 7. Test Proxy Configuration (Optional)

Only run if you've configured proxy settings in the web interface:

```bash
cd scrapper-alexis
source venv/bin/activate

timeout 15 python3 -c "
from playwright.sync_api import sync_playwright
import config
print('Proxy server:', config.PROXY_CONFIG['server'] if config.PROXY_CONFIG else 'NONE')
print('Password length:', len(config.PROXY_PASSWORD) if config.PROXY_PASSWORD else 0)
p = sync_playwright().start()
browser = p.firefox.launch(headless=True, proxy=config.PROXY_CONFIG)
page = browser.new_page()
page.goto('https://www.google.com', timeout=10000)
print('✅ Proxy works!')
browser.close()
" 2>&1 | tail -5

deactivate
```

**Expected:** Should show proxy server, password length, and "✅ Proxy works!"

---

### 8. Run Complete Health Check

Quick one-liner to check all critical components:

```bash
echo "=== SCRAPER HEALTH CHECK ===" && \
cd /var/www/alexis-scrapper-docker && \
echo "1. Installation Path:" && pwd && \
echo "2. Cron:" && sudo crontab -l -u www-data | grep schedule:run && \
echo "3. Database:" && ls -la scrapper-alexis-web/database/database.sqlite && \
echo "4. Python venv:" && ls -d scrapper-alexis/venv && \
echo "5. Storage:" && ls -ld scrapper-alexis-web/public/storage && \
echo "=== ALL CHECKS PASSED ==="
```

### 9. Critical: Verify No Hardcoded Paths

**This check ensures the installation will work in any directory:**

```bash
cd /path/to/installation

# Check Python config detects database correctly
cd scrapper-alexis
source venv/bin/activate
python3 -c "import config; print('Database:', config.DATABASE_PATH)"
deactivate

# Should output: Database: /your/path/scrapper-alexis-web/database/database.sqlite
# NOT: data/scraper.db or any other path!
```

**If you see `data/scraper.db`:**
1. Delete it: `rm -f scrapper-alexis/data/scraper.db`
2. Verify Laravel database exists: `ls -la scrapper-alexis-web/database/database.sqlite`
3. Re-test Python config

### 10. Verify Shell Scripts Use Dynamic Paths

```bash
cd scrapper-alexis

# Check run scripts don't have hardcoded paths
grep -n "cd /var/www" run_*.sh

# Should return NOTHING!
# If it shows matches, the scripts have hardcoded paths (BAD!)
```

---

## 🔧 Troubleshooting Common Issues

### Issue 1: "Permission denied" when deleting images

**Symptom:** Cannot delete images from web interface

**Cause:** Files created by root instead of www-data

**Fix:**
```bash
# Navigate to installation directory
cd /path/to/alexis-scrapper-docker

# Run setup script to fix permissions
cd scrapper-alexis-web
sudo ./setup.sh
```

**Prevent:** Ensure cron runs as `www-data`, not root!

---

### Issue 2: "NS_ERROR_PROXY_CONNECTION_REFUSED"

**Symptom:** Scraper fails to connect through proxy

**Causes:**
1. Missing `cryptography` Python module → passwords stay encrypted
2. Incorrect proxy credentials in web settings

**Fix:**
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis
source venv/bin/activate
pip install cryptography

# Verify password decryption
python3 -c "import config; print('Password length:', len(config.PROXY_PASSWORD))"
deactivate
```

---

### Issue 3: Messages not appearing in web interface

**Symptom:** Scraper runs but messages don't show up

**Cause:** Python scripts writing to wrong database

**Fix:**
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis
source venv/bin/activate

# Check database path
python3 -c "import config; print('Database:', config.DATABASE_PATH)"
deactivate

# Should output: .../scrapper-alexis-web/database/database.sqlite
# If wrong, the config.py will auto-detect the correct path after the installation
```

---

### Issue 4: Avatar image not showing

**Causes:**
1. Missing storage symlink
2. Avatar directory doesn't exist
3. Wrong permissions

**Fix:**
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis-web

# Create storage symlink
php artisan storage:link

# Create avatar directories
mkdir -p storage/app/avatars storage/app/public/avatars

# Fix permissions
sudo chown -R www-data:www-data storage/app
sudo chmod -R 775 storage/app
```

---

### Issue 5: "Image generation fails with 'no DISPLAY' error"

**Symptom:** Image generation fails in headless mode

**Cause:** Not using xvfb-run for Firefox

**Fix:**
Always run image generation with:
```bash
export HEADLESS=true
xvfb-run -a python3 generate_message_images.py
```

---

### Issue 6: 500 error on web interface

**Fix:**
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis-web

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

---

### Issue 7: Database locked errors

**Fix:**
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis-web

# Fix database permissions
sudo chmod 664 database/database.sqlite
sudo chmod 775 database/
sudo chown www-data:www-data database/database.sqlite

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

---

### Issue 8: Cron jobs not running

**Check cron status:**
```bash
# Check cron service is running
sudo systemctl status cron

# Check cron logs
journalctl -u cron -n 20 --no-pager | grep www-data

# Test manually
sudo -u www-data bash -c 'cd /path/to/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run'
```

### Issue 9: "Database not found" on fresh installation

**Symptom:** Scripts fail with database not found errors

**Cause:** Scripts checking wrong database path

**Fix:**
```bash
cd /path/to/installation/scrapper-alexis

# Check what database path Python is using
source venv/bin/activate
python3 -c "import config; print('Database:', config.DATABASE_PATH)"
deactivate

# Should be: .../scrapper-alexis-web/database/database.sqlite
# If it shows data/scraper.db, delete that file:
rm -f data/scraper.db

# Verify Laravel database exists
ls -la ../scrapper-alexis-web/database/database.sqlite
```

---

## ⚙️ Configuration

### Initial Setup Steps

After successful installation and verification:

1. **Login** to web interface with default credentials
2. **Change password** immediately (Profile → Change Password)
3. **Configure Settings** (Settings page):
   - Facebook credentials (email & password)
   - Twitter/X credentials (email & password)
   - Twitter profile settings (display name, username, avatar URL, verified badge)
   - Facebook profile URLs to scrape (comma-separated)
   - Scraping intervals (in minutes)
   - Proxy settings (optional but recommended):
     - Format: `http://IP:PORT`
     - Username and password
4. **Test scraper** (Dashboard):
   - Click "Run Facebook Scraper Now"
   - Click "Run Twitter Flow Now"
   - Check logs in real-time

### Configuration Reference

| Setting | Location | Purpose |
|---------|----------|---------|
| **System Timezone** | `timedatectl` | Must be Mexico (America/Mexico_City) |
| **App Credentials** | Web Interface → Settings | Facebook and Twitter login |
| **Profile Info** | Web Interface → Settings | Twitter display name, avatar, verified |
| **Scraping Targets** | Web Interface → Settings | Facebook profiles to monitor |
| **Intervals** | Web Interface → Settings | How often to scrape (minutes) |
| **Proxy Settings** | Web Interface → Settings | Proxy for browser requests |
| **Environment** | `.env` file | Laravel configuration (auto-generated) |

### Environment Variables

The `.env` file in `scrapper-alexis-web/` is auto-generated during installation. Key settings:

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/scrapper-alexis-web/database/database.sqlite
```

**Note:** Scraper credentials and settings are stored in the database, NOT in `.env` or environment variables. Configure them via the web interface.

---

## 📝 Useful Commands

### Service Management

```bash
# Restart all services
sudo systemctl restart php8.2-fpm nginx

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status cron

# View service logs
sudo journalctl -u nginx -n 50
sudo journalctl -u php8.2-fpm -n 50
```

### Application Logs

```bash
# Laravel logs
tail -f scrapper-alexis-web/storage/logs/laravel.log

# Scraper logs
tail -f scrapper-alexis/logs/manual_run.log

# System logs
journalctl -f
```

### Database Operations

```bash
cd scrapper-alexis-web

# View messages count
php artisan tinker --execute="echo \App\Models\Message::count();"

# View recent messages
php artisan tinker --execute="\App\Models\Message::latest()->take(5)->get(['content', 'created_at'])->each(fn(\$m) => print_r(\$m->toArray()));"

# Direct SQLite query
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM messages;"
```

### Maintenance Tasks

```bash
# Fix permissions (run anytime)
cd scrapper-alexis-web
sudo ./setup.sh

# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Test scheduler manually
php artisan schedule:run

# Check disk usage
df -h
du -sh scrapper-alexis/logs
du -sh scrapper-alexis/data/message_images
```

### Manual Scraper Execution

```bash
cd scrapper-alexis
source venv/bin/activate

# Run Facebook scraper
./run_facebook_flow.sh

# Run Twitter poster
./run_twitter_flow.sh

# Run page posting
./run_page_poster.sh

# Generate message images
export HEADLESS=true
xvfb-run -a python3 generate_message_images.py

deactivate
```

---

## 🔐 Security Checklist

After installation, verify these security measures:

- [ ] **System timezone** set to Mexico (America/Mexico_City)
- [ ] **Default admin password** changed
- [ ] **APP_DEBUG=false** in `.env`
- [ ] **APP_ENV=production** in `.env`
- [ ] **Cron configured** for www-data user (not root)
- [ ] **Firewall configured** (UFW or iptables)
  - Allow ports: 22 (SSH), 80 (HTTP), 443 (HTTPS)
  - Deny all other incoming ports
- [ ] **SSL certificate** installed (Let's Encrypt recommended)
- [ ] **All files** owned by www-data
- [ ] **Sensitive logs** excluded from Git (already configured in .gitignore)
- [ ] **Image deletion** tested from web interface

### Firewall Setup (Optional but Recommended)

```bash
# Install UFW
sudo apt install ufw

# Configure rules
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status verbose
```

### SSL Certificate Setup (Production)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate (replace with your domain)
sudo certbot --nginx -d your-domain.com

# Auto-renewal is configured automatically
```

---

## 📚 File Structure

```
alexis-scrapper-docker/
├── install.sh                          # Automated installation script
├── INSTALLATION.md                     # This file
├── scrapper-alexis/                    # Python scraper application
│   ├── venv/                           # Python virtual environment
│   ├── config.py                       # Dynamic configuration (auto-detects paths)
│   ├── requirements.txt                # Python dependencies
│   ├── run_facebook_flow.sh            # Facebook scraper runner
│   ├── run_twitter_flow.sh             # Twitter poster runner
│   ├── run_page_poster.sh              # Page posting runner
│   ├── generate_message_images.py      # Image generator
│   ├── data/                           # Application data
│   │   ├── message_images/             # Generated tweet images
│   │   └── auth_states/                # Browser authentication sessions
│   └── logs/                           # Scraper logs
└── scrapper-alexis-web/                # Laravel web interface
    ├── setup.sh                        # Permission fix script
    ├── nginx.conf                      # Nginx configuration template
    ├── .env                            # Laravel environment (auto-generated)
    ├── database/                       # SQLite database
    │   └── database.sqlite             # Main database file
    ├── storage/                        # Laravel storage
    │   ├── app/                        # App files
    │   │   └── avatars/                # Uploaded avatar images
    │   └── logs/                       # Laravel logs
    └── public/                         # Web root
        └── storage/                    # Storage symlink (created automatically)
```

---

## 🔄 What's NOT Committed to Git

The repository excludes these files (automatically ignored):

- ❌ Database files (`*.db`, `*.sqlite`)
- ❌ Log files (`*.log`)
- ❌ Generated images (`*.png`, `*.jpg`)
- ❌ Debug output
- ❌ Browser auth sessions
- ❌ Environment files (`.env`)
- ❌ Dependencies (`venv/`, `node_modules/`, `vendor/`)

Fresh installations start clean with:

- ✅ Empty database with admin user only
- ✅ No old messages or images
- ✅ Correct file permissions
- ✅ Proper configuration

---

## 🆘 Getting Help

### Log Locations

1. **Laravel Application**: `scrapper-alexis-web/storage/logs/laravel.log`
2. **Python Scraper**: `scrapper-alexis/logs/manual_run.log`
3. **Nginx Access**: `/var/log/nginx/access.log`
4. **Nginx Error**: `/var/log/nginx/error.log`
5. **PHP-FPM**: `/var/log/php8.2-fpm.log`
6. **Cron**: `journalctl -u cron`

### Debug Mode

To enable debug output for troubleshooting:

1. Go to **Settings** in web interface
2. Enable debug for specific script type:
   - Facebook Scraper Debug
   - Twitter Flow Debug
   - Page Posting Debug
3. Debug screenshots saved to `scrapper-alexis/debug_output/`

### Common Debug Commands

```bash
# Check if processes are running
ps aux | grep php
ps aux | grep firefox

# Check open ports
sudo netstat -tlnp | grep -E ':(80|443|9000)'

# Check disk space
df -h

# Check memory usage
free -h

# Check system resources
htop  # or top
```

---

## 📝 Important Notes

### Proxy Configuration
- **Format**: Must include `http://` prefix (e.g., `http://65.195.104.91:50100`)
- **Required for**: Twitter avatar downloads, some Facebook operations
- **Authentication**: Automatically encrypted in database

### Database
- **Shared Database**: Both Python and Laravel use the same SQLite database
- **Location**: `scrapper-alexis-web/database/database.sqlite`
- **Permissions**: Must be readable/writable by www-data (664)
- **Automatic Detection**: Python config automatically finds the correct database

### File Permissions
- **Critical for**: Image deletion, log rotation, file operations
- **Owner**: All files must be owned by www-data:www-data
- **Quick Fix**: Run `sudo ./setup.sh` in `scrapper-alexis-web/` directory

### Cron Jobs
- **Runs every minute**: Checks intervals in database before executing
- **Must run as**: www-data user (NOT root!)
- **Purpose**: Triggers Laravel scheduler which manages all periodic tasks

### Image Paths
- **Stored in DB**: As relative paths (e.g., `data/message_images/msg_123.png`)
- **Accessible via**: Laravel storage symlink at `/public/storage/`
- **Format**: PNG with transparent background for Twitter posts

---

## 🎯 Next Steps After Installation

1. ✅ Complete all post-installation verification checks
2. ✅ Login and change default password
3. ✅ Configure Facebook and Twitter credentials
4. ✅ Add Facebook profiles to monitor
5. ✅ Set appropriate scraping intervals
6. ✅ Configure proxy settings (recommended)
7. ✅ Test manual scraper execution
8. ✅ Monitor logs for first few runs
9. ✅ Set up SSL certificate (production)
10. ✅ Configure firewall rules

---

**Version:** 2.0  
**Last Updated:** November 2025  
**Installation Method:** Fully automated with dynamic path detection

For issues or questions, check the logs first, then review the Troubleshooting section above.

# Alexis Scraper - Facebook & Twitter Social Media Automation

Automated social media scraping and posting system with web-based management interface.

---

## 🚀 Quick Start

### Installation (Fresh VPS)

```bash
# 1. Clone the repository
cd /var/www
sudo git clone <YOUR_GIT_REPO_URL> alexis-scrapper-docker
cd alexis-scrapper-docker

# 2. Run automated installation
sudo chmod +x install.sh
sudo ./install.sh

# 3. Access web interface
# Open browser: http://YOUR_SERVER_IP
# Login: admin@scraper.local / password
```

**Installation time:** ~10-15 minutes

**⚠️ Change the default password immediately after first login!**

---

## 📖 Documentation

### Complete Installation Guide

See **[INSTALLATION.md](INSTALLATION.md)** for:
- Detailed system requirements
- Step-by-step installation instructions
- Post-installation verification checklist
- Comprehensive troubleshooting guide
- Security configuration
- All useful commands and maintenance tasks

### Key Features

- ✅ **Zero Hardcoded Paths** - Install anywhere, automatically detects paths
- ✅ **Fully Automated Installation** - One script does everything
- ✅ **Dynamic Configuration** - All settings in database, updated via web interface
- ✅ **Post-Installation Verification** - Built-in health checks
- ✅ **Comprehensive Troubleshooting** - Common issues and solutions documented

---

## 🎯 What This Does

### Facebook Scraper
- Monitors specified Facebook profiles/pages
- Scrapes new messages/posts automatically
- Stores content in database for review
- Runs on configurable intervals

### Twitter/X Poster
- Converts approved messages to tweet images
- Posts to Twitter/X automatically
- Customizable tweet template
- Image generation with profile customization

### Web Interface
- Approve/reject scraped messages
- Configure all credentials and settings
- Manage scraping intervals
- Generate tweet images
- View logs and statistics
- Manual execution controls

---

## 🛠️ System Requirements

- **OS:** Ubuntu 20.04+ or Debian 11+
- **RAM:** 2GB minimum (4GB recommended)
- **Disk:** 5GB free space
- **CPU:** 2 cores minimum

All dependencies installed automatically:
- PHP 8.2+
- Python 3.9+
- Node.js 18+
- Nginx
- SQLite3
- Xvfb (headless browser support)

---

## 📁 Project Structure

```
alexis-scrapper-docker/
├── install.sh              # Automated installer (dynamic paths)
├── INSTALLATION.md         # Complete installation guide
├── README.md               # This file
│
├── scrapper-alexis/        # Python scraper application
│   ├── config.py           # Dynamic configuration (auto-detects paths)
│   ├── venv/               # Python virtual environment
│   ├── requirements.txt    # Python dependencies
│   ├── run_*.sh            # Script runners
│   ├── data/               # Data storage
│   │   ├── message_images/ # Generated tweet images
│   │   └── auth_states/    # Browser sessions
│   └── logs/               # Scraper logs
│
└── scrapper-alexis-web/    # Laravel web interface
    ├── setup.sh            # Permission fix script (dynamic paths)
    ├── nginx.conf          # Nginx configuration template
    ├── database/           # SQLite database
    │   └── database.sqlite # Main database
    ├── storage/            # Laravel storage
    │   ├── app/avatars/    # User-uploaded avatars
    │   └── logs/           # Laravel logs
    └── public/             # Web root
```

---

## ⚙️ Configuration

All configuration is done through the web interface after installation:

1. **Login** to web interface
2. **Navigate** to Settings page
3. **Configure:**
   - Facebook credentials
   - Twitter/X credentials
   - Twitter profile (display name, username, avatar, verified badge)
   - Facebook profiles to monitor
   - Scraping intervals
   - Proxy settings (optional)

No manual configuration files to edit! Everything is stored in the database.

---

## 🔧 Maintenance

### Fix Permissions
```bash
cd /path/to/alexis-scrapper-docker/scrapper-alexis-web
sudo ./setup.sh
```

### View Logs
```bash
# Laravel logs
tail -f scrapper-alexis-web/storage/logs/laravel.log

# Python scraper logs
tail -f scrapper-alexis/logs/manual_run.log
```

### Restart Services
```bash
sudo systemctl restart nginx php8.2-fpm
```

### Manual Script Execution
```bash
cd scrapper-alexis
source venv/bin/activate

# Run Facebook scraper
./run_facebook_flow.sh

# Run Twitter poster
./run_twitter_flow.sh

deactivate
```

---

## 🐛 Troubleshooting

Common issues and solutions are documented in **[INSTALLATION.md](INSTALLATION.md)**:

- Permission denied errors
- Proxy connection issues
- Database access problems
- Image generation failures
- Cron job issues
- And more...

---

## 🔐 Security Notes

- ✅ All credentials encrypted in database
- ✅ No hardcoded paths or sensitive data in code
- ✅ Environment files excluded from Git
- ✅ Proper file permissions (www-data user)
- ✅ Firewall configuration recommended
- ✅ SSL certificate support

---

## 📝 Important Notes

### Dynamic Path Detection
This project uses **ZERO hardcoded paths**. You can install it anywhere:
- `/var/www/alexis-scrapper-docker` (default)
- `/home/user/projects/scraper`
- Any custom location

The installation script and all components automatically detect their location.

### File Permissions
All files must be owned by `www-data` user for proper operation:
- Allows web interface to delete files
- Enables proper cron job execution
- Ensures database write access

Run `sudo ./setup.sh` anytime to fix permissions.

### Timezone Requirement
System timezone **must** be set to Mexico (America/Mexico_City) for proper scheduling.
This is configured automatically during installation.

---

## 📞 Support

1. Check **[INSTALLATION.md](INSTALLATION.md)** for comprehensive documentation
2. Review logs for error messages
3. Run health check script (documented in INSTALLATION.md)
4. Check file permissions with `sudo ./setup.sh`

---

## 📄 License

[Your License Here]

---

**Version:** 2.0  
**Last Updated:** November 2025  
**Improvements:**
- Removed all hardcoded paths
- Single comprehensive installation guide
- Fully automated installation script
- Dynamic path detection throughout
- Improved troubleshooting documentation


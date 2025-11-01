# Installation Guide - Scrapper Alexis (Docker)

Complete installation guide for deploying the Facebook/Twitter scraper system on a fresh VPS.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Initial VPS Setup](#initial-vps-setup)
3. [Docker Installation](#docker-installation)
4. [Clone & Deploy Application](#clone--deploy-application)
5. [Configuration](#configuration)
6. [Start Application](#start-application)
7. [First Login](#first-login)
8. [Verify Installation](#verify-installation)
9. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

### Minimum System Requirements

- **OS:** Ubuntu 20.04+ / Debian 11+ (or any Linux with Docker support)
- **RAM:** 2GB minimum (4GB recommended)
- **Disk:** 20GB free space
- **Ports:** 8006 (or your preferred port)
- **Root/sudo access**

### Required Software

- Docker 20.10+
- Docker Compose v2.0+
- Git

---

## 🚀 Initial VPS Setup

### 1. Update System

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install Essential Tools

```bash
sudo apt install -y curl git wget nano ufw
```

### 3. Configure Firewall (Optional but Recommended)

```bash
# Allow SSH
sudo ufw allow 22/tcp

# Allow web application port
sudo ufw allow 8006/tcp

# Enable firewall
sudo ufw enable
```

---

## 🐳 Docker Installation

### 1. Install Docker

```bash
# Remove old versions
sudo apt remove docker docker-engine docker.io containerd runc

# Install dependencies
sudo apt install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

# Add Docker's official GPG key
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Set up Docker repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Engine
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### 2. Verify Docker Installation

```bash
sudo docker --version
sudo docker compose version
```

Expected output:
```
Docker version 24.0.x, build xxxxx
Docker Compose version v2.x.x
```

### 3. Add User to Docker Group (Optional - avoid using sudo)

```bash
sudo usermod -aG docker $USER
newgrp docker
```

**⚠️ Important:** Logout and login again for group changes to take effect.

---

## 📦 Clone & Deploy Application

### 1. Navigate to Web Directory

```bash
cd /var/www
```

If `/var/www` doesn't exist:
```bash
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www
```

### 2. Clone Repository

```bash
git clone https://github.com/AlexCodeForge/scrapper-alexis.git alexis-scrapper-docker
cd alexis-scrapper-docker
```

### 3. Set Proper Permissions

```bash
# Make scripts executable
chmod +x scrapper-alexis/*.sh

# Set directory permissions
sudo chown -R $USER:$USER /var/www/alexis-scrapper-docker

# Allow Laravel storage write access
chmod -R 775 scrapper-alexis-web/storage
chmod -R 775 scrapper-alexis-web/bootstrap/cache

# Create required directories
mkdir -p images
chmod 777 images
```

---

## ⚙️ Configuration

### 1. Environment Configuration

#### Scraper Environment (.env)

```bash
nano scrapper-alexis/.env
```

**Required configuration:**
```env
# Database
DATABASE_PATH=/app/data/scraper.db

# Browser Settings
HEADLESS=true
SLOW_MO=50

# Scraping Settings
MAX_SCROLL_ATTEMPTS=10
```

**Note:** Facebook credentials will be configured via web interface.

#### Web Application Environment (.env)

```bash
nano scrapper-alexis-web/.env
```

**Verify these settings:**
```env
APP_NAME="Scraper Admin Panel"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-server-ip:8006

DB_CONNECTION=sqlite
DB_DATABASE=/app/data/scraper.db

SESSION_DRIVER=file
SESSION_LIFETIME=120
```

### 2. Docker Compose Configuration (if changing port)

```bash
nano docker-compose.yml
```

Change port mapping (default is 8006):
```yaml
services:
  web:
    ports:
      - "8006:80"  # Change 8006 to your desired port
```

---

## 🎯 Start Application

### 1. Build and Start Containers

```bash
cd /var/www/alexis-scrapper-docker
docker compose up -d --build
```

**Build time:** 5-10 minutes on first run.

### 2. Verify Containers Are Running

```bash
docker ps
```

Expected output:
```
CONTAINER ID   IMAGE                    STATUS                   PORTS
xxxxx          scraper-alexis-web       Up X minutes (healthy)   0.0.0.0:8006->80/tcp
xxxxx          scraper-alexis-scraper   Up X minutes (healthy)
```

### 3. Check Container Logs (if issues)

```bash
# Web container
docker logs scraper-alexis-web --tail 50

# Scraper container
docker logs scraper-alexis-scraper --tail 50
```

---

## 🔐 First Login

### 1. Access Web Interface

Open your browser and navigate to:
```
http://YOUR_SERVER_IP:8006
```

### 2. Default Admin Credentials

```
Email: admin@scraper.local
Password: password
```

**⚠️ CRITICAL:** Change admin password immediately after first login!

### 3. Change Admin Password

Navigate to **Configuración** (Settings) → Change password.

---

## ✅ Verify Installation

### 1. Check System Health

#### Dashboard Metrics

Navigate to the dashboard and verify:
- ✅ Mensajes Totales: 0
- ✅ Perfiles Activos: 0
- ✅ All metrics displaying correctly

#### Container Health

```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
```

Both containers should show `Up X minutes (healthy)`.

### 2. Configure Facebook Credentials

1. Navigate to **Configuración** (Settings)
2. Under **Credenciales de Facebook:**
   - Username: Your Facebook email/phone
   - Password: Your Facebook password
3. Click **Guardar Credenciales**

### 3. Configure Posting Settings

1. Navigate to **Configuración** → **Configuración de Publicación**
2. Set:
   - **Nombre de la Página:** Your Facebook page name
   - **URL de la Página:** Your Facebook page URL
   - **Intervalo Mín/Max:** Posting interval in minutes (e.g., 60-120)
3. **Enable posting** if needed for scheduled runs
4. Click **Guardar Configuración**

### 4. Test Manual Run

1. Navigate to **Panel** (Dashboard)
2. Click **"Ejecutar Scraper Facebook"** button
3. Navigate to **Logs** to verify execution
4. Check for success message

---

## 🔧 Troubleshooting

### Container Won't Start

**Check Docker service:**
```bash
sudo systemctl status docker
sudo systemctl restart docker
```

**Rebuild containers:**
```bash
cd /var/www/alexis-scrapper-docker
docker compose down
docker compose up -d --build --force-recreate
```

### Permission Errors

```bash
cd /var/www/alexis-scrapper-docker
sudo chown -R $USER:$USER .
chmod -R 775 scrapper-alexis-web/storage
chmod -R 775 scrapper-alexis-web/bootstrap/cache
chmod 777 images
```

### Port Already in Use

**Check what's using port 8006:**
```bash
sudo netstat -tulpn | grep 8006
```

**Kill process or change port in docker-compose.yml**

### Can't Access Web Interface

**Verify container is running:**
```bash
docker ps | grep scraper-alexis-web
```

**Check firewall:**
```bash
sudo ufw status
sudo ufw allow 8006/tcp
```

**Check logs:**
```bash
docker logs scraper-alexis-web --tail 100
```

### Database Errors

**Reset database:**
```bash
docker exec scraper-alexis-scraper rm -f /app/data/scraper.db
docker restart scraper-alexis-web
```

The web container will recreate the database on restart.

### Manual Run Not Working

**Verify helpers.php is up to date:**
```bash
docker exec scraper-alexis-web cat /var/www/app/helpers.php | grep "MANUAL_RUN"
```

Should show: `-e MANUAL_RUN=1`

**Clear cache:**
```bash
docker exec scraper-alexis-web php artisan optimize:clear
```

---

## 🔄 Maintenance Commands

### View Logs

```bash
# Real-time web logs
docker logs -f scraper-alexis-web

# Real-time scraper logs
docker logs -f scraper-alexis-scraper

# View specific log file
docker exec scraper-alexis-scraper cat /app/logs/page_poster_$(date +%Y%m%d).log
```

### Restart Containers

```bash
cd /var/www/alexis-scrapper-docker
docker compose restart
```

### Stop Application

```bash
docker compose down
```

### Update Application

```bash
cd /var/www/alexis-scrapper-docker
git pull
docker compose up -d --build
```

### Backup Database

```bash
docker cp scraper-alexis-scraper:/app/data/scraper.db ./backup_$(date +%Y%m%d).db
```

### Clean Up Docker Resources

```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Complete cleanup (⚠️ removes everything not running)
docker system prune -a --volumes
```

---

## 📚 Additional Resources

### Project Structure

```
alexis-scrapper-docker/
├── scrapper-alexis/           # Python scraper service
│   ├── core/                  # Core scraper logic
│   ├── facebook/              # Facebook automation
│   ├── twitter/               # Twitter automation
│   ├── utils/                 # Utility functions
│   └── run_*.sh               # Execution scripts
├── scrapper-alexis-web/       # Laravel web interface
│   ├── app/                   # Application logic
│   ├── resources/views/       # Blade templates
│   └── routes/                # Web routes
├── docker-compose.yml         # Container orchestration
└── images/                    # Shared images directory
```

### Important Files

- `scrapper-alexis/.env` - Scraper configuration
- `scrapper-alexis-web/.env` - Web app configuration
- `docker-compose.yml` - Container setup
- `/app/data/scraper.db` - SQLite database (inside container)

### Useful Commands

```bash
# Enter scraper container
docker exec -it scraper-alexis-scraper bash

# Enter web container
docker exec -it scraper-alexis-web bash

# Run Laravel artisan commands
docker exec scraper-alexis-web php artisan [command]

# Run Python script manually
docker exec scraper-alexis-scraper python3 /app/relay_agent.py
```

---

## 🆘 Getting Help

### Check Logs First

1. Navigate to **Logs** page in web interface
2. Look for errors in recent execution
3. Check Docker logs: `docker logs scraper-alexis-scraper`

### Common Issues

- **"Scheduled run detected"**: Manual run not working → Clear cache with `php artisan optimize:clear`
- **"Database locked"**: Restart containers → `docker compose restart`
- **Facebook login failing**: Update credentials in Settings
- **Images not generating**: Check disk space and permissions on `images/` folder

### Debug Mode

Enable debug mode temporarily:
```bash
# Edit web .env
docker exec scraper-alexis-web nano /var/www/.env
# Change APP_DEBUG=false to APP_DEBUG=true
# Restart container
docker restart scraper-alexis-web
```

**⚠️ Remember to disable debug mode in production!**

---

## ✨ Success!

Your scraper is now installed and ready to use!

**Next steps:**
1. ✅ Configure Facebook credentials
2. ✅ Configure posting settings
3. ✅ Run first manual scrape
4. ✅ Set up cron jobs (automatic scheduled runs are enabled by default)

**Access your panel at:** `http://YOUR_SERVER_IP:8006`

---

## 📝 Notes

- The application runs 24/7 via Docker
- Cron jobs are managed inside the scraper container
- All data persists in Docker volumes (survives restarts)
- Regular backups of `/app/data/scraper.db` recommended
- Keep credentials secure - never commit to version control

---

**Version:** 2.0  
**Last Updated:** November 2025  
**Maintained by:** AlexCodeForge


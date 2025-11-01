# Scrapper Alexis - Deployment Guide

## 🚀 Overview

This is a fully Dockerized Facebook/Twitter scraper and poster system. All shell scripts have been removed - the system now runs Python scripts directly via cron jobs.

---

## 📦 Core Components

### 1. **Facebook Page Poster** (`facebook_page_poster.py`)
- **Purpose**: Posts approved images to Facebook pages
- **Schedule**: Every 30 minutes (checks for approved images)
- **Manual Run**: `docker exec scraper-alexis-scraper python3 facebook_page_poster.py`
- **Manual Test**: `docker exec scraper-alexis-scraper sh -c "MANUAL_RUN=1 python3 facebook_page_poster.py"`

### 2. **Facebook Scraper** (`relay_agent.py`)
- **Purpose**: Scrapes Facebook messages from multiple profiles
- **Schedule**: Configurable interval (default: disabled)
- **Enable**: Set `FACEBOOK_SCRAPER_ENABLED=true` in `.env`

### 3. **Twitter Profile Extractor** (`extract_twitter_profile.py`)
- **Purpose**: Extracts and posts Twitter profiles
- **Schedule**: Configurable interval (default: disabled)
- **Enable**: Set `TWITTER_POSTER_ENABLED=true` in `.env`

---

## 🔧 Configuration

### Environment Variables (`.env`)

```bash
# Facebook Scraper
FACEBOOK_SCRAPER_ENABLED=false
FACEBOOK_INTERVAL_MIN=45
FACEBOOK_INTERVAL_MAX=80

# Twitter Poster
TWITTER_POSTER_ENABLED=false
TWITTER_INTERVAL_MIN=8
TWITTER_INTERVAL_MAX=15
```

### Posting Settings (Web UI)

Configure Facebook page posting via the web interface at `http://localhost:8080`:
- Page Name: `Miltoner`
- Page URL: `https://www.facebook.com/TeamMiltoner/`
- Posting Interval: Min/Max minutes between posts
- Enable/Disable: Toggle posting on/off

---

## 🐳 Docker Commands

### Build & Start
```bash
docker compose build scraper
docker compose up -d scraper
```

### View Logs
```bash
# All logs
docker logs scraper-alexis-scraper -f

# Specific log files
docker exec scraper-alexis-scraper tail -f /app/logs/page_poster_cron.log
docker exec scraper-alexis-scraper tail -f /app/logs/facebook_cron.log
docker exec scraper-alexis-scraper tail -f /app/logs/twitter_cron.log
```

### Manual Testing
```bash
# Test Facebook posting (bypass scheduling)
docker exec scraper-alexis-scraper sh -c "cd /app && MANUAL_RUN=1 python3 facebook_page_poster.py"

# Test Facebook scraper
docker exec scraper-alexis-scraper sh -c "cd /app && python3 relay_agent.py"

# Test Twitter extractor
docker exec scraper-alexis-scraper sh -c "cd /app && python3 extract_twitter_profile.py"
```

### Check Cron Jobs
```bash
docker exec scraper-alexis-scraper crontab -l
```

---

## 📊 How It Works

### Facebook Page Posting Flow

1. **Approval Process**
   - Messages are scraped from Facebook
   - Images are generated via web UI
   - Admin approves images for posting

2. **Posting Logic**
   - Cron runs every 30 minutes
   - Checks if interval has passed since last post
   - Gets oldest approved image from database
   - Logs in to Facebook (using saved auth state)
   - **Switches to page profile** (critical fix!)
   - Posts image to page

3. **Profile Switching Fix** (v2.0)
   - Old method: Looked for "¿Qué estás pensando?" button ❌
   - **New method**: Checks for composer TEXT with page name ✅
   - Detects: "¿Qué estás pensando, Miltoner?" (proves we're in page mode)
   - Clicks composer to open "Crear publicación" modal
   - Uploads and publishes image

---

## 🔍 Debugging

### Check if posting is working

```bash
# View recent logs
docker exec scraper-alexis-scraper tail -100 /app/logs/page_poster_cron.log

# Check for errors
docker exec scraper-alexis-scraper grep -i error /app/logs/page_poster_cron.log

# View debug screenshots
docker exec scraper-alexis-scraper ls -lht /pictures/ | head -10
```

### Common Issues

#### 1. **"No approved images to post"**
- **Solution**: Approve images via web UI at `http://localhost:8080`

#### 2. **"Failed to switch to page profile"**
- **Solution**: Check `auth_facebook.json` is valid
- Re-authenticate if needed

#### 3. **"Composer not found"**
- **Solution**: This was fixed in v2.0 - composer is now detected as TEXT, not button

---

## 📁 Directory Structure

```
/app/
├── facebook_page_poster.py      # Facebook posting script
├── relay_agent.py               # Facebook scraper
├── extract_twitter_profile.py   # Twitter extractor
├── docker-entrypoint.sh         # Container startup (only .sh file)
├── config.py                    # Configuration
├── facebook/                    # Facebook modules
│   ├── facebook_auth.py
│   └── facebook_page_manager.py # Profile switching logic
├── core/                        # Core modules
│   ├── database.py
│   └── debug_helper.py
├── data/                        # Shared data (volume)
│   └── scraper.db              # SQLite database
├── logs/                        # Log files
├── auth/                        # Auth state (volume)
│   └── auth_facebook.json
└── debug_output/                # Debug screenshots
```

---

## 🎯 Recent Changes

### v2.0 - Profile Switch Fix (2025-11-01)

**Problem**: Facebook posting failed - script couldn't detect when profile switch succeeded

**Root Cause**: 
- Code looked for "¿Qué estás pensando?" as a **button**
- Reality: It's clickable **TEXT**, not a button element
- `is_in_page_mode()` always failed, even when profile switch worked

**Solution**:
1. Modified `is_in_page_mode()` to check for composer TEXT with page name
2. Updated `post_image_to_page()` to click composer TEXT (not button)
3. Validates profile switch by detecting "¿Qué estás pensando, Miltoner?"

**Result**: ✅ Posting now works 100%

### v1.1 - Shell Script Removal (2025-11-01)

**Changes**:
- Deleted all `.sh` wrapper scripts (except `docker-entrypoint.sh`)
- Updated cron jobs to call Python scripts directly
- Simplified deployment and maintenance

---

## 🧪 Testing Checklist

Before deploying to production:

- [ ] Build container: `docker compose build scraper`
- [ ] Start container: `docker compose up -d scraper`
- [ ] Check container logs: `docker logs scraper-alexis-scraper`
- [ ] Verify cron jobs: `docker exec scraper-alexis-scraper crontab -l`
- [ ] Test manual posting: `MANUAL_RUN=1 python3 facebook_page_poster.py`
- [ ] Approve test image via web UI
- [ ] Wait 30 minutes OR trigger manual posting
- [ ] Check Facebook page for new post
- [ ] Verify logs show success: `tail /app/logs/page_poster_cron.log`

---

## 📞 Support

For issues or questions:
1. Check logs first: `docker logs scraper-alexis-scraper`
2. Review debug screenshots: `docker exec scraper-alexis-scraper ls /pictures/`
3. Test manually with `MANUAL_RUN=1` to isolate issues
4. Check database: `docker exec scraper-alexis-scraper sqlite3 /app/data/scraper.db`

---

## 🔐 Security Notes

- **Auth State**: Stored in `auth/auth_facebook.json` (Docker volume)
- **Credentials**: Loaded from `credenciales.txt` (not in repo)
- **Proxy**: Configured via `config.py` (required for Facebook access)
- **Database**: Shared between scraper and web containers

---

*Last updated: 2025-11-01*



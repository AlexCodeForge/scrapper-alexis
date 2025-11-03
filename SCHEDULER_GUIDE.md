# Laravel Scheduler System Guide

## Current Setup

**Hybrid Laravel Scheduler** with database-controlled execution and dynamic delays.

---

## How It Works

```
System Cron (every minute)
    ↓
Laravel Scheduler checks database
    ↓
If enabled=0: DON'T RUN (efficient) ✅
If enabled=1: Execute command with dynamic delay from DB
    ↓
Python scripts run with credentials from database
```

---

## System Components

### 1. System Crontab (Single Entry)
```bash
* * * * * cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Laravel Commands
- `php artisan scraper:facebook` - Facebook scraper
- `php artisan scraper:twitter` - Twitter poster  
- `php artisan scraper:page-poster` - Facebook page poster

### 3. Schedule Configuration
**Location:** `/var/www/alexis-scrapper-docker/scrapper-alexis-web/bootstrap/app.php`

```php
->withSchedule(function (Schedule $schedule): void {
    // Facebook Scraper - Hourly, only when enabled
    $schedule->command('scraper:facebook')
        ->hourly()
        ->when(function () {
            return ScraperSettings::getSettings()->facebook_enabled;
        })
        ->withoutOverlapping()
        ->runInBackground()
        ->onOneServer();
    
    // Twitter Poster - Hourly at :15, only when enabled
    $schedule->command('scraper:twitter')
        ->hourly()
        ->at(':15')
        ->when(function () {
            return ScraperSettings::getSettings()->twitter_enabled;
        })
        ->withoutOverlapping()
        ->runInBackground()
        ->onOneServer();
    
    // Facebook Page Poster - Every 30 minutes
    $schedule->command('scraper:page-poster')
        ->everyThirtyMinutes()
        ->when(function () {
            return PostingSetting::getSettings()->enabled;
        })
        ->withoutOverlapping()
        ->runInBackground()
        ->onOneServer();
})
```

---

## Features

### ✅ Database-Controlled Execution
- **Enable/Disable:** Via web UI at http://213.199.33.207:8006/settings
- **Dynamic Intervals:** Min/max minutes for random delays (from database)
- **No wasted execution:** Commands only run when enabled

### ✅ Built-in Protections
- **`->withoutOverlapping()`** - Prevents multiple instances
- **`->onOneServer()`** - Single execution in multi-server setup
- **`->runInBackground()`** - Non-blocking parallel execution

### ✅ Dynamic Random Delays
Commands apply 20% variance based on database intervals:
```php
// Example: interval 45-80 min
$avgInterval = (45 + 80) / 2 = 62.5 min
$maxDelay = 62.5 * 60 * 0.20 = 750 seconds (12.5 min)
$delay = rand(0, 750) // Random 0-12.5 min delay
```

---

## Web UI Control

### Enable/Disable Scrapers
1. Go to: http://213.199.33.207:8006/settings
2. Click "Programación Cron y Cronjobs"
3. Toggle Facebook Scraper / Twitter Poster
4. Shows: 🟢 Activo or 🔴 Detenido
5. Click "Guardar Configuración"

### Adjust Intervals
- Set **Mínimo** (minimum minutes between runs)
- Set **Máximo** (maximum minutes between runs)
- Commands apply 20% random variance on each execution

---

## Commands Reference

### List Scheduled Tasks
```bash
php artisan schedule:list
```

### Test Commands Manually
```bash
# Skip random delay for testing
php artisan scraper:facebook --skip-delay
php artisan scraper:twitter --skip-delay
php artisan scraper:page-poster --skip-delay

# Run with delay
php artisan scraper:facebook
```

### Run Scheduler Manually
```bash
php artisan schedule:run
```

### Run Scheduler in Foreground (Testing)
```bash
php artisan schedule:work
```

---

## Current Schedule

| Command | Frequency | Next Run | Controlled By |
|---------|-----------|----------|---------------|
| `scraper:facebook` | Hourly at :00 | (See schedule:list) | `scraper_settings.facebook_enabled` |
| `scraper:twitter` | Hourly at :15 | (See schedule:list) | `scraper_settings.twitter_enabled` |
| `scraper:page-poster` | Every 30 min | (See schedule:list) | `posting_settings.enabled` |

---

## Database Tables

### `scraper_settings`
```sql
facebook_enabled         BOOLEAN   -- Enable/disable Facebook scraper
twitter_enabled          BOOLEAN   -- Enable/disable Twitter poster
facebook_interval_min    INTEGER   -- Min minutes for random delay
facebook_interval_max    INTEGER   -- Max minutes for random delay
twitter_interval_min     INTEGER   -- Min minutes for random delay
twitter_interval_max     INTEGER   -- Max minutes for random delay
facebook_email           TEXT      -- Facebook credentials (encrypted)
facebook_password        TEXT      -- (encrypted)
twitter_email            TEXT      -- Twitter credentials (encrypted)
twitter_password         TEXT      -- (encrypted)
```

### `posting_settings`
```sql
enabled                  BOOLEAN   -- Enable/disable page posting
interval_min             INTEGER   -- Min minutes between posts
interval_max             INTEGER   -- Max minutes between posts
```

---

## Logs

### Laravel Logs
```bash
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log
```

### Scraper Logs
```bash
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis/logs/cron_execution.log
```

### System Cron Logs
```bash
grep CRON /var/log/syslog | tail -20
```

---

## Troubleshooting

### Check if scheduler is running
```bash
crontab -l  # Should show single Laravel Scheduler cron
php artisan schedule:list  # Shows registered schedules
```

### Check database settings
```bash
sqlite3 /var/www/scrapper-alexis/data/scraper.db "SELECT facebook_enabled, twitter_enabled FROM scraper_settings;"
```

### Web UI shows "Detenido" (Stopped)
**Cause:** Database has `facebook_enabled=0` or `twitter_enabled=0`  
**Fix:** Toggle switch in web UI and save

### Commands not running
```bash
# Check if cron service is running
systemctl status cron

# Check Laravel logs
tail -f storage/logs/laravel.log

# Test command manually
php artisan scraper:facebook --skip-delay
```

---

## Why This Approach?

### vs Direct System Cron
- ✅ **More efficient:** Only runs when enabled (no wasted checks)
- ✅ **Built-in overlap prevention:** No duplicate runs
- ✅ **Centralized logic:** All scheduling in one place
- ✅ **Easy testing:** `php artisan schedule:work`
- ✅ **Better monitoring:** Integrated Laravel logging

### vs Pure Laravel Scheduler
- ✅ **Dynamic delays:** Random variance from database
- ✅ **Database control:** Enable/disable via web UI
- ✅ **Flexible intervals:** Min/max from database

---

## File Locations

```
/var/www/alexis-scrapper-docker/scrapper-alexis-web/
├── bootstrap/app.php                          # Schedule configuration
├── app/Console/Commands/
│   ├── FacebookScraperCommand.php             # Facebook scraper command
│   ├── TwitterScraperCommand.php              # Twitter poster command
│   └── PagePosterCommand.php                  # Page poster command
└── app/Models/
    ├── ScraperSettings.php                    # Scraper settings model
    └── PostingSetting.php                     # Page posting model

/var/www/alexis-scrapper-docker/scrapper-alexis/
├── relay_agent.py                             # Facebook scraper script
├── run_twitter_flow.sh                        # Twitter flow script
└── run_page_poster.sh                         # Page poster script

/var/www/scrapper-alexis/data/scraper.db       # Shared SQLite database
```

---

## Summary

**Single system cron** → **Laravel Scheduler** → **Database check** → **Execute if enabled** → **Apply dynamic delay** → **Run Python scripts**

- Control everything via: http://213.199.33.207:8006/settings
- Only runs when enabled (efficient)
- Dynamic delays from database
- Built-in overlap prevention
- Easy to test and monitor


# Scraper Fixes Summary - November 7, 2025

## ✅ COMPLETED FIXES

### 1. **Logs Cleared**
- ✅ Cleared `/var/www/alexis-scrapper-docker/scrapper-alexis/logs/cron_execution.log`
- ✅ Cleared `/var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log`
- ✅ Cleared all dated log files
- ✅ **Database intact**: 836 messages preserved ✓

### 2. **Firefox Memory Leak Fixed**
**Problem:** Multiple Firefox instances stayed open, consuming ~600MB RAM each, crashing server

**Solutions Implemented:**

#### A. Added Firefox Cleanup in Bash Scripts
- `run_facebook_flow.sh`: Added `trap` to kill Firefox on exit/failure
- `run_page_poster.sh`: Added `trap` to kill Firefox on exit/failure
- `run_image_generation.sh`: Added cleanup for any stray processes

**What this does:**
- Kills Firefox processes when script exits (success OR failure)
- Uses process tree killing (`pkill -P $$`) to catch child processes
- Cleans up orphaned Firefox processes automatically

#### B. Created Periodic Cleanup Script
- New file: `/var/www/alexis-scrapper-docker/scrapper-alexis/cleanup_orphaned_firefox.sh`
- Kills Firefox processes running longer than 15 minutes
- Warns if more than 3 Firefox instances are detected
- **Recommended:** Add to crontab to run every 10 minutes:
  ```bash
  */10 * * * * /bin/bash /var/www/alexis-scrapper-docker/scrapper-alexis/cleanup_orphaned_firefox.sh >> /var/www/alexis-scrapper-docker/scrapper-alexis/logs/cleanup.log 2>&1
  ```

### 3. **Scheduler Fixed**
**Problem:** Jobs had internal delays (up to 12 min sleep), causing overlapping instances

**Solution:**
- Added `--skip-delay` flag to all scheduled commands
- Scheduler now controls timing via `scheduler_state` database table
- Commands execute immediately without internal delays
- Fixed "ghost run" bug where state updated but command didn't execute

**Current Configuration:**
```php
$schedule->command('scraper:facebook --skip-delay')
    ->everyMinute()
    ->when(/* Check scheduler_state */)
    ->onSuccess(/* Update scheduler_state */)
    ->withoutOverlapping()  // Prevents duplicate runs
    ->runInBackground()     // Doesn't block scheduler
    ->onOneServer();        // Single instance only
```

### 4. **Process Locking Enhanced**
- All scripts use `flock` to prevent duplicate execution
- Lock files: `/var/lock/facebook_scraper.lock`, `/var/lock/page_poster.lock`, `/var/lock/image_generator.lock`
- Max concurrent Firefox instances: **3** (one per script)

## 📊 CURRENT STATUS

### Memory Usage
```
Total:     11Gi
Used:      3.9Gi
Available: 7.8Gi ✓ HEALTHY
```

### Scheduler Status
```bash
* * * * * php artisan scraper:facebook --skip-delay       ✓ Ready
* * * * * php artisan scraper:page-poster --skip-delay    ✓ Ready
* * * * * php artisan scraper:generate-images --skip-delay ✓ Ready
```

### Firefox Processes
```
Current count: 0 scraper instances ✓ CLEAN
Max allowed:   3 instances
```

## 🚀 READY TO RESUME

You can now safely enable the cron jobs. The system will:
1. ✅ Only run 1 instance of each script at a time (flock + withoutOverlapping)
2. ✅ Kill Firefox automatically on script exit/failure (trap cleanup)
3. ✅ Execute immediately without delays (--skip-delay flag)
4. ✅ Update scheduler state only after successful completion
5. ✅ Never exceed 3 Firefox instances total

## 📝 RECOMMENDED CRONTAB

Add this to root crontab (`crontab -e`):
```bash
# Laravel Scheduler (runs scrapers)
* * * * * cd /var/www/alexis-scrapper-docker/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1

# Firefox cleanup (runs every 10 minutes)
*/10 * * * * /bin/bash /var/www/alexis-scrapper-docker/scrapper-alexis/cleanup_orphaned_firefox.sh >> /var/www/alexis-scrapper-docker/scrapper-alexis/logs/cleanup.log 2>&1
```

## 🔧 MONITORING

Check logs:
```bash
# Execution logs
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis/logs/cron_execution.log

# Laravel logs  
tail -f /var/www/alexis-scrapper-docker/scrapper-alexis-web/storage/logs/laravel.log

# Firefox processes
ps aux | grep -E "firefox.*playwright" | grep -v grep | wc -l

# Memory usage
free -h
```

---
**Date:** 2025-11-07 18:32  
**Status:** ✅ READY FOR PRODUCTION



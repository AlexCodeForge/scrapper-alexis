# Quick Start - Cronjob Testing

## ⚠️ CRITICAL: Proxy Required!

**Twitter WILL NOT WORK without proxy configured!**  
Check that `copy.env` has:
```env
PROXY_SERVER=http://77.47.156.7:50100
PROXY_USERNAME=gNhwRLuC
PROXY_PASSWORD=OZ7h82Gknc
```

See `PROXY_CRITICAL_README.md` for details.

---

## 🚀 Setup in 3 Steps

### Step 1: Test Workflows
```bash
cd /var/www/scrapper-alexis
bash test_workflows.sh
```
**Look for:** `🔒 Using proxy:` in Facebook logs  
**Twitter should NOT exit** with proxy error

### Step 2: Install Cronjobs
```bash
bash setup_cron.sh
```

### Step 3: Monitor
```bash
tail -f logs/cron_*.log
```

---

## 📋 Testing Schedule

| Task | Frequency | Next Run After |
|------|-----------|----------------|
| **Facebook Scraper** | Every 1 hour | Top of each hour (e.g., 10:00, 11:00) |
| **Twitter Poster** | Every 8 minutes | 8-minute intervals (e.g., 10:00, 10:08, 10:16) |
| **Image Generator** | After Twitter posts | Automatically after each post |

---

## 📊 Expected Results (per hour)

- **Facebook:** ~20 messages scraped per configured profile
- **Twitter:** 7-8 posts published
- **Images:** 7-8 images generated

---

## 🔍 Quick Commands

### Check What's Running
```bash
crontab -l
```

### View Database Stats
```bash
cd /var/www/scrapper-alexis
sqlite3 data/scraper.db "SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN posted_to_twitter = 1 THEN 1 ELSE 0 END) as posted,
  SUM(CASE WHEN image_generated = 1 THEN 1 ELSE 0 END) as images
FROM messages;"
```

### Watch Logs Live
```bash
# All logs
tail -f logs/cron_*.log

# Just execution times
tail -f logs/cron_execution.log
```

### Stop Cronjobs
```bash
crontab -r
```

### Restart Cronjobs
```bash
crontab crontab_config.txt
```

---

## ⚠️ Important Notes

1. **Before starting:** Ensure you have authenticated both Facebook and Twitter at least once manually
2. **Disk space:** Monitor disk usage, especially for images and logs
3. **Test duration:** Run for 2-3 hours initially to verify everything works
4. **Browser processes:** Chromium instances will start and stop automatically

---

## 🐛 Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| "Not logged in" errors | Delete `auth/*.json` and re-authenticate manually |
| Cronjobs not running | Check `systemctl status cron` |
| Scripts failing | Check permissions: `ls -l *.sh` |
| Logs empty | Wait 8 minutes for first Twitter run, or 1 hour for Facebook |

---

## 📁 Key Files

- `run_facebook_flow.sh` - Facebook scraper script
- `run_twitter_flow.sh` - Twitter poster script (also runs image generation)
- `run_image_generation.sh` - Image generator script
- `crontab_config.txt` - Cron schedule configuration
- `setup_cron.sh` - Automated setup script
- `test_workflows.sh` - Manual testing script

---

## 🛑 When Testing is Complete

```bash
# Stop cronjobs
crontab -r

# Backup your data
cp data/scraper.db data/scraper_test_backup_$(date +%Y%m%d).db

# Review results
sqlite3 data/scraper.db "SELECT * FROM messages ORDER BY scraped_at DESC LIMIT 10;"
```

---

## 📖 Full Documentation

For detailed information, see `CRON_SETUP.md`


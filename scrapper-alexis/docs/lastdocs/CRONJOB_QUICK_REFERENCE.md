# Cronjob Quick Reference

**Status:** ✅ ACTIVE  
**Installed:** October 13, 2025

---

## 📋 Schedule

| Job | Frequency | Next Run |
|-----|-----------|----------|
| 🐦 **Twitter Poster** | Every 8 minutes | Check: `bash monitor_cron.sh` |
| 📘 **Facebook Scraper** | Every 1 hour (at :00) | Check: `bash monitor_cron.sh` |
| 🖼️ **Image Generator** | After each Twitter post | Automatic |

---

## 📊 Monitoring Commands

### Quick Status Check
```bash
cd /var/www/scrapper-alexis
bash monitor_cron.sh
```

### Watch Logs in Real-Time
```bash
# Twitter logs (updates every 8 minutes)
tail -f logs/cron_twitter.log

# Facebook logs (updates every hour)
tail -f logs/cron_facebook.log

# Watch both
tail -f logs/cron_*.log
```

### View Last 50 Lines
```bash
tail -n 50 logs/cron_twitter.log
tail -n 50 logs/cron_facebook.log
```

### Check Database Stats
```bash
sqlite3 data/scraper.db "SELECT COUNT(*) as total, SUM(posted_to_twitter) as posted FROM messages;"
```

---

## 🔧 Management Commands

### View Current Crontab
```bash
crontab -l
```

### Edit Crontab
```bash
crontab -e
```

### Stop All Cronjobs
```bash
crontab -r
```

### Reinstall Cronjobs
```bash
cd /var/www/scrapper-alexis
crontab crontab_config.txt
```

---

## 📁 Important Files

| File | Purpose |
|------|---------|
| `crontab_config.txt` | Cron schedule configuration |
| `run_facebook_flow.sh` | Facebook scraper script |
| `run_twitter_flow.sh` | Twitter poster + image gen script |
| `monitor_cron.sh` | Quick status monitoring |
| `logs/cron_facebook.log` | Facebook scraper output |
| `logs/cron_twitter.log` | Twitter poster output |

---

## ✅ What's Working

- ✅ **Proxy Configuration** - All workflows use proxy
- ✅ **VPS Stability** - Using Firefox + xvfb-run
- ✅ **Unicode Support** - Spanish accents preserved (á, é, í, ó, ú, ñ)
- ✅ **Message Validation** - Verifies correct text posted
- ✅ **Retry Logic** - Auto-retry on failures
- ✅ **Debug Screenshots** - Saved for troubleshooting
- ✅ **Error Handling** - Graceful failure handling

---

## 🚨 Troubleshooting

### Check if Cron is Running
```bash
systemctl status cron
# or
service cron status
```

### Check Cron Logs (System)
```bash
grep CRON /var/log/syslog | tail -20
```

### Test Scripts Manually
```bash
# Test Facebook scraper
bash run_facebook_flow.sh

# Test Twitter poster
bash run_twitter_flow.sh

# Test image generator
bash run_image_generation.sh
```

### Common Issues

**Issue:** Cronjobs not executing  
**Fix:** Check cron service is running: `systemctl start cron`

**Issue:** Scripts fail with "Permission denied"  
**Fix:** Make scripts executable: `chmod +x run_*.sh`

**Issue:** "Display not found" errors  
**Fix:** Already fixed with xvfb-run in all scripts ✅

**Issue:** Accents lost in tweets  
**Fix:** Already fixed with locator.type() ✅

---

## 📈 Expected Behavior

### Every 8 Minutes (Twitter)
1. Select oldest unposted message from database
2. Post to Twitter with proxy
3. Generate image for the posted message
4. Update database with post URL

### Every Hour (Facebook)
1. Scrape new messages from Facebook group
2. Save to database
3. Mark as "not posted" (ready for Twitter)

---

## 🎯 Success Indicators

✅ Logs show "SUCCESS" messages  
✅ `cron_twitter.log` updates every 8 minutes  
✅ `cron_facebook.log` updates every hour  
✅ Database `posted_to_twitter` count increases  
✅ New images in `data/message_images/`  
✅ Live tweets on https://x.com/soyemizapata  

---

## 🛑 Emergency Stop

To immediately stop all cronjobs:
```bash
crontab -r
```

To restart them:
```bash
cd /var/www/scrapper-alexis
crontab crontab_config.txt
```

---

**Need help?** Check logs first:
```bash
bash monitor_cron.sh
```





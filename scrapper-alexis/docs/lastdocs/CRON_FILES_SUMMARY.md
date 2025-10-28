# Cronjob Setup - Files Summary

## 📁 New Files Created

All files have been created in `/var/www/scrapper-alexis/`

### 🔧 Executable Scripts

| File | Purpose | Executable |
|------|---------|-----------|
| `run_facebook_flow.sh` | Runs Facebook scraper | ✓ |
| `run_twitter_flow.sh` | Runs Twitter poster + image generator | ✓ |
| `run_image_generation.sh` | Runs image generator standalone | ✓ |
| `setup_cron.sh` | Automated cronjob installation | ✓ |
| `test_workflows.sh` | Manual workflow testing | ✓ |
| `monitor_status.sh` | Real-time system monitoring | ✓ |

### 📄 Configuration & Documentation

| File | Purpose |
|------|---------|
| `crontab_config.txt` | Cron schedule configuration |
| `CRON_SETUP.md` | Complete setup guide (detailed) |
| `QUICK_START_CRON.md` | Quick start guide (condensed) |
| `CRON_FILES_SUMMARY.md` | This file - overview of all files |

---

## 🚀 Quick Start Guide

### Step 1: Verify Prerequisites
```bash
cd /var/www/scrapper-alexis

# Check configuration exists
ls -l copy.env

# Check authentication files exist (or you'll need to create them)
ls -l auth/auth_facebook.json auth/auth_x.json
```

### Step 2: Test Workflows
```bash
# Run the interactive test script
bash test_workflows.sh
```

This will test each workflow individually. If any fail, fix the issues before proceeding.

### Step 3: Install Cronjobs
```bash
# Run the setup script
bash setup_cron.sh
```

This will:
- Backup your existing crontab
- Show you the new schedule
- Ask for confirmation
- Install the cronjobs

### Step 4: Monitor Activity
```bash
# View current status
bash monitor_status.sh

# Watch logs in real-time
tail -f logs/cron_*.log
```

---

## ⏰ Testing Schedule Details

### Cronjob Configuration

**Facebook Scraper** - Every 1 hour
```cron
0 * * * * /bin/bash /var/www/scrapper-alexis/run_facebook_flow.sh >> /var/www/scrapper-alexis/logs/cron_facebook.log 2>&1
```
- Runs at: 00:00, 01:00, 02:00, ..., 23:00
- Duration: ~30-60 seconds per profile
- Output: New messages in database

**Twitter Poster + Image Generator** - Every 8 minutes
```cron
*/8 * * * * /bin/bash /var/www/scrapper-alexis/run_twitter_flow.sh >> /var/www/scrapper-alexis/logs/cron_twitter.log 2>&1
```
- Runs at: 00:00, 00:08, 00:16, 00:24, 00:32, 00:40, 00:48, 00:56
- Duration: ~15-30 seconds per execution
- Output: 1 tweet posted + 1 image generated per run

### Expected Testing Results (2 hours)

Assuming 1 Facebook profile configured:

- **Facebook scrapes:** 2 runs → ~40 messages
- **Twitter posts:** 15 runs → 15 tweets posted
- **Images generated:** 15 images
- **Messages remaining:** ~25 unposted

---

## 📊 Monitoring Commands

### Check System Status
```bash
bash monitor_status.sh
```

Shows:
- Cronjob status
- Database statistics
- Recent activity
- Storage usage
- Next scheduled runs

### View Logs
```bash
# All cron logs (live)
tail -f logs/cron_*.log

# Facebook logs only
tail -f logs/cron_facebook.log

# Twitter logs only
tail -f logs/cron_twitter.log

# Execution timestamps
tail -f logs/cron_execution.log
```

### Database Queries
```bash
cd /var/www/scrapper-alexis

# Total messages
sqlite3 data/scraper.db "SELECT COUNT(*) FROM messages;"

# Posted vs unposted
sqlite3 data/scraper.db "SELECT posted_to_twitter, COUNT(*) FROM messages GROUP BY posted_to_twitter;"

# Recent activity
sqlite3 data/scraper.db "SELECT * FROM messages ORDER BY scraped_at DESC LIMIT 5;"
```

---

## 🛠 Management Commands

### View Installed Cronjobs
```bash
crontab -l
```

### Stop Cronjobs
```bash
# Remove all cronjobs
crontab -r

# Or edit and comment out
crontab -e
```

### Restart Cronjobs
```bash
crontab /var/www/scrapper-alexis/crontab_config.txt
```

### Modify Schedule
```bash
# Edit the config file
nano /var/www/scrapper-alexis/crontab_config.txt

# Reinstall
crontab /var/www/scrapper-alexis/crontab_config.txt
```

---

## 🗂 Log Files Created

| Log File | Content |
|----------|---------|
| `logs/cron_facebook.log` | Facebook scraper output |
| `logs/cron_twitter.log` | Twitter poster + image gen output |
| `logs/cron_execution.log` | Execution timestamps |
| `logs/relay_agent_YYYYMMDD.log` | Detailed application logs |

---

## 🔍 Troubleshooting

### Cronjobs Not Running

Check cron service:
```bash
systemctl status cron
# or
systemctl status crond
```

Check system logs:
```bash
grep CRON /var/log/syslog | tail -20
```

### Scripts Failing

Check permissions:
```bash
ls -l /var/www/scrapper-alexis/*.sh
# All should be -rwxr-xr-x
```

Test manually:
```bash
bash /var/www/scrapper-alexis/run_facebook_flow.sh
```

View errors:
```bash
cat /var/www/scrapper-alexis/logs/cron_facebook.log
```

### Authentication Expired

Re-authenticate Facebook:
```bash
cd /var/www/scrapper-alexis
rm auth/auth_facebook.json
python3 relay_agent.py
# Login when browser opens
```

Re-authenticate Twitter:
```bash
cd /var/www/scrapper-alexis
rm auth/auth_x.json
python3 -m twitter.twitter_post
# Login when browser opens
```

---

## ⚠️ Important Notes

1. **Browser Automation**: Chromium will start headless - you won't see windows
2. **Concurrent Runs**: Scripts prevent overlapping executions
3. **Disk Space**: Monitor storage - images and logs can grow
4. **Network**: Requires stable internet for scraping and posting
5. **Proxy**: Twitter requires proxy configured in `copy.env`

---

## 📈 Testing Recommendations

### Initial Test (2-3 hours)
- Monitor logs actively
- Check database growth
- Verify authentication stays valid
- Watch for errors

### Extended Test (8-12 hours)
- Monitor resource usage
- Check disk space
- Verify no authentication issues
- Review success rates

### Overnight Test (24 hours)
- Full day cycle
- Check stability
- Review all collected data
- Analyze any patterns in failures

---

## 🎯 Success Criteria

✓ Cronjobs running as scheduled  
✓ Facebook scraper completing without errors  
✓ Twitter posting successfully  
✓ Images generating correctly  
✓ No authentication timeouts  
✓ Logs showing expected activity  
✓ Database growing appropriately  

---

## 🛑 Stopping the Test

When testing is complete:

```bash
# Stop cronjobs
crontab -r

# Backup data
cp data/scraper.db data/scraper_test_$(date +%Y%m%d_%H%M%S).db

# Review results
bash monitor_status.sh

# Check total messages
sqlite3 data/scraper.db "SELECT COUNT(*) FROM messages;"
```

---

## 📚 Documentation Reference

- **Quick Start**: `QUICK_START_CRON.md`
- **Detailed Guide**: `CRON_SETUP.md`
- **Project Overview**: `project-summary/README.md`
- **Workflows**: `project-summary/WORKFLOWS.md`
- **Usage Guide**: `project-summary/USAGE.md`

---

## 🤝 Need Help?

1. Check `CRON_SETUP.md` for detailed troubleshooting
2. Review logs in `logs/` directory
3. Run `monitor_status.sh` to see current state
4. Test workflows manually with `test_workflows.sh`

---

**Created**: October 13, 2025  
**Purpose**: Testing cronjob automation for Social Media Relay Agent  
**Duration**: For testing period only - adjust schedule for production use


# Cronjob Setup Guide

## ⚠️ CRITICAL: Read This First!

**Proxy configuration is MANDATORY!** Twitter will not work without it.

Before proceeding, verify `copy.env` contains:
```env
PROXY_SERVER=http://77.47.156.7:50100
PROXY_USERNAME=gNhwRLuC
PROXY_PASSWORD=OZ7h82Gknc
```

See `PROXY_CRITICAL_README.md` for complete proxy documentation.

---

## Overview

This guide will help you set up automated cronjobs for testing the Social Media Relay Agent on Linux.

## Cron Schedule

| Workflow | Frequency | Description |
|----------|-----------|-------------|
| **Facebook Scraper** | Every 1 hour | Scrapes messages from configured Facebook profiles |
| **Twitter Poster** | Every 8 minutes | Posts one message to Twitter |
| **Image Generator** | After each Twitter post | Automatically generates images after posting |

## Quick Start

### 1. Make Scripts Executable

```bash
cd /var/www/scrapper-alexis
chmod +x run_facebook_flow.sh
chmod +x run_twitter_flow.sh
chmod +x run_image_generation.sh
chmod +x setup_cron.sh
chmod +x test_workflows.sh
```

### 2. Test Workflows Manually

Before setting up cronjobs, test each workflow manually:

```bash
bash test_workflows.sh
```

This will guide you through testing:
- Facebook scraping
- Twitter posting
- Image generation

### 3. Install Cronjobs

Once testing is successful, install the cronjobs:

```bash
bash setup_cron.sh
```

This will:
- Make all scripts executable
- Backup your existing crontab
- Show you the new configuration
- Ask for confirmation before installing

## Manual Installation

If you prefer to set up cron manually:

```bash
# Edit your crontab
crontab -e

# Add these lines:
0 * * * * /bin/bash /var/www/scrapper-alexis/run_facebook_flow.sh >> /var/www/scrapper-alexis/logs/cron_facebook.log 2>&1
*/8 * * * * /bin/bash /var/www/scrapper-alexis/run_twitter_flow.sh >> /var/www/scrapper-alexis/logs/cron_twitter.log 2>&1
```

## Monitoring

### View Active Cronjobs

```bash
crontab -l
```

### Monitor Logs in Real-time

```bash
# All logs
tail -f /var/www/scrapper-alexis/logs/cron_*.log

# Facebook only
tail -f /var/www/scrapper-alexis/logs/cron_facebook.log

# Twitter only
tail -f /var/www/scrapper-alexis/logs/cron_twitter.log

# Execution timestamps
tail -f /var/www/scrapper-alexis/logs/cron_execution.log
```

### Check Database Stats

```bash
cd /var/www/scrapper-alexis
sqlite3 data/scraper.db "SELECT COUNT(*) as total FROM messages;"
sqlite3 data/scraper.db "SELECT COUNT(*) as posted FROM messages WHERE posted_to_twitter = 1;"
sqlite3 data/scraper.db "SELECT COUNT(*) as images FROM messages WHERE image_generated = 1;"
```

## Execution Timeline Example

For a 1-hour period starting at 10:00:

```
10:00 - Facebook scraper runs (hourly)
10:00 - Twitter poster runs (every 8 min)
10:08 - Twitter poster runs
10:16 - Twitter poster runs
10:24 - Twitter poster runs
10:32 - Twitter poster runs
10:40 - Twitter poster runs
10:48 - Twitter poster runs
10:56 - Twitter poster runs
11:00 - Facebook scraper runs (hourly)
11:00 - Twitter poster runs (every 8 min)
...
```

**Expected behavior:**
- Facebook scrapes 20 messages per hour (per configured profile)
- Twitter posts 7-8 messages per hour (one every 8 minutes)
- Images generate after each Twitter post

## Managing Cronjobs

### Temporarily Stop All Cronjobs

```bash
# List and backup current crontab
crontab -l > /var/www/scrapper-alexis/crontab_backup.txt

# Remove all cronjobs
crontab -r
```

### Restore Cronjobs

```bash
crontab /var/www/scrapper-alexis/crontab_backup.txt
```

### Modify Schedule

Edit the crontab directly:

```bash
crontab -e
```

Or modify `crontab_config.txt` and reinstall:

```bash
nano /var/www/scrapper-alexis/crontab_config.txt
crontab /var/www/scrapper-alexis/crontab_config.txt
```

## Cron Syntax Reference

```
* * * * * command
│ │ │ │ │
│ │ │ │ └─── Day of week (0-7, 0 and 7 are Sunday)
│ │ │ └───── Month (1-12)
│ │ └─────── Day of month (1-31)
│ └───────── Hour (0-23)
└─────────── Minute (0-59)
```

### Common Examples

```bash
# Every minute
* * * * * command

# Every 5 minutes
*/5 * * * * command

# Every hour at minute 0
0 * * * * command

# Every day at 8:00 AM
0 8 * * * command

# Every Monday at 9:00 AM
0 9 * * 1 command
```

## Troubleshooting

### Cronjobs Not Running

1. **Check if cron service is running:**
   ```bash
   systemctl status cron
   # or
   systemctl status crond
   ```

2. **Check system logs:**
   ```bash
   grep CRON /var/log/syslog
   ```

3. **Verify crontab is installed:**
   ```bash
   crontab -l
   ```

### Scripts Failing

1. **Check script permissions:**
   ```bash
   ls -l /var/www/scrapper-alexis/*.sh
   # Should show -rwxr-xr-x
   ```

2. **Check log files:**
   ```bash
   cat /var/www/scrapper-alexis/logs/cron_facebook.log
   cat /var/www/scrapper-alexis/logs/cron_twitter.log
   ```

3. **Test script manually:**
   ```bash
   bash /var/www/scrapper-alexis/run_facebook_flow.sh
   ```

### Authentication Issues

If you see login errors in logs:

1. **Re-authenticate Facebook:**
   ```bash
   cd /var/www/scrapper-alexis
   rm auth/auth_facebook.json
   python3 relay_agent.py
   # Login manually when browser opens
   ```

2. **Re-authenticate Twitter:**
   ```bash
   cd /var/www/scrapper-alexis
   rm auth/auth_x.json
   python3 -m twitter.twitter_post
   # Login manually when browser opens
   ```

### Environment Variables Not Loading

Cron doesn't load your user environment by default. The scripts are configured to:
- Change to the correct directory
- Activate the virtual environment
- Load environment from `copy.env`

If issues persist, add explicit environment variables to your crontab:

```bash
crontab -e

# Add at the top:
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
```

## Best Practices for Testing

1. **Start with short test periods:**
   - Run for 2-3 hours initially
   - Monitor logs actively
   - Check database growth

2. **Monitor system resources:**
   ```bash
   htop  # or top
   ```

3. **Check disk space:**
   ```bash
   df -h
   du -sh /var/www/scrapper-alexis/data/
   du -sh /var/www/scrapper-alexis/logs/
   ```

4. **Backup before long runs:**
   ```bash
   cp data/scraper.db data/scraper_backup_$(date +%Y%m%d).db
   ```

## Stopping the Test

When you're done testing:

```bash
# Stop all cronjobs
crontab -r

# Or keep crontab but comment out jobs
crontab -e
# Add # at the start of each line
```

## Production Recommendations

After successful testing, consider adjusting frequencies:

- **Facebook Scraper:** Once or twice daily (not hourly)
- **Twitter Poster:** Every 2-4 hours (not every 8 minutes)
- **Image Generator:** Once daily in evening

Example production schedule:

```cron
# Facebook - twice daily
0 8,20 * * * /bin/bash /var/www/scrapper-alexis/run_facebook_flow.sh >> /var/www/scrapper-alexis/logs/cron_facebook.log 2>&1

# Twitter - every 3 hours
0 */3 * * * /bin/bash /var/www/scrapper-alexis/run_twitter_flow.sh >> /var/www/scrapper-alexis/logs/cron_twitter.log 2>&1
```

## Log Rotation

To prevent logs from growing too large:

```bash
# Create logrotate config
sudo nano /etc/logrotate.d/scrapper-alexis

# Add:
/var/www/scrapper-alexis/logs/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
}
```

## Support

For issues or questions:
1. Check the main documentation in `project-summary/`
2. Review workflow details in `WORKFLOWS.md`
3. Check troubleshooting in `USAGE.md`


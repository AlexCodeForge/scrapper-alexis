# Manual Script Execution Guide

## Running Scripts Manually (Instant - No Delays)

When you run scripts manually, they automatically skip all random delays. The scripts detect whether they're run by cron or manually.

### Facebook Scraper

```bash
# From host
docker exec -it scraper-alexis-scraper /app/run_facebook_flow.sh

# Inside container
docker exec -it scraper-alexis-scraper bash
cd /app
./run_facebook_flow.sh
```

**Expected behavior:** ✅ Runs instantly, logs "Facebook manual run: Skipping random delay"

### Twitter Poster

```bash
# From host
docker exec -it scraper-alexis-scraper /app/run_twitter_flow.sh

# Inside container
docker exec -it scraper-alexis-scraper bash
cd /app
./run_twitter_flow.sh
```

**Expected behavior:** ✅ Runs instantly, logs "Twitter manual run: Skipping random delay"

---

## How It Works

The scripts detect manual execution using two methods:

1. **Terminal detection:** Checks if stdin is a terminal (`-t 0`)
2. **Environment variable:** You can also set `SKIP_DELAY=1` to force skipping delays

### Force Skip Delay

```bash
# If for some reason automatic detection doesn't work:
SKIP_DELAY=1 ./run_facebook_flow.sh
SKIP_DELAY=1 ./run_twitter_flow.sh
```

---

## Cron Execution (Automatic - With Delays)

When cron runs the scripts, they automatically apply random delays:

- **Facebook:** ±20% of configured interval (e.g., if interval is 60min avg, delay is 0-12 minutes)
- **Twitter:** ±20% of configured interval (e.g., if interval is 11min avg, delay is 0-2.2 minutes)

These delays make the automation look more natural and human-like.

---

## Logs

Check execution logs:

```bash
# Cron execution logs
docker exec scraper-alexis-scraper cat /app/logs/cron_execution.log

# Facebook scraper logs
docker exec scraper-alexis-scraper cat /app/logs/facebook_cron.log

# Twitter poster logs
docker exec scraper-alexis-scraper cat /app/logs/twitter_cron.log
```

---

## Default Credentials

**Login at:** `http://YOUR_SERVER_IP:8006`

- Email: `admin@scraper.local`
- Password: `password`

⚠️ **Change the password immediately after first login!**


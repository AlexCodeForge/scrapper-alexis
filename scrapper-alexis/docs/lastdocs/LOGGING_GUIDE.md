# Logging Guide

**Updated:** October 13, 2025

---

## 📁 Log Files in `logs/`

### 1. **Cronjob Execution Log**
**File:** `logs/cron_execution.log`

Tracks when each cronjob runs:
```
[2025-10-13 21:08:54] Twitter posting completed
[2025-10-13 21:09:22] Image generation completed
[2025-10-13 21:16:42] Twitter posting completed
[2025-10-13 21:16:59] Image generation completed
[2025-10-13 21:00:00] Facebook scraping completed
```

**Purpose:** Quick timeline of all cronjob executions

---

### 2. **Twitter Posting Log**
**File:** `logs/cron_twitter.log`

Detailed output from Twitter posting workflow:
- Message selection
- Authentication status
- Posting attempts
- Success/failure details
- Image generation after posting

**View last entries:**
```bash
tail -50 logs/cron_twitter.log
```

---

### 3. **Facebook Scraping Log**
**File:** `logs/cron_facebook.log`

Detailed output from Facebook scraping:
- Profile scraping progress
- Messages found
- Duplicates detected
- Quality filtering
- Database updates

**View last entries:**
```bash
tail -50 logs/cron_facebook.log
```

---

### 4. **Message Posting & Image Log** ✨ NEW
**File:** `logs/message_posting_log.txt`

Comprehensive audit trail of all messages:

**Sections:**
1. **Successfully Posted Messages**
   - ID, text, URL, image status
   - Posted timestamp
   - Image file verification

2. **Skipped Messages (Quality Filter)**
   - Messages rejected for being too short/low quality
   - Marked as `SKIPPED_QUALITY_FILTER`
   - No images generated

3. **Pending Messages**
   - Next messages in queue
   - Not yet posted

4. **Statistics**
   - Total messages
   - Posted vs pending
   - Images generated
   - Anomalies (if any)

5. **Image Files Verification**
   - Check which images exist on disk
   - Identify missing files
   - Flag anomalies

**Generate/Update:**
```bash
python3 generate_posting_log.py
```

**View:**
```bash
cat logs/message_posting_log.txt
# or
less logs/message_posting_log.txt
```

---

### 5. **Daily Relay Agent Log**
**File:** `logs/relay_agent_YYYYMMDD.log`

Detailed Facebook scraping logs (created automatically):
- Session tracking
- Profile details
- Message extraction
- Database operations

**Example:** `relay_agent_20251013.log`

---

## 🔍 Common Log Queries

### Check Last Twitter Posts
```bash
grep "Post URL:" logs/cron_twitter.log | tail -10
```

### Check for Errors
```bash
grep -i "error\|failed" logs/cron_twitter.log | tail -20
```

### Count Today's Posts
```bash
grep "Twitter posting completed" logs/cron_execution.log | grep "2025-10-13" | wc -l
```

### View Skipped Messages
```bash
grep "SKIPPED_QUALITY_FILTER" logs/cron_twitter.log
```

---

## 📊 Message Posting Audit

### Full Audit Report
```bash
python3 generate_posting_log.py
cat logs/message_posting_log.txt
```

### Check for Image Issues
```bash
# Find messages posted but missing images
sqlite3 data/scraper.db << EOF
SELECT id, message_text 
FROM messages 
WHERE posted_to_twitter = 1 
AND post_url != 'SKIPPED_QUALITY_FILTER'
AND (image_generated = 0 OR image_generated IS NULL);
EOF
```

### Verify Image Files Exist
```bash
# Generate audit log
python3 generate_posting_log.py

# Check for "MISSING" in verification section
grep "MISSING" logs/message_posting_log.txt
```

---

## 🔄 Automated Logging

### Cronjob Logs (Automatic)
All cronjobs automatically append to their respective log files:

**Twitter Flow** (`run_twitter_flow.sh`):
```bash
echo "[timestamp] Twitter posting completed" >> logs/cron_execution.log
```

**Facebook Flow** (`run_facebook_flow.sh`):
```bash
echo "[timestamp] Facebook scraping completed" >> logs/cron_execution.log
```

**Image Generation** (`run_image_generation.sh`):
```bash
echo "[timestamp] Image generation completed" >> logs/cron_execution.log
```

### Manual Audit (Run Anytime)
```bash
python3 generate_posting_log.py
```

---

## 🚨 Troubleshooting

### Issue: Missing images for posted messages
**Check:**
```bash
python3 generate_posting_log.py
grep "Image: ❌ Missing" logs/message_posting_log.txt
```

**Fix:** Regenerate images
```bash
xvfb-run -a python3 generate_message_images.py
```

### Issue: Images for skipped messages
**Check:**
```bash
python3 generate_posting_log.py
grep "⚠️.*SKIPPED MESSAGE" logs/message_posting_log.txt
```

**This was fixed on 2025-10-13:**
- Updated SQL query to exclude skipped messages
- Cleaned up 2 pre-fix anomalies (IDs 180, 192)
- Won't happen again! ✅

### Issue: Cronjobs not running
**Check execution log:**
```bash
tail -20 logs/cron_execution.log
```

**Expected pattern (every 8 minutes):**
```
[timestamp] Twitter posting completed
[timestamp] Image generation completed
```

---

## 📁 Log File Sizes

Monitor log file growth:
```bash
ls -lh logs/
```

**If logs get too large:**
```bash
# Archive old logs
tar -czf logs_archive_$(date +%Y%m%d).tar.gz logs/*.log
# Clear old logs
> logs/cron_twitter.log
> logs/cron_facebook.log
```

**Keep:**
- `cron_execution.log` (timeline)
- `message_posting_log.txt` (audit)

---

## ✅ Best Practices

1. **Regular Audits**
   ```bash
   # Generate fresh audit report
   python3 generate_posting_log.py
   ```

2. **Monitor Cron Executions**
   ```bash
   # Check last 10 executions
   tail -20 logs/cron_execution.log
   ```

3. **Check for Issues**
   ```bash
   # Look for errors in last hour
   grep -i "error\|failed" logs/cron_twitter.log | tail -20
   ```

4. **Verify Image Integrity**
   ```bash
   # Generate audit, check for missing files
   python3 generate_posting_log.py
   grep "MISSING" logs/message_posting_log.txt
   ```

---

## 🎯 Quick Reference

| Need | Command |
|------|---------|
| View cronjob timeline | `cat logs/cron_execution.log` |
| Check Twitter details | `tail -50 logs/cron_twitter.log` |
| Check Facebook details | `tail -50 logs/cron_facebook.log` |
| Full message audit | `python3 generate_posting_log.py && cat logs/message_posting_log.txt` |
| Watch logs live | `tail -f logs/cron_*.log` |
| Find errors | `grep -i error logs/cron_*.log` |

---

**Logging System Status:** ✅ COMPLETE  
**Last Updated:** October 13, 2025  
**All Issues Resolved:** Yes





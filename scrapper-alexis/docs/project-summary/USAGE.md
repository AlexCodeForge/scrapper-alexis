# Usage Guide

## Prerequisites
- Windows 10/11
- Python 3.10 or higher
- Playwright browsers installed
- Proxy credentials (for Twitter)

## Initial Setup

### 1. Install Dependencies
```bash
pip install -r requirements.txt
python -m playwright install chromium
```

### 2. Configure Environment
Create `copy.env` file in project root:
```env
# Facebook Profiles (comma-separated URLs)
FACEBOOK_PROFILES=https://facebook.com/profile1,https://facebook.com/profile2

# Twitter Credentials
X_EMAIL=your_twitter_email
X_PASSWORD=your_twitter_password

# Database
DATABASE_PATH=data/scraper.db
```

### 3. Authenticate Social Platforms

#### Facebook Authentication
Run Facebook scraping once to authenticate:
```bash
python relay_agent.py
```
- Browser will open
- Login manually if prompted
- Session saved to `auth/auth_facebook.json`

#### Twitter Authentication  
Run Twitter posting once to authenticate:
```bash
python -m twitter.twitter_post
```
- Browser will open
- Login manually if prompted
- Session saved to `auth/auth_x.json`

---

## Production Workflows

### Workflow 1: Scrape Facebook Messages
```bash
run_facebook_flow.bat
```

**What it does**:
- Scrapes 20 messages from each configured Facebook profile
- Prevents duplicate scraping via message hashing
- Stores messages in database with UTF-8 encoding
- Exports JSON backup to debug_output/

**Expected Output**:
```
Messages ready for Twitter: 20
Already posted messages: 0
Total messages: 20
```

---

### Workflow 2: Post to Twitter
```bash
run_twitter_flow.bat
```

**What it does**:
- Gets next unposted message from database
- Posts to Twitter via proxy
- Captures post URL and avatar URL
- Updates database with posting status

**Expected Output**:
```
SUCCESS: Message posted to Twitter!
LIVE POST: https://x.com/username/status/...
```

**Note**: Run multiple times to post multiple messages

---

### Workflow 3: Generate Images
```bash
run_image_generation.bat
```

**What it does**:
- Finds posted messages without images
- Downloads avatars via proxy (cached)
- Generates high-quality tweet-style PNG images
- Stores images in data/message_images/
- Updates database with generation status

**Expected Output**:
```
Successful images: 7
Images saved in: data\message_images\
```

---

## Advanced Usage

### Check System Status
```python
python -c "
from core.database import initialize_database
db = initialize_database('data/scraper.db')

# Message stats
stats = db.get_message_stats()
print(f'Total: {stats[\"total_messages\"]}')
print(f'Posted: {stats[\"posted\"]}')
print(f'Unposted: {stats[\"unposted\"]}')

# Image stats
img_stats = db.get_message_image_stats()
print(f'Images: {img_stats[\"images_generated\"]}')
"
```

### Manual Operations

#### Run Facebook Scraping Directly
```bash
python relay_agent.py
```

#### Run Twitter Posting Directly
```bash
python -m twitter.twitter_post
```

#### Run Image Generation Directly
```bash
python generate_message_images.py
```

---

## File Locations

### Configuration
- `copy.env` - Environment variables and credentials
- `config.py` - Python configuration (reads from copy.env)

### Authentication
- `auth/auth_facebook.json` - Facebook session
- `auth/auth_x.json` - Twitter session

### Data
- `data/scraper.db` - Main database
- `data/message_images/` - Generated tweet images
- `avatar_cache/` - Cached avatar images

### Logs
- `logs/relay_agent_YYYYMMDD.log` - Daily logs
- `debug_output/run_*/` - Debug screenshots per session

### Backups
- `debug_output/run_*/extraction/` - JSON message backups

---

## Common Operations

### Add New Facebook Profile
1. Edit `copy.env`
2. Add URL to `FACEBOOK_PROFILES` (comma-separated)
3. Run `run_facebook_flow.bat`

### Reset Database (Fresh Start)
```python
# Delete database file
import os
os.remove('data/scraper.db')

# Authentication files are preserved
# Next run will recreate database
```

### View Database Contents
```bash
sqlite3 data/scraper.db
.tables
SELECT * FROM messages LIMIT 5;
.exit
```

### Re-authenticate Platform
Delete session file and run workflow:
```bash
# For Facebook
del auth\auth_facebook.json
run_facebook_flow.bat

# For Twitter
del auth\auth_x.json
run_twitter_flow.bat
```

---

## Troubleshooting

### "No unposted messages available"
- Run Facebook scraping first: `run_facebook_flow.bat`
- Check if profiles are configured in `copy.env`

### "Not logged in to Twitter"
- Delete `auth/auth_x.json`
- Run `run_twitter_flow.bat` and login manually

### "Proxy connection failed"
- Verify proxy credentials in `copy.env`
- Test proxy connection separately

### "Database locked"
- Close any DB browser tools
- Only one script should run at a time

### "Image generation failed"
- Check `twitter/tweet_template.html` exists
- Verify avatars are downloadable

---

## Best Practices

1. **Run workflows in order**: Facebook → Twitter → Images
2. **Check authentication** before automated runs
3. **Monitor logs** in `logs/` directory
4. **Backup database** before major changes
5. **Test with few profiles** first
6. **Use debug screenshots** for troubleshooting

---

## Workflow Frequency

### Recommended Schedule
- **Facebook Scraping**: Once daily
- **Twitter Posting**: Every 2-4 hours (one message per run)
- **Image Generation**: Once daily after posting

### Automation (Optional)
Use Windows Task Scheduler to automate:
```
Task 1: Daily 8am - run_facebook_flow.bat
Task 2: Every 3 hours - run_twitter_flow.bat  
Task 3: Daily 9pm - run_image_generation.bat
```


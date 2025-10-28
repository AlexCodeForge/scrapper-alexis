# How to Create Environment Files

## ⚠️ These files need to be created manually due to security restrictions

---

## Step 1: Create `.env.example`

1. In the project root directory (`C:\Users\Alex\Desktop\alexis scrapper\`), create a new file named `.env.example`
2. Copy and paste this content:

```
# Facebook Credentials
FACEBOOK_EMAIL=your_email@example.com
FACEBOOK_PASSWORD=your_facebook_password

# X/Twitter Credentials
X_EMAIL=your_x_username_or_email
X_PASSWORD=your_x_password

# Target Facebook Message URL
FACEBOOK_MESSAGE_URL=https://www.facebook.com/messages/t/...

# Browser Configuration
HEADLESS=false
SLOW_MO=50

# Timeouts (milliseconds)
DEFAULT_TIMEOUT=30000
NAVIGATION_TIMEOUT=30000
LOGIN_TIMEOUT=60000

# Rate Limiting
MAX_RETRIES=3
BASE_RETRY_DELAY=2
MAX_RETRY_DELAY=60

# Logging
LOG_LEVEL=INFO

# Browser Fingerprint
USER_AGENT=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36
LOCALE=en-US
TIMEZONE=America/New_York

# Screenshots
SCREENSHOT_DIR=screenshots
SCREENSHOT_QUALITY=100

# Database Configuration
DATABASE_PATH=relay_agent.db
DUPLICATE_CHECK_HOURS=24
AUTO_BACKUP=true
BACKUP_RETENTION_DAYS=7
```

---

## Step 2: Create `.env`

1. In the project root directory, create a new file named `.env`
2. Copy and paste this content with **YOUR ACTUAL CREDENTIALS**:

```
# Facebook Credentials
FACEBOOK_EMAIL=bernardogarcia.mx
FACEBOOK_PASSWORD=Aagt127222$

# X/Twitter Credentials
X_EMAIL=YOUR_TWITTER_USERNAME_OR_EMAIL_HERE
X_PASSWORD=YOUR_TWITTER_PASSWORD_HERE

# Target Facebook Message URL
FACEBOOK_MESSAGE_URL=https://www.facebook.com/messages/t/YOUR_CONVERSATION_ID_HERE

# Browser Configuration
HEADLESS=false
SLOW_MO=50

# Timeouts (milliseconds)
DEFAULT_TIMEOUT=30000
NAVIGATION_TIMEOUT=30000
LOGIN_TIMEOUT=60000

# Rate Limiting
MAX_RETRIES=3
BASE_RETRY_DELAY=2
MAX_RETRY_DELAY=60

# Logging
LOG_LEVEL=INFO

# Browser Fingerprint
USER_AGENT=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36
LOCALE=en-US
TIMEZONE=America/New_York

# Screenshots
SCREENSHOT_DIR=screenshots
SCREENSHOT_QUALITY=100

# Database Configuration
DATABASE_PATH=relay_agent.db
DUPLICATE_CHECK_HOURS=24
AUTO_BACKUP=true
BACKUP_RETENTION_DAYS=7
```

3. **Replace these values:**
   - `YOUR_TWITTER_USERNAME_OR_EMAIL_HERE` → Your X/Twitter email or username
   - `YOUR_TWITTER_PASSWORD_HERE` → Your X/Twitter password
   - `YOUR_CONVERSATION_ID_HERE` → The specific Facebook message/conversation URL you want to scrape

---

## Step 3: Verify Setup

After creating both files, run:

```bash
python relay_agent.py
```

If configured correctly, you should see:
```
2025-10-09 XX:XX:XX,XXX - __main__ - INFO - Configuration validated successfully
2025-10-09 XX:XX:XX,XXX - __main__ - INFO - Relay Agent starting...
2025-10-09 XX:XX:XX,XXX - __main__ - INFO - Phase 0: Setup complete
```

---

## 🔒 Security Reminder

- ✅ `.env` is already in `.gitignore` - it won't be committed to git
- ✅ Never share your `.env` file
- ✅ Never commit credentials to version control
- ✅ The `.env.example` file has placeholder values and is safe to commit



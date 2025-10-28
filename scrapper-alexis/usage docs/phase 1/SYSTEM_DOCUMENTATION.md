# Relay Agent System Documentation

## 📋 Table of Contents
1. [How It Works - Simple Overview](#how-it-works---simple-overview)
2. [Authentication & Sessions Explained](#authentication--sessions-explained)
3. [Complete Process Flow](#complete-process-flow)
4. [Data Storage - Current Status](#data-storage---current-status)
5. [File Structure](#file-structure)
6. [Configuration Guide](#configuration-guide)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 How It Works - Simple Overview

The Relay Agent is a web scraper that:
1. **Logs into Facebook** (saves your session so you don't login every time)
2. **Navigates to a Facebook page/profile** 
3. **Scrolls and extracts messages/posts** (with smart fail-safe scrolling)
4. **Currently logs messages** (Phase 3 will save to database)

**Key Feature**: Smart scrolling with fail-safe - if Facebook's page gets stuck, it automatically forces scroll to keep loading content.

---

## 🔐 Authentication & Sessions Explained

### What are Sessions?

Think of a session like a "pass" that proves you're logged in. Instead of typing your password every time, the system saves this "pass" and reuses it.

### How Authentication Works

#### First Time Login:
```
1. You run the script
2. Browser opens Facebook login page
3. You manually log in
4. System saves TWO files:
   - auth_facebook.json → Your login session (cookies, tokens)
   - auth_facebook_session.json → Metadata about when you logged in
```

#### Next Time You Run:
```
1. Script checks: "Do I have auth_facebook.json?"
2. YES → Loads that file and you're instantly logged in
3. NO → Opens browser for you to login again
```

### Session Files Explained

**`auth_facebook.json`** (Auto-generated)
- Contains: Cookies, tokens, session data
- Think of it as: Your digital "keycard" to Facebook
- Never share this file - it's like your password!

**`auth_facebook_session.json`** (Metadata)
```json
{
  "platform": "facebook",
  "saved_at": "2025-10-09T14:18:33.160000"
}
```
- Just tracks when you logged in
- Helps with debugging

### Session Lifecycle

```
┌─────────────────────────────────────────────────┐
│  START: Run relay_agent.py                      │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
    ┌────────────────────────────┐
    │ Check: auth_facebook.json  │
    │        exists?             │
    └────────┬──────────┬────────┘
             │          │
        YES  │          │  NO
             ▼          ▼
    ┌────────────┐  ┌──────────────────┐
    │ Load saved │  │ Open browser     │
    │ session    │  │ Wait for manual  │
    │            │  │ login            │
    └─────┬──────┘  └────────┬─────────┘
          │                  │
          │                  ▼
          │         ┌─────────────────────┐
          │         │ Save session to:    │
          │         │ auth_facebook.json  │
          │         └─────────┬───────────┘
          │                   │
          └───────────────────┘
                      │
                      ▼
            ┌──────────────────┐
            │ Verify logged in │
            │ (check redirect) │
            └─────────┬────────┘
                      │
                      ▼
            ┌──────────────────────┐
            │ Ready to scrape!     │
            └──────────────────────┘
```

---

## 📊 Complete Process Flow

### Phase 1: Facebook Content Extraction (CURRENT)

```
Step 1: Launch Browser
├── Headless mode (background) or visible
├── Anti-detection settings (looks like real browser)
└── Slow motion (optional, for debugging)

Step 2: Authentication
├── Check if auth_facebook.json exists
│   ├── YES → Load session (instant login)
│   └── NO → Open login page, wait for user to login
├── Verify login status (checks for redirect)
└── Save session for next time

Step 3: Navigate to Target
├── Get URL from config (FACEBOOK_MESSAGE_URL)
├── Navigate with retry logic (max 3 attempts)
└── Wait for page load (domcontentloaded)

Step 4: Smart Scroll & Extract
├── Initial wait (3 seconds for page load)
├── Close any login popups if they appear
└── Start smart scrolling loop:
    │
    ├── Extract all visible messages
    │   ├── Find elements: div[dir="auto"]
    │   ├── Get text content
    │   ├── Clean whitespace
    │   ├── Filter out UI elements (buttons, etc.)
    │   └── Deduplicate (using set)
    │
    ├── Check scroll position
    │   ├── Same as before? → Stuck detected!
    │   │   ├── Try 1: Scroll 2000px (bigger jump)
    │   │   └── Try 2: Force scroll to bottom (fail-safe)
    │   └── Different? → Normal scroll (1000px)
    │
    ├── Wait 1.5 seconds for new content
    │
    └── Repeat until:
        ├── Target messages reached (100) OR
        ├── No new messages for 5 scrolls OR
        └── Max scrolls reached (100)

Step 5: Display Results
├── Log all extracted messages
├── Show count and preview
└── Return messages list
```

### Phase 2: X/Twitter Posting (NOT IMPLEMENTED YET)

Will post extracted messages to Twitter/X

### Phase 3: Screenshot & Database (NOT IMPLEMENTED YET)

Will save messages and screenshots to database

---

## 💾 Data Storage - Current Status

### ⚠️ IMPORTANT: Messages Are NOT Saved Yet!

**Current Behavior:**
- Messages are extracted ✅
- Messages are logged to console ✅
- Messages are saved to log files ✅
- Messages are **NOT** saved to database ❌

**Where Messages Go Right Now:**

1. **Console Output** (you see them when script runs)
2. **Log Files** in `logs/relay_agent_YYYYMMDD.log`
   ```
   Example: logs/relay_agent_20251009.log
   ```

**Example Log Entry:**
```
2025-10-09 14:30:18,342 - __main__ - INFO - [1] con este frío si ando aguantando las mentiras de un precioso
2025-10-09 14:30:18,342 - __main__ - INFO - [2] Pss si lo funé, pero yaes mi corason d melón otraves
...
```

### 🔮 Future: Phase 3 Implementation

**What Phase 3 Will Do:**
- Save messages to SQLite database (`relay_agent.db`)
- Take screenshots of posts
- Store metadata (date, source URL, author, etc.)
- Check for duplicates before saving
- Auto-backup database

**Database Schema (Planned):**
```sql
CREATE TABLE messages (
    id INTEGER PRIMARY KEY,
    platform TEXT,           -- 'facebook'
    content TEXT,            -- The actual message
    source_url TEXT,         -- Facebook URL
    screenshot_path TEXT,    -- Path to screenshot
    extracted_at DATETIME,   -- When we scraped it
    posted_to_x BOOLEAN,     -- Did we post to X?
    unique_hash TEXT         -- For duplicate detection
);
```

---

## 📁 File Structure

```
alexis scrapper/
│
├── relay_agent.py                  # Main script - run this!
├── config.py                       # Configuration loader
├── .env                            # Your credentials (create this!)
│
├── facebook_auth.py               # Facebook login & session handling
├── facebook_extractor.py          # Message extraction & scrolling
├── exceptions.py                  # Custom error classes
│
├── utils/
│   ├── browser_config.py          # Browser setup (anti-detection)
│   └── selector_strategies.py    # CSS selectors for Facebook
│
├── auth_facebook.json             # 🔐 Session file (auto-generated)
├── auth_facebook_session.json     # Session metadata
│
├── logs/
│   └── relay_agent_YYYYMMDD.log   # Daily logs with extracted messages
│
├── screenshots/                    # Future: will store screenshots
├── backups/                        # Future: database backups
│
└── docs/                          # Documentation
    ├── PRD.md
    ├── Implementation/
    └── credenciales.txt
```

---

## ⚙️ Configuration Guide

### Step 1: Create `.env` File

Create a file called `.env` in the project root:

```env
# Facebook Credentials
FACEBOOK_EMAIL=your.email@example.com
FACEBOOK_PASSWORD=your_password_here
FACEBOOK_MESSAGE_URL=https://www.facebook.com/share/1E8ChgJj5b/?mibextid=wwXIfr

# Browser Settings
HEADLESS=false                    # true = invisible, false = visible browser
SLOW_MO=50                        # Milliseconds between actions (debugging)

# Timeouts (milliseconds)
DEFAULT_TIMEOUT=30000             # 30 seconds
NAVIGATION_TIMEOUT=30000          # 30 seconds
LOGIN_TIMEOUT=60000               # 60 seconds

# Logging
LOG_LEVEL=INFO                    # DEBUG, INFO, WARNING, ERROR

# Future: X/Twitter (not used yet)
X_EMAIL=
X_PASSWORD=

# Future: Database (not used yet)
DATABASE_PATH=relay_agent.db
SCREENSHOT_DIR=screenshots
```

### Step 2: Install Dependencies

```bash
pip install -r requirements.txt
```

### Step 3: Run the Script

```bash
# Test mode (extracts 100 messages)
python relay_agent.py --test-phase1

# Normal mode (uses config)
python relay_agent.py
```

---

## 🔧 Troubleshooting

### Problem: "Missing required configuration"

**Solution:** Check your `.env` file has:
- FACEBOOK_EMAIL
- FACEBOOK_PASSWORD  
- FACEBOOK_MESSAGE_URL

### Problem: Browser opens but doesn't login automatically

**Solution:** 
1. Delete `auth_facebook.json`
2. Run script again
3. Login manually when browser opens
4. Session will be saved for next time

### Problem: "Scroll position hasn't changed" warnings

**This is NORMAL!** The fail-safe is working:
- First warning: Tries bigger scroll (2000px)
- Second warning: Forces scroll to bottom
- This prevents infinite hanging

### Problem: Not extracting enough messages

**Solutions:**
1. Increase target: Change line 110 in `relay_agent.py`:
   ```python
   messages = extract_message_text(page, max_messages=200)  # Was 100
   ```

2. Check the page actually has that many messages

3. View browser (set `HEADLESS=false`) to see what's happening

### Problem: Session expires / keeps asking to login

**Cause:** Facebook sessions expire after ~24-48 hours

**Solution:** Just login again when prompted. The new session will be saved.

---

## 🚀 Quick Start Summary

1. **Create `.env`** with your Facebook credentials
2. **Run:** `python relay_agent.py --test-phase1`
3. **First time:** Browser opens, login manually
4. **Session saved:** Next time it's automatic
5. **Messages extracted:** Check logs or console output
6. **Database storage:** Coming in Phase 3!

---

## 📝 Notes

- **Security:** Never commit `auth_facebook.json` or `.env` to git
- **Rate Limiting:** Script has delays to avoid triggering Facebook anti-bot
- **Encoding:** Logs use UTF-8 for international characters (Spanish posts work!)
- **Fail-Safe:** Smart scrolling prevents hanging on Facebook's infinite scroll

---

## 🎯 Next Steps (Phase 3)

To implement database storage:
1. Create SQLite database schema
2. Add message saving function
3. Implement duplicate detection
4. Add screenshot capture
5. Create backup system

**Current Status:** Phase 1 Complete ✅ | Phase 2 Pending | Phase 3 Pending


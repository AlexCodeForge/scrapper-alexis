# Quick Reference Guide

## 🎯 What This Does

**Scrapes Facebook posts/messages → Currently saves to logs → (Future: Database & Twitter posting)**

---

## 🔑 Sessions & Auth - ELI5 (Explain Like I'm 5)

### How Sessions Work

**Without Sessions:**
```
You → Login → Do stuff → Close browser
Next time → Login AGAIN → Do stuff → Close browser
Next time → Login AGAIN → Do stuff... (annoying!)
```

**With Sessions (What We Have):**
```
First time:
You → Login → System saves "proof you logged in" → Do stuff → Close

Next time:
You → System uses "proof" → Already logged in! → Do stuff → Close

Next time:
You → Already logged in! → Do stuff → Close
```

### The Files

**`auth_facebook.json`** = Your "proof of login" (cookies & tokens)
- Think of it like a **movie ticket** that gets you in without buying again
- Created automatically after first login
- Lasts 24-48 hours before expiring

**`auth_facebook_session.json`** = Just a note saying "when you got the ticket"
- Metadata only
- Not critical

---

## 📍 Where Are Messages Stored?

### Current Reality (Phase 1):

#### ✅ Messages ARE saved in:
1. **Log files:** `logs/relay_agent_YYYYMMDD.log`
   - One file per day
   - Contains EVERYTHING the script does
   - Messages are there but mixed with other logs
   - **Encoding:** UTF-8 (Spanish characters work!)

2. **Console output** (terminal/screen)
   - You see them as script runs
   - Disappear when you close terminal

#### ❌ Messages ARE NOT saved in:
- Database (Phase 3 not implemented yet)
- JSON files (not implemented)
- CSV files (not implemented)
- Text files (not implemented)

### To View Extracted Messages:

**Option 1: Check Today's Log**
```bash
# Windows
type logs\relay_agent_20251009.log | findstr "INFO - \["

# Linux/Mac
cat logs/relay_agent_20251009.log | grep "INFO - \["
```

**Option 2: Open Log File**
```
logs/relay_agent_YYYYMMDD.log
Search for: "[OK] Successfully extracted"
```

**Example Log Content:**
```
2025-10-09 14:30:18,342 - __main__ - INFO - [1] con este frío si ando aguantando...
2025-10-09 14:30:18,342 - __main__ - INFO - [2] Pss si lo funé, pero yaes mi...
2025-10-09 14:30:18,342 - __main__ - INFO - [3] como que chupeton gfa, si en estas...
```

---

## 🚀 Running the Script

### First Time Ever:

1. **Setup credentials:**
   ```bash
   # Create .env file with:
   FACEBOOK_EMAIL=your@email.com
   FACEBOOK_PASSWORD=yourpassword
   FACEBOOK_MESSAGE_URL=https://facebook.com/...
   HEADLESS=false
   ```

2. **Run script:**
   ```bash
   python relay_agent.py --test-phase1
   ```

3. **What happens:**
   - Browser opens (you can see it)
   - Goes to Facebook login
   - **YOU login manually** (type email/password)
   - Script sees you logged in
   - **Saves session** → `auth_facebook.json` created
   - Navigates to target page
   - Scrolls and extracts messages
   - Shows results in console
   - Saves to log file

### Every Time After:

1. **Run script:**
   ```bash
   python relay_agent.py --test-phase1
   ```

2. **What happens:**
   - Browser opens
   - **Auto-login using saved session** (instant!)
   - Navigates to target page
   - Scrolls and extracts messages
   - Shows results
   - Done!

**No manual login needed!** (until session expires)

---

## 🔄 The Complete Flow

```
┌─────────────────────────────────────────────┐
│  1. START: python relay_agent.py            │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
         ┌─────────────────────┐
         │ 2. Check session    │
         │    auth_facebook.json│
         └─────────┬───────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
        ▼                     ▼
   ┌─────────┐          ┌──────────────┐
   │ EXISTS  │          │ NOT EXISTS   │
   │         │          │              │
   │ Load &  │          │ Open browser │
   │ use it  │          │ You login    │
   │         │          │ Save session │
   └────┬────┘          └──────┬───────┘
        │                      │
        └──────────┬───────────┘
                   │
                   ▼
         ┌─────────────────────┐
         │ 3. Navigate to page │
         │    (Facebook URL)    │
         └──────────┬──────────┘
                   │
                   ▼
         ┌─────────────────────────────┐
         │ 4. Scroll & Extract         │
         │    - Smart scroll (1000px)  │
         │    - Fail-safe (if stuck)   │
         │    - Extract text           │
         │    - Deduplicate            │
         │    - Repeat until 100 msgs  │
         └──────────┬──────────────────┘
                   │
                   ▼
         ┌──────────────────────────────┐
         │ 5. Save Results              │
         │    ✅ Console (display)      │
         │    ✅ Log file (permanent)   │
         │    ❌ Database (Phase 3)     │
         └──────────────────────────────┘
```

---

## 🛠️ Common Commands

### Run test (extract 100 messages):
```bash
python relay_agent.py --test-phase1
```

### Reset session (force new login):
```bash
# Delete session file
rm auth_facebook.json          # Linux/Mac
del auth_facebook.json         # Windows

# Then run again
python relay_agent.py --test-phase1
```

### Check today's extracted messages:
```bash
# View log file
cat logs/relay_agent_20251009.log          # Linux/Mac
type logs\relay_agent_20251009.log         # Windows
```

### Change target messages count:
Edit `relay_agent.py` line 110:
```python
# Change from:
messages = extract_message_text(page, max_messages=100)

# To:
messages = extract_message_text(page, max_messages=200)
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Missing required configuration" | Check `.env` has FACEBOOK_EMAIL, FACEBOOK_PASSWORD, FACEBOOK_MESSAGE_URL |
| Browser doesn't auto-login | Delete `auth_facebook.json`, run again, login manually |
| "Scroll position hasn't changed" | **This is normal!** Fail-safe is working to unstick the page |
| Session expired / asks to login again | Facebook sessions expire every 1-2 days. Just login again. |
| Can't find extracted messages | Check `logs/relay_agent_YYYYMMDD.log` file |
| Special characters look weird (�) | Log file is UTF-8. Open with editor that supports UTF-8 |

---

## 📊 What Gets Logged

Every run creates detailed logs:

```
logs/relay_agent_20251009.log  ← Today's log

Contains:
├── Browser launch info
├── Session loading/login
├── Navigation steps
├── Scroll attempts
├── Fail-safe activations
├── Extracted messages (numbered)
└── Final count
```

**Log Format:**
```
2025-10-09 14:30:18,342 - MODULE - LEVEL - MESSAGE
     │                     │        │       │
     │                     │        │       └─ The actual info
     │                     │        └───────── INFO/WARNING/ERROR
     │                     └────────────────── Which file it's from
     └──────────────────────────────────────── Timestamp
```

---

## ⚡ Quick Answers

**Q: Where are my messages?**  
A: In `logs/relay_agent_YYYYMMDD.log` (today's date)

**Q: Do I need to login every time?**  
A: No! Only first time, or when session expires (~1-2 days)

**Q: How does it remember my login?**  
A: Saves cookies/tokens to `auth_facebook.json`

**Q: Is it safe?**  
A: Yes, but NEVER share `auth_facebook.json` or `.env` files

**Q: Why "scroll position hasn't changed"?**  
A: Facebook's infinite scroll gets stuck. Fail-safe forces it to continue. This is GOOD!

**Q: Can I save to database?**  
A: Not yet! Phase 3 will add database storage

**Q: Can I change target URL?**  
A: Yes, edit FACEBOOK_MESSAGE_URL in `.env`

**Q: How many messages can it extract?**  
A: Default 100, but you can change it (edit relay_agent.py line 110)

---

## 🎯 Files You Care About

| File | What It Is | You Should... |
|------|------------|---------------|
| `.env` | Your credentials | Create it / Edit it |
| `relay_agent.py` | Main script | Run it |
| `auth_facebook.json` | Session file | Let script create it / Never share it |
| `logs/relay_agent_*.log` | Message storage | Read it to see extracted messages |
| `config.py` | Settings loader | Usually don't touch |
| `facebook_extractor.py` | Scraping logic | Advanced: edit to change extraction |

---

## 🔮 Future Phases

**Phase 2:** Post to X/Twitter  
**Phase 3:** Save to database + screenshots  

**Current:** Phase 1 Complete ✅ (Facebook extraction with sessions)


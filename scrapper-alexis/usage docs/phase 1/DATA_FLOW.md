# Data Flow & Storage Map

## 🗺️ Where Does Everything Go?

### Current System (Phase 1)

```
┌─────────────────────────────────────────────────────────────────┐
│                     FACEBOOK PAGE                               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Post 1: "con este frío si ando aguantando..."           │  │
│  │  Post 2: "Pss si lo funé, pero yaes mi corason..."       │  │
│  │  Post 3: "como que chupeton gfa..."                      │  │
│  │  ... (scrolls and loads more)                            │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               │ ① Playwright Browser
                               │    (extract text)
                               ▼
                    ┌──────────────────────┐
                    │  PYTHON SCRIPT       │
                    │  facebook_extractor  │
                    │                      │
                    │  messages = [        │
                    │    "con este frío...",│
                    │    "Pss si lo funé...",│
                    │    ...               │
                    │  ]                   │
                    └──────────┬───────────┘
                               │
                               │ ② Output goes to:
                               │
              ┌────────────────┼────────────────┐
              │                │                │
              ▼                ▼                ▼
    ┌─────────────────┐  ┌──────────────┐  ┌─────────────────┐
    │   CONSOLE       │  │  LOG FILE    │  │  MEMORY ONLY    │
    │   (Terminal)    │  │  (Disk)      │  │  (Lost on exit) │
    │                 │  │              │  │                 │
    │ [1] con este... │  │ logs/        │  │ Python variable │
    │ [2] Pss si...   │  │ relay_agent_ │  │ messages = [...] │
    │ [3] como que... │  │ 20251009.log │  │                 │
    │                 │  │              │  │ ⚠️ NOT SAVED    │
    │ ✅ Visible      │  │ ✅ Permanent │  │ ❌ Temporary    │
    │ ❌ Not saved    │  │ ✅ Searchable│  │                 │
    └─────────────────┘  └──────────────┘  └─────────────────┘
```

### Future System (Phase 3 - Not Implemented Yet)

```
                    ┌──────────────────────┐
                    │  PYTHON SCRIPT       │
                    │                      │
                    │  messages = [...]    │
                    └──────────┬───────────┘
                               │
                               │ ② Output goes to:
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
┌───────────────┐     ┌─────────────────┐    ┌──────────────────┐
│   CONSOLE     │     │  DATABASE       │    │  SCREENSHOTS     │
│   (Terminal)  │     │  (SQLite)       │    │  (PNG files)     │
│               │     │                 │    │                  │
│ [1] con...    │     │ relay_agent.db  │    │ screenshots/     │
│ [2] Pss...    │     │                 │    │ msg_001.png      │
│               │     │ TABLE: messages │    │ msg_002.png      │
│ ✅ Visible    │     │ ├─ id           │    │ ...              │
│ ❌ Not saved  │     │ ├─ content      │    │                  │
└───────────────┘     │ ├─ source_url   │    │ ✅ Visual proof  │
                      │ ├─ extracted_at │    │ ✅ Permanent     │
                      │ ├─ screenshot   │    └──────────────────┘
                      │ └─ posted_to_x  │
                      │                 │
                      │ ✅ Permanent    │
                      │ ✅ Queryable    │
                      │ ✅ Structured   │
                      └─────────────────┘
```

---

## 📁 File Storage Map

### What Files Are Created & Where

```
alexis scrapper/
│
├── 📄 .env                                  ← YOU create this
│   └── Contains: Credentials (email, password, URLs)
│
├── 🔐 auth_facebook.json                    ← AUTO-CREATED on first login
│   └── Contains: Session cookies, tokens (your "login pass")
│   └── When: After you manually login first time
│   └── Expires: 24-48 hours
│
├── 📝 auth_facebook_session.json            ← AUTO-CREATED (metadata)
│   └── Contains: Just timestamp of when you logged in
│   └── When: Same time as auth_facebook.json
│
├── 📂 logs/
│   ├── relay_agent_20251009.log            ← AUTO-CREATED daily
│   ├── relay_agent_20251010.log            ← NEW FILE each day
│   └── relay_agent_20251011.log
│       │
│       └── Contains: EVERYTHING including extracted messages
│           Format: timestamp - module - level - message
│           Encoding: UTF-8 (supports Spanish/international chars)
│           ✅ This is where your messages ARE saved right now!
│
├── 📂 screenshots/                          ← EMPTY (Phase 3)
│   └── (future: will have .png files)
│
└── 📂 backups/                              ← EMPTY (Phase 3)
    └── (future: will have database backups)
```

---

## 🔄 Session Files Explained

### Authentication Flow

```
FIRST TIME:
──────────────────────────────────────────────────────
1. No auth_facebook.json exists
2. Script opens browser
3. YOU type email/password on Facebook
4. Facebook says "OK, you're in" → gives cookies/tokens
5. Script saves cookies → auth_facebook.json
6. Script saves timestamp → auth_facebook_session.json

RESULT:
✅ auth_facebook.json (1.2 KB - contains session data)
✅ auth_facebook_session.json (60 bytes - contains timestamp)


NEXT TIME (within 24-48 hours):
──────────────────────────────────────────────────────
1. auth_facebook.json EXISTS
2. Script loads cookies from file
3. Browser uses cookies → Facebook says "I remember you!"
4. Instantly logged in (no email/password needed)

RESULT:
✅ Auto-login (no manual interaction)
✅ Same session files reused


AFTER SESSION EXPIRES (2+ days):
──────────────────────────────────────────────────────
1. auth_facebook.json exists BUT cookies expired
2. Script tries to use cookies → Facebook says "Session expired"
3. Script detects NOT logged in
4. Opens browser for manual login again
5. Saves new session

RESULT:
🔄 auth_facebook.json (updated with new session)
🔄 auth_facebook_session.json (updated timestamp)
```

---

## 💾 Message Storage Detail

### Current Storage (Phase 1)

#### Log File Content Example:
```
logs/relay_agent_20251009.log
─────────────────────────────────────────────────────────────────
2025-10-09 14:30:18,338 - facebook_extractor - INFO - === Scroll & Extract Summary ===
2025-10-09 14:30:18,338 - facebook_extractor - INFO - Total scrolls: 14
2025-10-09 14:30:18,338 - facebook_extractor - INFO - Unique messages extracted: 104
2025-10-09 14:30:18,338 - facebook_extractor - INFO - 
=== Extracted Messages ===
2025-10-09 14:30:18,339 - facebook_extractor - INFO -   [OK] [1] con este frío si ando aguantando las mentiras de un precioso
2025-10-09 14:30:18,339 - facebook_extractor - INFO -   [OK] [2] Pss si lo funé, pero yaes mi corason d melón otraves
2025-10-09 14:30:18,339 - facebook_extractor - INFO -   [OK] [3] como que chupeton gfa, si en estas fechas ya salen los wampiros
...
2025-10-09 14:30:18,341 - facebook_extractor - INFO - 
[OK] Successfully extracted 104 unique messages
```

**How to Extract Messages from Log:**

```bash
# Linux/Mac
grep "\[OK\] \[" logs/relay_agent_20251009.log

# Windows PowerShell
Select-String -Path "logs\relay_agent_20251009.log" -Pattern "\[OK\] \["

# Python script to extract
import re

with open('logs/relay_agent_20251009.log', 'r', encoding='utf-8') as f:
    for line in f:
        match = re.search(r'\[OK\] \[\d+\] (.+)$', line)
        if match:
            print(match.group(1))
```

### Future Storage (Phase 3 - Planned)

#### Database Schema:
```sql
CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL,              -- 'facebook'
    content TEXT NOT NULL,               -- The actual message text
    source_url TEXT NOT NULL,            -- Where it came from
    author TEXT,                         -- Who posted it (if available)
    extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    screenshot_path TEXT,                -- screenshots/msg_001.png
    posted_to_x BOOLEAN DEFAULT 0,       -- Was it posted to Twitter?
    posted_at DATETIME,                  -- When posted to Twitter
    unique_hash TEXT UNIQUE,             -- For duplicate detection
    metadata JSON                        -- Extra data (likes, shares, etc.)
);
```

#### Screenshot Naming:
```
screenshots/
├── fb_20251009_143018_001.png    ← Facebook, date, time, index
├── fb_20251009_143018_002.png
├── fb_20251009_143018_003.png
...
```

---

## 🎯 Quick Answers

### Q: Where are my extracted messages RIGHT NOW?
**A:** In `logs/relay_agent_YYYYMMDD.log` (example: `logs/relay_agent_20251009.log`)

### Q: How do I find messages in the log?
**A:** Search for lines with `[OK] [1]`, `[OK] [2]`, etc.

### Q: Are messages saved permanently?
**A:** ✅ YES in log files (permanent)  
❌ NO in database (Phase 3 not implemented)

### Q: What is auth_facebook.json?
**A:** Your saved login session (cookies + tokens). Like a digital keycard.

### Q: Why does auth_facebook.json expire?
**A:** Facebook security. Sessions last 24-48 hours, then you need to login again.

### Q: Can I backup my extracted messages?
**A:** YES! Just copy the log files:
```bash
cp logs/relay_agent_20251009.log backups/
```

### Q: What happens if I delete auth_facebook.json?
**A:** Next run will require manual login again (creates new session file)

### Q: Do I need auth_facebook_session.json?
**A:** Not critical. It's just metadata. The important one is `auth_facebook.json`

---

## 🔮 Roadmap

### Phase 1 (Current): ✅ COMPLETE
- Extract messages from Facebook
- Save to log files
- Session management (auto-login)

### Phase 2 (Next): ⏳ PENDING
- Post messages to X/Twitter
- Track which messages were posted

### Phase 3 (Future): ⏳ PENDING
- SQLite database storage
- Screenshot capture
- Duplicate detection
- Auto-backup system

---

## 📊 Data Lifecycle

```
EXTRACTION → PROCESSING → STORAGE (Current) → POSTING (Future)
    │            │              │                   │
    │            │              │                   │
    ▼            ▼              ▼                   ▼
Facebook → Clean text → logs/ → (database) → (X/Twitter)
   |            |         |          |              |
   |            |         |          |              |
Scrape      Filter UI   Save    Store with     Post &
with        elements    to log  screenshot     track
Playwright  & dedupe    file    (Phase 3)      (Phase 2)
```

---

**Last Updated:** October 9, 2025  
**Current Phase:** Phase 1 Complete  
**Messages Stored In:** Log files (`logs/relay_agent_*.log`)


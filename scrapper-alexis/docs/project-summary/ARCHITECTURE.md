# System Architecture

## Architecture Pattern
**Modular Monolith** - Single Python application with clear module separation

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     USER INTERFACE                          │
│              (Windows Batch Scripts)                        │
│  run_facebook_flow.bat | run_twitter_flow.bat |            │
│          run_image_generation.bat                           │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────┴────────────────────────────────────────┐
│                   MAIN SCRIPTS                              │
│  relay_agent.py | twitter_post.py | generate_images.py     │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────┴────────────────────────────────────────┐
│                  CORE MODULES                               │
│  database.py | profile_manager.py | message_deduplicator   │
│                debug_helper.py                              │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────┴────────────────────────────────────────┐
│              PLATFORM MODULES                               │
│    facebook/           │         twitter/                   │
│  - facebook_auth.py    │    - twitter_auth.py              │
│  - facebook_extractor  │    - twitter_post.py              │
│                        │    - tweet_template.html          │
└────────────────────────┴─────────────────────────────────────┘
                     │
┌────────────────────┴────────────────────────────────────────┐
│               EXTERNAL SERVICES                             │
│    Facebook (via Playwright) | Twitter (via Proxy)         │
└─────────────────────────────────────────────────────────────┘
```

## Module Breakdown

### **Core Modules** (`core/`)
- **`database.py`**: SQLite operations, schema management, CRUD
- **`profile_manager.py`**: Profile sync from config to database
- **`message_deduplicator.py`**: SHA256 hashing, duplicate detection
- **`debug_helper.py`**: Logging, screenshots, session management

### **Platform Modules**

#### Facebook (`facebook/`)
- **`facebook_auth.py`**: Login flow, session persistence
- **`facebook_extractor.py`**: Message extraction, scrolling logic

#### Twitter (`twitter/`)
- **`twitter_auth.py`**: Login flow, session persistence
- **`twitter_post.py`**: Tweet posting, URL extraction
- **`tweet_template.html`**: HTML template for image generation

### **Main Scripts** (Root)
- **`relay_agent.py`**: Facebook scraping orchestrator
- **`generate_message_images.py`**: Image generation orchestrator
- **`twitter/twitter_post.py`**: Has main() for standalone Twitter posting

### **Utilities** (`utils/`)
- **`browser_config.py`**: Playwright browser configuration
- **`selector_strategies.py`**: CSS selector strategies

## Data Flow

### Configuration Flow
```
copy.env → config.py → profile_manager.py → database.py
```

### Scraping Flow
```
relay_agent.py → facebook_auth.py → facebook_extractor.py → database.py
                                                           ↓
                                                    debug_helper.py
```

### Posting Flow
```
twitter_post.py → twitter_auth.py → Twitter API → database.py
                                                  ↓
                                            avatar/post URLs
```

### Image Generation Flow
```
generate_images.py → database.py (get messages)
                   ↓
                tweet_template.html → Playwright → PNG files
                   ↓
                database.py (update status)
```

## Key Design Decisions

### 1. **SQLite for State Management**
- Single-file database for portability
- No server setup required
- ACID compliance for data integrity

### 2. **Playwright for Browser Automation**
- Handles JavaScript-heavy sites (Facebook/Twitter)
- Built-in screenshot capabilities
- Session state management

### 3. **Session-Based Authentication**
- Saves cookies/storage state to JSON
- Avoids repeated logins
- Faster execution

### 4. **Hash-Based Deduplication**
- SHA256 prevents identical message re-scraping
- UNIQUE constraint at database level
- Fast lookup performance

### 5. **Proxy for Twitter**
- Required due to geo-restrictions
- Configured at browser launch
- Credentials in environment variables

### 6. **Modular Script Architecture**
- Each workflow is independent
- Can run separately or in sequence
- Easy to debug individual components

## Security Considerations
- Credentials stored in `copy.env` (not committed)
- Session files in `auth/` directory (gitignored)
- Proxy credentials in environment variables
- No plaintext passwords in code

## Scalability Notes
- **Current**: Single-threaded, sequential processing
- **Limitation**: One profile at a time, one post at a time
- **Future**: Could add async/parallel processing if needed
- **Database**: SQLite suitable for <100K messages

## Technology Stack
- **Language**: Python 3.10+
- **Browser Automation**: Playwright (Chromium)
- **Database**: SQLite3
- **Environment**: python-dotenv
- **HTTP**: requests (for avatar downloads)
- **OS**: Windows (batch scripts)


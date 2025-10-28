# Phase 1: Facebook Content Acquisition

## 🎯 Objective
Implement Facebook authentication, session management, and message content extraction using Playwright.

## 📋 Prerequisites
- Phase 0 completed
- Valid Facebook credentials in `.env`
- Target Facebook message URL available

## ⏱️ Estimated Time
2-3 hours

## 🏗️ Architecture Overview

### Component Structure
```
Phase 1 Components:
├── facebook_auth.py          # Authentication & session management
├── facebook_extractor.py     # Content extraction logic
└── utils/
    ├── browser_config.py     # Browser context configuration
    └── selector_strategies.py # Fallback selector patterns
```

### Data Flow
```
1. Check for saved session (auth_facebook.json)
   ├── Found → Load context with storage state
   └── Not found → Manual login flow
2. Navigate to target message URL
3. Wait for message content to load
4. Extract text using fallback selectors
5. Validate extracted content
6. Save session state for future use
```

## 🔧 Key Components

### 1. Browser Context Configuration
Anti-detection browser setup with:
- Realistic user agent
- Standard viewport (1920x1080)
- Locale and timezone settings
- Device emulation parameters

### 2. Authentication Flow
```python
if auth_file.exists():
    # Load saved session
    context = browser.new_context(storage_state='auth_facebook.json')
else:
    # Manual login with fallback selectors
    # Save session with IndexedDB support
    context.storage_state(path='auth_facebook.json', indexed_db=True)
```

### 3. Content Extraction
Multi-selector strategy:
- Primary: `div[role="article"]`
- Fallback: `div[data-ad-preview="message"]`
- Fallback: `.x1iorvi4.x1pi30zi`
- Fallback: `div[dir="auto"]`

## 📊 Database Schema
Not needed for Phase 1 (message text stored in memory).

## 🔐 Security Considerations
- **Session Storage**: Capture both cookies and session storage
- **IndexedDB**: Save IndexedDB data for auth tokens
- **Rate Limiting**: Human-like delays (500-1500ms)
- **CAPTCHA Handling**: Manual intervention system
- **Anti-Detection**: Realistic browser fingerprint

## 📁 File Structure Updates
```
project/
├── facebook_auth.py              # NEW
├── facebook_extractor.py         # NEW
├── utils/                        # NEW
│   ├── __init__.py
│   ├── browser_config.py
│   └── selector_strategies.py
├── auth_facebook.json            # AUTO-GENERATED
├── auth_facebook_session.json   # AUTO-GENERATED
└── relay_agent.py                # UPDATED with Phase 1
```

## ✅ Acceptance Criteria
- [ ] Browser launches with anti-detection configuration
- [ ] Session state loads from file if available
- [ ] Manual login works with fallback selectors
- [ ] Session state saves with IndexedDB support
- [ ] Navigation to message URL succeeds
- [ ] Message content extracts correctly
- [ ] Extraction validates (non-empty text)
- [ ] Human-like delays implemented
- [ ] Error handling for all failure modes
- [ ] Comprehensive logging of all actions

## 🚧 Known Challenges
1. **Facebook DOM Changes**: Implement multiple fallback selectors
2. **CAPTCHA/2FA**: Manual intervention required - pause execution
3. **Session Expiration**: Handle re-authentication gracefully
4. **Network Issues**: Retry logic with exponential backoff


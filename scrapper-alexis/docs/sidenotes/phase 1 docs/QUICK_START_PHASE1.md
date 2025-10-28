# Quick Start - Phase 1 Facebook Scraper

## TL;DR - How It Works Now

### The New Flow (Reliable & Robust)

```
1. Start Script
   ↓
2. Check if auth_facebook.json exists
   ↓
3. Navigate to Facebook Login Page
   ↓
4. Check Login Status:
   - IF redirected to home → Already logged in ✅
   - IF login form visible → Need to login
   ↓
5. Login (if needed)
   - Enter credentials
   - Click login
   - Wait for success
   - Save session
   - Verify login worked
   ↓
6. Navigate to Target Page
   ↓
7. Extract 10 Messages
   - Scroll to load content
   - Find message elements
   - Filter & deduplicate
   - Return results
```

---

## Why This Approach?

### Problem Before:
- ❌ Assumed saved session = logged in
- ❌ Facebook showed popups during extraction
- ❌ Had to handle login mid-extraction
- ❌ Context destroyed errors

### Solution Now:
- ✅ **Verify** login status first
- ✅ **Authenticate** before extraction
- ✅ **Navigate** when fully logged in
- ✅ **Extract** without interruptions

---

## Session Storage - Q&A

### Q: Are we storing sessions?
**A: Yes!** Sessions are saved to `auth_facebook.json`

### Q: Why do we need to login every time?
**A: Facebook's Security** - They invalidate sessions between browser launches. This is normal for automation.

### Q: Is this bad?
**A: No!** It's actually better because:
- No stale sessions causing errors
- Always fresh authentication
- More reliable extraction
- Clear error messages if login fails

---

## What Was Fixed

### 1. Login Verification ✅
- Added `verify_logged_in()` function
- Checks actual page state, not just file existence
- Verifies after authentication

### 2. Error Handling ✅
- UTF-8 encoding for Spanish text
- Removed emoji characters (Windows compatibility)
- Non-critical session storage errors handled gracefully

### 3. Logging ✅
- Step-by-step progress
- Extraction statistics
- Better debugging info

### 4. Popup Handling ✅
- Automatic close if detected
- Non-blocking (won't fail if no popup)
- Cleaner extraction process

---

## Running the Script

```bash
python relay_agent.py
```

### What to Expect:

```
✅ Configuration validated
✅ Browser launched
✅ Checking login status...
   → May need to login (Facebook security)
✅ Logged in successfully
✅ Navigating to target page
✅ Extracting 10 messages
✅ Complete!
```

---

## Logs Location

- **File**: `logs/relay_agent_YYYYMMDD.log`
- **Encoding**: UTF-8 (proper Spanish characters)
- **Console**: May show `�` for accents (Windows limitation)

---

## Configuration

Make sure `.env` has:
```env
FACEBOOK_EMAIL=your_email
FACEBOOK_PASSWORD=your_password
FACEBOOK_MESSAGE_URL=your_target_url
```

---

## Next Phase Ready

Phase 1 is **COMPLETE** and **STABLE**. Ready for:
- Phase 2: Twitter posting
- Phase 3: Screenshots & Database
- Phase 4: Testing & hardening

---

## Troubleshooting

### Issue: Script keeps logging in
**Solution**: This is normal! Facebook invalidates sessions for security.

### Issue: Extraction fails
**Check**: 
1. Credentials correct?
2. Target URL accessible?
3. Check logs for details

### Issue: Characters look weird in console
**Solution**: Normal on Windows. Check log file for proper UTF-8.

---

## Success Indicators

When everything works, you'll see:
```
[OK] Login verified successfully
[OK] Navigation successful
[OK] Successfully extracted 10 unique messages
[OK] PHASE 1 COMPLETE
```

---

## Key Files

- `relay_agent.py` - Main script
- `facebook_auth.py` - Login logic
- `facebook_extractor.py` - Content extraction
- `auth_facebook.json` - Session storage (auto-generated)
- `logs/` - Execution logs


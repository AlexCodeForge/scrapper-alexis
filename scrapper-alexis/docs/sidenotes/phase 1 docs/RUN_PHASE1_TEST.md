# Quick Start: Phase 1 Testing

## Prerequisites ✅

1. **Environment Setup** (Already Complete from Phase 0)
   - ✅ Virtual environment activated
   - ✅ Dependencies installed
   - ✅ Playwright browsers installed

2. **Configuration Required** ⚠️
   - Update `.env` with Facebook credentials
   - Add target Facebook message URL

---

## Step 1: Configure Credentials

Edit your `.env` file:

```bash
# Facebook Credentials (REQUIRED)
FACEBOOK_EMAIL=your_email@example.com
FACEBOOK_PASSWORD=your_facebook_password

# Target Message URL (REQUIRED)
# Get this by opening a Facebook message and copying the URL
FACEBOOK_MESSAGE_URL=https://www.facebook.com/messages/t/123456789

# Browser Settings (Recommended for first test)
HEADLESS=false        # Keep false to see what's happening
SLOW_MO=50           # Adds 50ms delay for debugging
```

### How to Get Facebook Message URL:
1. Open Facebook in browser
2. Go to Messages/Messenger
3. Open the specific conversation/message you want to scrape
4. Copy the URL from address bar (should look like `https://www.facebook.com/messages/t/...` or `https://www.messenger.com/t/...`)

---

## Step 2: First Run (Manual Login)

**Important:** Delete any existing auth files first:
```bash
rm -f auth_facebook*.json  # Linux/Mac
# or
del auth_facebook*.json    # Windows
```

**Run the agent:**
```bash
python relay_agent.py
```

### What to Expect:

1. **Browser Opens** (visible window)
2. **Navigates to Facebook login**
3. **Fills in email and password** (you'll see typing)
4. **Clicks login button**
5. **May pause for 2FA/CAPTCHA:**
   - If prompted, complete 2FA in browser window
   - Or solve CAPTCHA if shown
   - Press `Ctrl+C` in terminal when done
6. **Saves authentication files:**
   - `auth_facebook.json`
   - `auth_facebook_session.json`
7. **Navigates to your message URL**
8. **Extracts message text**
9. **Shows preview in logs**

### Success Indicators:
```
✅ PHASE 1 COMPLETE
Extracted XXX characters
Preview: [your message text]...
```

---

## Step 3: Second Run (Cached Session)

**Without changing anything, run again:**
```bash
python relay_agent.py
```

### What to Expect:

1. **Browser opens**
2. **Loads saved session** (much faster)
3. **Skips login completely**
4. **Goes directly to message**
5. **Extracts text**
6. **Completes in ~10-15 seconds** (vs 30-45 for first run)

---

## Step 4: Review Logs

Check the log file:
```bash
# Linux/Mac
cat logs/relay_agent_$(date +%Y%m%d).log

# Windows
type logs\relay_agent_[TODAY'S DATE].log
```

Look for:
- ✅ "PHASE 1 COMPLETE"
- ✅ Message preview
- ✅ No errors

---

## Troubleshooting

### Issue: "Could not find email input field"
**Cause:** Facebook login page selectors changed  
**Fix:** Use Playwright MCP to inspect and update selectors

### Issue: "Could not locate message content"
**Cause:** Message selectors don't match your message type  
**Solution:**
1. Check if message URL is accessible when logged in manually
2. Use Playwright MCP to inspect the message page
3. Update `MESSAGE_SELECTORS` in `utils/selector_strategies.py`

### Issue: Script hangs during login
**Cause:** CAPTCHA or 2FA required  
**Fix:** Complete in browser window, then press `Ctrl+C`

### Issue: "Configuration error: FACEBOOK_MESSAGE_URL is required"
**Cause:** Missing message URL in `.env`  
**Fix:** Add `FACEBOOK_MESSAGE_URL=https://...` to `.env`

---

## Expected Output (Success)

```
======================================================================
PLAYWRIGHT SOCIAL CONTENT RELAY AGENT
======================================================================
Configuration validated successfully

=== Browser Launch ===
Browser launched (headless=False)

======================================================================
PHASE 1: FACEBOOK CONTENT ACQUISITION
======================================================================
ℹ️ No Facebook auth state found
No saved session - manual login required
Browser context created with anti-detection settings

--- Facebook Authentication ---
=== Starting Facebook Login ===
Navigating to Facebook login page...
Entering email address...
Waiting 1234ms (human-like delay)...
Entering password...
Waiting 876ms (human-like delay)...
Clicking login button...
Waiting for authentication to complete...
✅ Network idle - login appears successful
✅ Facebook login successful
Saving Facebook authentication state...
✅ Saved storage state to auth_facebook.json
✅ Saved session storage to auth_facebook_session.json
✅ Authentication complete and saved

--- Message Navigation ---
=== Navigating to Facebook Message ===
URL: https://www.facebook.com/messages/t/123456789
Navigation attempt 1/3...
✅ Navigation successful (attempt 1)

--- Content Extraction ---
=== Extracting Message Content ===
Waiting for message content to load...
✅ Found element with selector: div[role="article"]
✅ Message extracted successfully
Message length: 142 characters
Message preview: This is the message content from Facebook...

======================================================================
✅ PHASE 1 COMPLETE
Extracted 142 characters
Preview: This is the message content from Facebook...
======================================================================

⚠️ Phase 2 (X/Twitter Posting) - Not yet implemented
⚠️ Phase 3 (Screenshot & Database) - Not yet implemented

======================================================================
RELAY AGENT EXECUTION COMPLETE
======================================================================

Closing browser...
Browser closed
```

---

## Next Steps After Success

1. ✅ Verify `auth_facebook.json` and `auth_facebook_session.json` were created
2. ✅ Run second time to confirm session caching works
3. ✅ Document which message selector worked (in logs)
4. ✅ Mark Phase 1 complete
5. ➡️ Proceed to Phase 2: X/Twitter Posting

---

## Quick Commands Reference

```bash
# First run (delete auth, then run)
rm -f auth_facebook*.json && python relay_agent.py

# Check logs
tail -f logs/relay_agent_*.log

# Verify config
python -c "import config; config.validate_config(); print('Config OK')"

# Clean all auth files
rm -f auth_*.json

# List what was created
ls -la auth_* screenshots/ logs/
```

---

**Ready to test?** Follow Steps 1-4 above! 🚀



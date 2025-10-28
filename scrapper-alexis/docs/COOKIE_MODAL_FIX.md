# Cookie Modal Handling Fix

## Problem Identified

Based on logs from `relay_agent_20251010.log`, the Facebook login was failing because the **cookie consent modal was blocking the login button click**:

```
<span dir="auto" ...>Essential cookies: These cookies are required to …</span> 
from <div class="_10 uiLayer _4-hy _3qw" data-testid="cookie-policy-manage-dialog">…</div> 
subtree intercepts pointer events
```

### Root Cause
1. Cookie modal appears on the login page
2. Previous code tried to close it by clicking "Allow" button
3. **The modal didn't actually close** (verification was missing)
4. When trying to click the login button, Playwright retried 57+ times but the modal kept blocking it
5. Eventually timed out after 30 seconds

## Solution Implemented

### Strategy 1: Enhanced Cookie Button Detection
Added comprehensive button selectors based on Playwright best practices:

```python
accept_buttons = [
    # Strategy 1: "Allow essential and optional cookies" (full consent)
    'button:has-text("Allow essential and optional cookies")',
    'button:has-text("Permitir cookies esenciales y opcionales")',
    
    # Strategy 2: "Decline optional cookies" (minimal consent - USER SUGGESTION!)
    'button:has-text("Decline optional cookies")',
    'button:has-text("Rechazar cookies opcionales")',
    
    # Strategy 3: Generic "Allow" buttons
    'div[role="button"]:has-text("Allow essential and optional")',
    'div[role="button"]:has-text("Decline optional")',
    
    # Strategy 4: Old-style selectors (fallback)
    'button[data-cookiebanner="accept_button"]',
    'button:has-text("Allow all")',
    ...
]
```

### Strategy 2: Verify Modal Actually Closes
After clicking any button, we now **verify the modal is gone**:

```python
btn.click(force=True)
page.wait_for_timeout(2000)

# VERIFY the modal actually closed
if dialog.is_visible(timeout=1000):
    logger.warning(f"⚠️ Cookie modal still visible after clicking {btn_selector}")
    button_clicked = False
    continue  # Try next button
else:
    logger.info("[OK] ✅ Cookie modal successfully closed")
```

### Strategy 3: Double-Check Before Login Click
Added a **second cookie modal check** right before clicking the login button:

```python
# CRITICAL: Check AGAIN for cookie modal right before clicking login button
cookie_modal = page.locator('div[data-testid="cookie-policy-manage-dialog"]').first
if cookie_modal.is_visible(timeout=1000):
    logger.warning("⚠️ Cookie modal STILL VISIBLE - attempting to close again...")
    # Try to close it again with the most reliable buttons
```

This ensures that even if the modal reappears or wasn't fully closed, we handle it.

## Key Changes to `facebook_auth.py`

### Location 1: Initial Cookie Handling (Lines 165-243)
- **Before**: Simple button click without verification
- **After**: 
  - Try multiple button selectors (including "Decline optional cookies")
  - Verify modal actually closes
  - Log detailed debugging information
  - Take screenshots for each step

### Location 2: Pre-Login Cookie Check (Lines 290-330)
- **New**: Added second cookie modal check right before login button click
- Ensures modal is definitely gone before attempting login
- Provides critical error logging if modal can't be closed

## Testing Validation with Context7/Playwright Docs

Based on Playwright Python documentation:
1. ✅ Using `has-text()` pseudo-selector for button detection (recommended)
2. ✅ Using `force=True` for clicking elements that might be behind overlays
3. ✅ Using `is_visible()` for verification (best practice)
4. ✅ Using proper timeout strategies with explicit waits
5. ✅ Logging and screenshots for debugging (essential for headless)

## Expected Behavior

### Scenario 1: Modal Appears Initially
```
[INFO] Cookie dialog detected: div[data-testid="cookie-policy-manage-dialog"]
[INFO] Found cookie button: button:has-text("Decline optional cookies")
[OK] Clicked cookie button: button:has-text("Decline optional cookies")
[OK] ✅ Cookie modal successfully closed and no longer visible
[OK] ✅ No cookie modal blocking - safe to click login
```

### Scenario 2: Modal Persists After First Attempt
```
[INFO] Found cookie button: button:has-text("Allow")
[OK] Clicked cookie button: button:has-text("Allow")
⚠️ Cookie modal still visible after clicking button:has-text("Allow")
[INFO] Found cookie button: button:has-text("Decline optional cookies")
[OK] ✅ Cookie modal successfully closed
```

### Scenario 3: Modal Reappears Before Login Click
```
⚠️ Cookie modal STILL VISIBLE before login click - attempting to close again...
[INFO] Attempting to close modal with: button:has-text("Decline optional cookies")
[OK] ✅ Modal successfully closed with: button:has-text("Decline optional cookies")
```

## Next Steps

1. **Test on VPS**: Run the scraper to see if the cookie modal is properly handled
2. **Check debug screenshots**: Look for `01b_cookie_closed.png` and `05b_modal_finally_closed.png`
3. **Review logs**: Confirm modal is actually closing (should see ✅ messages)
4. **Iterate if needed**: If specific text variations are encountered, add them to the selectors

## User Suggestion Incorporated ✅

As suggested by the user:
> "maybe click the decline optional cookies button?"

This has been **implemented as Strategy 2** (lines 190-192) and is now one of the **primary** button selectors tried, right after "Allow essential and optional cookies".


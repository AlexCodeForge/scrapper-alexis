# Phase 1: Facebook Content Acquisition - Tasks

## Task 1: Create Browser Configuration Utility
**Priority:** CRITICAL  
**Estimated Time:** 30 minutes

### Steps:
1. Create `utils/browser_config.py`
2. Implement `create_browser_context()` function
3. Configure anti-detection parameters:
   - User agent from config
   - Viewport (1920x1080)
   - Locale and timezone
   - Device scale factor
4. Add support for loading storage state

### Code Template:
```python
# utils/browser_config.py
from playwright.sync_api import Browser, BrowserContext
from pathlib import Path
import config

def create_browser_context(
    browser: Browser,
    storage_state_path: str = None
) -> BrowserContext:
    """Create browser context with anti-detection configuration."""
    context_options = {
        'user_agent': config.USER_AGENT,
        'viewport': {'width': 1920, 'height': 1080},
        'locale': config.LOCALE,
        'timezone_id': config.TIMEZONE,
        'device_scale_factor': 1,
        'has_touch': False,
        'java_script_enabled': True
    }
    
    if storage_state_path and Path(storage_state_path).exists():
        context_options['storage_state'] = storage_state_path
    
    return browser.new_context(**context_options)
```

### Verification:
```python
from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context

with sync_playwright() as p:
    browser = p.chromium.launch()
    context = create_browser_context(browser)
    page = context.new_page()
    # Should have correct user agent
    user_agent = page.evaluate("navigator.userAgent")
    print(f"User Agent: {user_agent}")
```

---

## Task 2: Implement Selector Strategies
**Priority:** HIGH  
**Estimated Time:** 20 minutes

### Steps:
1. Create `utils/selector_strategies.py`
2. Define selector lists for:
   - Email input field
   - Password input field
   - Login button
   - Message content
3. Implement `try_selectors()` helper function

### Code Template:
```python
# utils/selector_strategies.py
from playwright.sync_api import Page, Locator
from typing import List, Optional

# Facebook Login Selectors
EMAIL_SELECTORS = [
    '#email',
    'input[name="email"]',
    'input[type="email"]',
    'input[autocomplete="username"]'
]

PASSWORD_SELECTORS = [
    '#pass',
    'input[name="pass"]',
    'input[type="password"]',
    'input[autocomplete="current-password"]'
]

LOGIN_BUTTON_SELECTORS = [
    'button[name="login"]',
    'button[type="submit"]',
    'input[type="submit"]'
]

# Facebook Message Selectors
MESSAGE_SELECTORS = [
    'div[role="article"]',
    'div[data-ad-preview="message"]',
    '.x1iorvi4.x1pi30zi',
    'div[dir="auto"]'
]

def try_selectors(page: Page, selectors: List[str], timeout: int = 5000) -> Optional[Locator]:
    """Try multiple selectors and return first visible one."""
    for selector in selectors:
        locator = page.locator(selector)
        try:
            locator.first.wait_for(state='visible', timeout=timeout)
            return locator.first
        except:
            continue
    return None
```

### Verification:
Test that `try_selectors()` returns the first visible element.

---

## Task 3: Implement Facebook Authentication
**Priority:** CRITICAL  
**Estimated Time:** 60 minutes

### Steps:
1. Create `facebook_auth.py`
2. Implement `check_auth_state()` function
3. Implement `login_facebook()` function with:
   - Fallback selectors for email/password
   - Human-like delays
   - Error handling
   - 2FA/CAPTCHA detection
4. Implement `save_auth_state()` function with IndexedDB support

### Code Template:
```python
# facebook_auth.py
import logging
import random
import json
from pathlib import Path
from playwright.sync_api import Page, BrowserContext, TimeoutError as PlaywrightTimeoutError

import config
from exceptions import LoginError
from utils.selector_strategies import EMAIL_SELECTORS, PASSWORD_SELECTORS, try_selectors

logger = logging.getLogger(__name__)

def check_auth_state() -> bool:
    """Check if Facebook auth state exists."""
    auth_file = Path('auth_facebook.json')
    return auth_file.exists()

def login_facebook(page: Page) -> bool:
    """
    Perform Facebook login with fallback selectors.
    Returns True if successful, raises LoginError on failure.
    """
    try:
        logger.info("Navigating to Facebook login page...")
        page.goto('https://www.facebook.com/login', 
                 wait_until='domcontentloaded', 
                 timeout=config.NAVIGATION_TIMEOUT)
        
        # Email input
        logger.info("Entering email...")
        email_input = try_selectors(page, EMAIL_SELECTORS, timeout=10000)
        if not email_input:
            raise LoginError("Could not find email input field")
        
        email_input.fill(config.FACEBOOK_EMAIL)
        page.wait_for_timeout(random.randint(500, 1500))
        
        # Password input
        logger.info("Entering password...")
        password_input = try_selectors(page, PASSWORD_SELECTORS, timeout=5000)
        if not password_input:
            raise LoginError("Could not find password input field")
        
        password_input.fill(config.FACEBOOK_PASSWORD)
        page.wait_for_timeout(random.randint(500, 1500))
        
        # Login button
        logger.info("Clicking login button...")
        login_button = page.get_by_role("button", name=re.compile("log in|sign in", re.IGNORECASE))
        login_button.or_(page.locator('button[name="login"]')).first.click()
        
        # Wait for navigation
        logger.info("Waiting for authentication...")
        page.wait_for_load_state('networkidle', timeout=config.LOGIN_TIMEOUT)
        
        logger.info("✅ Facebook login successful")
        return True
        
    except PlaywrightTimeoutError as e:
        logger.error(f"Facebook login timeout: {e}")
        raise LoginError(f"Login timed out: {e}")
    except Exception as e:
        logger.error(f"Facebook login failed: {e}")
        raise LoginError(f"Login error: {e}")

def save_auth_state(context: BrowserContext, page: Page):
    """Save Facebook authentication state with IndexedDB support."""
    try:
        # Save storage state with IndexedDB
        context.storage_state(path='auth_facebook.json', indexed_db=True)
        logger.info("Saved Facebook storage state to auth_facebook.json")
        
        # Save session storage
        session_storage = page.evaluate("() => JSON.stringify(sessionStorage)")
        with open('auth_facebook_session.json', 'w') as f:
            json.dump({'session_storage': session_storage}, f)
        logger.info("Saved Facebook session storage")
        
    except Exception as e:
        logger.error(f"Failed to save auth state: {e}")
```

### Verification:
1. Run login flow with valid credentials
2. Verify `auth_facebook.json` and `auth_facebook_session.json` created
3. Close browser and reopen - should load session

---

## Task 4: Implement Message Content Extraction
**Priority:** CRITICAL  
**Estimated Time:** 45 minutes

### Steps:
1. Create `facebook_extractor.py`
2. Implement `navigate_to_message()` function with retry
3. Implement `extract_message_text()` function with:
   - Multiple selector fallback
   - Explicit waits
   - Content validation
   - Whitespace cleanup

### Code Template:
```python
# facebook_extractor.py
import logging
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError

import config
from exceptions import NavigationError, ExtractionError
from utils.selector_strategies import MESSAGE_SELECTORS, try_selectors

logger = logging.getLogger(__name__)

def navigate_to_message(page: Page, url: str, max_retries: int = 3) -> bool:
    """Navigate to Facebook message with retry logic."""
    for attempt in range(max_retries):
        try:
            logger.info(f"Navigating to message URL (attempt {attempt + 1}/{max_retries})...")
            page.goto(url, wait_until='domcontentloaded', timeout=config.NAVIGATION_TIMEOUT)
            logger.info("✅ Navigation successful")
            return True
        except PlaywrightTimeoutError:
            if attempt < max_retries - 1:
                logger.warning(f"Navigation timeout, retrying...")
                page.wait_for_timeout(2000)
            else:
                raise NavigationError(f"Failed to navigate after {max_retries} attempts")
    return False

def extract_message_text(page: Page) -> str:
    """Extract message text with fallback selectors and validation."""
    try:
        logger.info("Waiting for message content...")
        
        # Try multiple selectors
        message_locator = try_selectors(page, MESSAGE_SELECTORS, timeout=10000)
        
        if not message_locator:
            raise ExtractionError("Could not locate message content with any selector")
        
        # Extract text
        message_text = message_locator.text_content()
        
        # Validate
        if not message_text or len(message_text.strip()) == 0:
            raise ExtractionError("Extracted message is empty")
        
        # Clean whitespace
        message_text = ' '.join(message_text.split())
        
        logger.info(f"✅ Extracted message ({len(message_text)} chars): {message_text[:100]}...")
        
        return message_text
        
    except Exception as e:
        logger.error(f"Content extraction failed: {e}")
        raise ExtractionError(f"Failed to extract message: {e}")
```

### Verification:
Navigate to real Facebook message and extract text successfully.

---

## Task 5: Integrate Phase 1 into Main Script
**Priority:** HIGH  
**Estimated Time:** 30 minutes

### Steps:
1. Update `relay_agent.py` to import Phase 1 modules
2. Implement Facebook authentication flow
3. Implement message extraction flow
4. Add comprehensive error handling
5. Add detailed logging

### Code Template:
```python
# relay_agent.py (Phase 1 integration)
from playwright.sync_api import sync_playwright
import logging

import config
from exceptions import LoginError, ExtractionError, NavigationError
from utils.browser_config import create_browser_context
from facebook_auth import check_auth_state, login_facebook, save_auth_state
from facebook_extractor import navigate_to_message, extract_message_text

logger = logging.getLogger(__name__)

def main():
    config.validate_config()
    
    with sync_playwright() as p:
        # Launch browser
        browser = p.chromium.launch(
            headless=config.HEADLESS,
            slow_mo=config.SLOW_MO
        )
        
        try:
            # Phase 1: Facebook Content Acquisition
            logger.info("=== Phase 1: Facebook Content Acquisition ===")
            
            # Create context (with or without saved state)
            if check_auth_state():
                logger.info("Loading saved Facebook session...")
                context = create_browser_context(browser, 'auth_facebook.json')
            else:
                logger.info("No saved session found, manual login required")
                context = create_browser_context(browser)
            
            page = context.new_page()
            
            # Login if needed
            if not check_auth_state():
                login_facebook(page)
                save_auth_state(context, page)
            
            # Navigate and extract
            navigate_to_message(page, config.FACEBOOK_MESSAGE_URL)
            message_text = extract_message_text(page)
            
            logger.info(f"✅ Phase 1 complete - Extracted: {message_text[:100]}...")
            
            # TODO: Phase 2 - X/Twitter Posting
            # TODO: Phase 3 - Screenshot & Database
            
        except (LoginError, NavigationError, ExtractionError) as e:
            logger.error(f"Phase 1 failed: {e}")
            raise
        finally:
            browser.close()

if __name__ == "__main__":
    main()
```

### Verification:
Run full Phase 1 flow end-to-end.

---

## Task 6: Implement Manual Intervention for CAPTCHA/2FA
**Priority:** MEDIUM  
**Estimated Time:** 20 minutes

### Steps:
1. Add `wait_for_manual_intervention()` function
2. Detect CAPTCHA/2FA scenarios
3. Pause execution for manual resolution

### Code Template:
```python
# facebook_auth.py (add this function)
def wait_for_manual_intervention(page: Page, message: str = "Manual intervention required", timeout: int = 300000):
    """Pause for manual CAPTCHA/2FA resolution."""
    logger.warning(f"⚠️ {message}")
    logger.warning(f"Waiting up to {timeout/1000} seconds for manual resolution...")
    logger.warning("Press Ctrl+C when done to continue...")
    
    try:
        page.wait_for_timeout(timeout)
    except KeyboardInterrupt:
        logger.info("✅ Manual intervention completed")
    
    return True
```

### Usage:
Add detection in login flow:
```python
# After login button click
try:
    page.wait_for_load_state('networkidle', timeout=10000)
except:
    # Possible CAPTCHA
    wait_for_manual_intervention(page, "Possible CAPTCHA detected")
```

---

## ✅ Phase 1 Completion Checklist

### Code Implementation
- [ ] `utils/browser_config.py` created with anti-detection config
- [ ] `utils/selector_strategies.py` created with fallback selectors
- [ ] `facebook_auth.py` created with login flow
- [ ] `facebook_extractor.py` created with extraction logic
- [ ] `relay_agent.py` updated with Phase 1 integration

### Functionality
- [ ] Browser launches with correct anti-detection settings
- [ ] Session state loads from file when available
- [ ] Manual login works with real Facebook credentials
- [ ] Session state saves with IndexedDB support
- [ ] Navigation to message URL succeeds
- [ ] Message text extraction works
- [ ] Extracted text validates (non-empty)
- [ ] Human-like delays implemented (500-1500ms)

### Error Handling
- [ ] Login timeout errors caught and logged
- [ ] Navigation errors caught and logged
- [ ] Extraction errors caught and logged
- [ ] Retry logic works for navigation
- [ ] CAPTCHA/2FA manual intervention works

### Logging
- [ ] All major actions logged at INFO level
- [ ] Errors logged with details
- [ ] Extracted message preview logged
- [ ] Session state save/load logged

### Testing
- [ ] Tested with saved session (should skip login)
- [ ] Tested without saved session (manual login)
- [ ] Tested with expired session (re-login)
- [ ] Tested with real Facebook message URL
- [ ] Verified extraction accuracy

---

## 🚀 Next Steps
Once Phase 1 is complete, proceed to **Phase 2: X/Twitter Posting**


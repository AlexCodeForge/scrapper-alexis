# Phase 2: X/Twitter Posting - Tasks

## Task 1: Create Text Truncation Utility
**Priority:** HIGH  
**Estimated Time:** 20 minutes

### Steps:
1. Create `utils/text_utils.py`
2. Implement `truncate_for_x()` function
3. Add unit tests for edge cases

### Code Template:
```python
# utils/text_utils.py
import logging

logger = logging.getLogger(__name__)

X_CHAR_LIMIT = 280

def truncate_for_x(text: str, limit: int = X_CHAR_LIMIT) -> str:
    """
    Truncate text to fit X character limit with ellipsis.
    
    Args:
        text: Original text to truncate
        limit: Character limit (default 280)
    
    Returns:
        Truncated text with ellipsis if needed
    """
    if len(text) <= limit:
        return text
    
    # Reserve 3 characters for ellipsis
    truncated = text[:limit - 3].rstrip()
    result = f"{truncated}..."
    
    logger.info(f"Truncated text from {len(text)} to {len(result)} characters")
    
    return result


def was_truncated(original: str, truncated: str) -> bool:
    """Check if text was truncated."""
    return len(original) > len(truncated)
```

### Verification:
```python
# Test cases
assert truncate_for_x("Short") == "Short"
assert len(truncate_for_x("a" * 300)) == 280
assert truncate_for_x("a" * 300).endswith("...")
```

---

## Task 2: Implement X/Twitter Authentication
**Priority:** CRITICAL  
**Estimated Time:** 60 minutes

### Steps:
1. Create `twitter_auth.py`
2. Implement `check_x_auth_state()` function
3. Implement multi-step `login_x()` function:
   - Enter username/email
   - Click "Next" button
   - Wait for password field
   - Enter password
   - Click "Login" button
   - Handle verification challenges
4. Implement `save_x_auth_state()` function

### Code Template:
```python
# twitter_auth.py
import logging
import random
import json
import re
from pathlib import Path
from playwright.sync_api import Page, BrowserContext, TimeoutError as PlaywrightTimeoutError

import config
from exceptions import LoginError

logger = logging.getLogger(__name__)

def check_x_auth_state() -> bool:
    """Check if X/Twitter auth state exists."""
    auth_file = Path('auth_x.json')
    return auth_file.exists()


def login_x(page: Page) -> bool:
    """
    Perform X/Twitter multi-step login.
    Returns True if successful, raises LoginError on failure.
    """
    try:
        logger.info("Navigating to X login page...")
        page.goto('https://twitter.com/i/flow/login', 
                 wait_until='domcontentloaded', 
                 timeout=config.NAVIGATION_TIMEOUT)
        
        # Step 1: Username/Email input
        logger.info("Step 1: Entering username/email...")
        username_selectors = [
            'input[autocomplete="username"]',
            'input[name="text"]',
            'input[type="text"]'
        ]
        
        username_input = None
        for selector in username_selectors:
            locator = page.locator(selector)
            if locator.count() > 0:
                username_input = locator.first
                break
        
        if not username_input:
            raise LoginError("Could not find username input field")
        
        username_input.fill(config.X_EMAIL)
        page.wait_for_timeout(random.randint(300, 800))
        
        # Click Next button
        logger.info("Clicking Next button...")
        next_button = page.get_by_role("button", name=re.compile("next|continue", re.IGNORECASE))
        next_button.click()
        
        # Step 2: Wait for password field
        logger.info("Step 2: Waiting for password field...")
        page.wait_for_timeout(random.randint(1000, 2000))
        
        password_selectors = [
            'input[name="password"]',
            'input[type="password"]',
            'input[autocomplete="current-password"]'
        ]
        
        password_input = None
        for selector in password_selectors:
            locator = page.locator(selector)
            try:
                locator.first.wait_for(state='visible', timeout=5000)
                password_input = locator.first
                break
            except:
                continue
        
        if not password_input:
            raise LoginError("Could not find password input field")
        
        logger.info("Entering password...")
        password_input.fill(config.X_PASSWORD)
        page.wait_for_timeout(random.randint(500, 1000))
        
        # Click Login button
        logger.info("Clicking login button...")
        login_button = page.get_by_role("button", name=re.compile("log in|sign in", re.IGNORECASE))
        login_button.click()
        
        # Wait for authentication
        logger.info("Waiting for authentication to complete...")
        page.wait_for_load_state('networkidle', timeout=config.LOGIN_TIMEOUT)
        
        logger.info("✅ X/Twitter login successful")
        return True
        
    except PlaywrightTimeoutError as e:
        logger.error(f"X login timeout: {e}")
        raise LoginError(f"X login timed out: {e}")
    except Exception as e:
        logger.error(f"X login failed: {e}")
        raise LoginError(f"X login error: {e}")


def save_x_auth_state(context: BrowserContext, page: Page):
    """Save X/Twitter authentication state."""
    try:
        # Save storage state with IndexedDB
        context.storage_state(path='auth_x.json', indexed_db=True)
        logger.info("Saved X storage state to auth_x.json")
        
        # Save session storage
        session_storage = page.evaluate("() => JSON.stringify(sessionStorage)")
        with open('auth_x_session.json', 'w') as f:
            json.dump({'session_storage': session_storage}, f)
        logger.info("Saved X session storage")
        
    except Exception as e:
        logger.error(f"Failed to save X auth state: {e}")


def wait_for_manual_verification(page: Page, timeout: int = 300000):
    """Wait for manual phone/email verification."""
    logger.warning("⚠️ Manual verification may be required")
    logger.warning(f"Waiting up to {timeout/1000} seconds...")
    logger.warning("Complete verification in browser, then press Ctrl+C to continue")
    
    try:
        page.wait_for_timeout(timeout)
    except KeyboardInterrupt:
        logger.info("✅ Manual verification completed")
    
    return True
```

### Verification:
1. Run login with valid X credentials
2. Verify `auth_x.json` and `auth_x_session.json` created
3. Test multi-step flow completes

---

## Task 3: Implement Post Composer Logic
**Priority:** CRITICAL  
**Estimated Time:** 45 minutes

### Steps:
1. Create `twitter_poster.py`
2. Implement `navigate_to_compose()` function
3. Implement `post_to_x()` function with:
   - Composer field detection
   - Human-like typing
   - Post button validation
   - Submission verification

### Code Template:
```python
# twitter_poster.py
import logging
import random
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError

import config
from exceptions import PostingError, NavigationError
from utils.text_utils import truncate_for_x

logger = logging.getLogger(__name__)

def navigate_to_compose(page: Page) -> bool:
    """Navigate to X compose page."""
    try:
        logger.info("Navigating to X compose page...")
        page.goto('https://twitter.com/compose/tweet', 
                 wait_until='domcontentloaded', 
                 timeout=config.NAVIGATION_TIMEOUT)
        
        logger.info("✅ Navigation to compose successful")
        return True
        
    except PlaywrightTimeoutError as e:
        logger.error(f"Navigation to compose failed: {e}")
        raise NavigationError(f"Could not navigate to compose: {e}")


def post_to_x(page: Page, message_text: str) -> str:
    """
    Post message to X/Twitter.
    
    Args:
        page: Playwright page object
        message_text: Text to post (will be truncated if needed)
    
    Returns:
        The text that was actually posted
    """
    try:
        # Truncate if needed
        original_length = len(message_text)
        post_text = truncate_for_x(message_text)
        
        if original_length > len(post_text):
            logger.info(f"Truncated message from {original_length} to {len(post_text)} chars")
        else:
            logger.info(f"Message within limit: {len(post_text)} chars")
        
        # Find composer input
        logger.info("Locating composer input...")
        composer_selectors = [
            'div[data-testid="tweetTextarea_0"]',
            'div[role="textbox"]',
            'div[contenteditable="true"]'
        ]
        
        composer = None
        for selector in composer_selectors:
            locator = page.locator(selector)
            try:
                locator.first.wait_for(state='visible', timeout=5000)
                composer = locator.first
                logger.info(f"Found composer with selector: {selector}")
                break
            except:
                continue
        
        if not composer:
            raise PostingError("Could not find composer input")
        
        # Type the text with human-like delay
        logger.info("Typing message into composer...")
        composer.click()
        page.wait_for_timeout(random.randint(300, 700))
        page.keyboard.type(post_text, delay=random.randint(50, 150))
        
        # Wait before posting
        page.wait_for_timeout(random.randint(1000, 2000))
        
        # Find post button
        logger.info("Locating post button...")
        post_button_selectors = [
            'button[data-testid="tweetButton"]',
            'button[data-testid="tweetButtonInline"]',
            'div[data-testid="tweetButton"]'
        ]
        
        post_button = None
        for selector in post_button_selectors:
            locator = page.locator(selector)
            if locator.count() > 0:
                # Check if enabled
                if locator.first.is_enabled():
                    post_button = locator.first
                    logger.info(f"Found enabled post button: {selector}")
                    break
        
        if not post_button:
            raise PostingError("Could not find enabled post button")
        
        # Click post button
        logger.info("Clicking post button...")
        post_button.click()
        
        # Wait for post to complete
        page.wait_for_timeout(3000)
        
        logger.info("✅ Successfully posted to X/Twitter")
        logger.info(f"Posted text: {post_text[:100]}...")
        
        return post_text
        
    except Exception as e:
        logger.error(f"Failed to post to X: {e}")
        raise PostingError(f"X posting failed: {e}")
```

### Verification:
Test posting with:
1. Short message (< 280 chars)
2. Long message (> 280 chars) - should truncate
3. Message with special characters

---

## Task 4: Integrate Phase 2 into Main Script
**Priority:** HIGH  
**Estimated Time:** 30 minutes

### Steps:
1. Update `relay_agent.py` imports
2. Add X authentication flow after Facebook extraction
3. Add posting flow
4. Add error handling
5. Store both original and truncated text

### Code Template:
```python
# relay_agent.py (Phase 2 integration)
from playwright.sync_api import sync_playwright
import logging

import config
from exceptions import LoginError, PostingError
from utils.browser_config import create_browser_context

# Phase 1 imports
from facebook_auth import check_auth_state, login_facebook, save_auth_state
from facebook_extractor import navigate_to_message, extract_message_text

# Phase 2 imports
from twitter_auth import check_x_auth_state, login_x, save_x_auth_state
from twitter_poster import navigate_to_compose, post_to_x
from utils.text_utils import was_truncated

logger = logging.getLogger(__name__)

def main():
    config.validate_config()
    
    with sync_playwright() as p:
        browser = p.chromium.launch(
            headless=config.HEADLESS,
            slow_mo=config.SLOW_MO
        )
        
        try:
            # === PHASE 1: Facebook Content Acquisition ===
            logger.info("=== Phase 1: Facebook Content Acquisition ===")
            
            # [Phase 1 code from previous task...]
            # ... Facebook auth and extraction ...
            message_text_original = extract_message_text(page)
            
            logger.info("✅ Phase 1 complete")
            
            # === PHASE 2: X/Twitter Posting ===
            logger.info("=== Phase 2: X/Twitter Posting ===")
            
            # Create X context
            if check_x_auth_state():
                logger.info("Loading saved X session...")
                x_context = create_browser_context(browser, 'auth_x.json')
            else:
                logger.info("No saved X session, manual login required")
                x_context = create_browser_context(browser)
            
            x_page = x_context.new_page()
            
            # Login if needed
            if not check_x_auth_state():
                login_x(x_page)
                save_x_auth_state(x_context, x_page)
            
            # Navigate to compose
            navigate_to_compose(x_page)
            
            # Post message
            posted_text = post_to_x(x_page, message_text_original)
            
            # Check if truncated
            if was_truncated(message_text_original, posted_text):
                logger.warning(f"⚠️ Message was truncated from {len(message_text_original)} to {len(posted_text)} chars")
            
            logger.info("✅ Phase 2 complete")
            
            # TODO: Phase 3 - Screenshot & Database
            
        except (LoginError, PostingError) as e:
            logger.error(f"Phase 2 failed: {e}")
            raise
        finally:
            browser.close()

if __name__ == "__main__":
    main()
```

### Verification:
Run full Phases 1 & 2 end-to-end.

---

## Task 5: Add Alternative Compose Navigation
**Priority:** MEDIUM  
**Estimated Time:** 15 minutes

### Steps:
1. Add alternative navigation method (click compose button from home)
2. Implement as fallback if direct URL fails

### Code Addition:
```python
# twitter_poster.py (add this function)
def click_compose_button(page: Page) -> bool:
    """Alternative: Click compose button from X home page."""
    try:
        logger.info("Alternative: Clicking compose button...")
        
        # Navigate to home first
        page.goto('https://twitter.com/home', wait_until='domcontentloaded')
        
        # Find compose button
        compose_button = page.get_by_role("link", name=re.compile("post|tweet|compose", re.IGNORECASE))
        compose_button.click()
        
        page.wait_for_timeout(1000)
        logger.info("✅ Compose modal opened")
        return True
        
    except Exception as e:
        logger.error(f"Failed to click compose button: {e}")
        return False
```

### Verification:
Test both navigation methods work.

---

## ✅ Phase 2 Completion Checklist

### Code Implementation
- [ ] `utils/text_utils.py` created with truncation logic
- [ ] `twitter_auth.py` created with multi-step login
- [ ] `twitter_poster.py` created with posting logic
- [ ] `relay_agent.py` updated with Phase 2 integration

### Functionality
- [ ] Text truncation works (280 char limit)
- [ ] X session loads from file when available
- [ ] Multi-step login works (username → password)
- [ ] Session state saves with IndexedDB
- [ ] Navigate to compose page succeeds
- [ ] Composer field located correctly
- [ ] Text types with human-like delay
- [ ] Post button validates (enabled check)
- [ ] Post submission succeeds
- [ ] Truncation detected and logged

### Error Handling
- [ ] Login errors caught and logged
- [ ] Navigation errors handled
- [ ] Posting errors caught
- [ ] Composer not found handled
- [ ] Post button not enabled handled

### Logging
- [ ] All major actions logged
- [ ] Truncation logged with char counts
- [ ] Posted text preview logged
- [ ] Session save/load logged

### Testing
- [ ] Tested with short message (< 280 chars)
- [ ] Tested with long message (> 280 chars)
- [ ] Tested with saved session (skips login)
- [ ] Tested without saved session (manual login)
- [ ] Verified post appears on X timeline

---

## 🚀 Next Steps
Once Phase 2 is complete, proceed to **Phase 3: Screenshot & Database Storage**


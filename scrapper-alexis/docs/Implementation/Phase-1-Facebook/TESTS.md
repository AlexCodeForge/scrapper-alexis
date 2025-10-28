# Phase 1: Testing & Validation

## Test Categories

### 1. Browser Configuration Tests

#### Test 1.1: Anti-Detection Settings
```python
# test_browser_config.py
from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
import config

def test_anti_detection_config():
    """Verify browser context has correct anti-detection settings."""
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = create_browser_context(browser)
        page = context.new_page()
        
        # Check user agent
        user_agent = page.evaluate("navigator.userAgent")
        assert config.USER_AGENT in user_agent
        
        # Check viewport
        viewport = page.viewport_size
        assert viewport['width'] == 1920
        assert viewport['height'] == 1080
        
        # Check locale
        locale = page.evaluate("navigator.language")
        assert locale.startswith('en')
        
        print("✅ Anti-detection config correct")
        browser.close()

if __name__ == "__main__":
    test_anti_detection_config()
```

**Pass Criteria:** All assertions pass

---

#### Test 1.2: Storage State Loading
```python
# test_storage_state.py
from pathlib import Path
from playwright.sync_playwright import sync_playwright
from utils.browser_config import create_browser_context

def test_storage_state_loading():
    """Test loading saved storage state."""
    # This test requires auth_facebook.json to exist
    auth_file = Path('auth_facebook.json')
    
    if not auth_file.exists():
        print("⚠️ Skipping - auth_facebook.json not found")
        return
    
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = create_browser_context(browser, 'auth_facebook.json')
        
        # Verify cookies loaded
        cookies = context.cookies()
        assert len(cookies) > 0
        
        print(f"✅ Loaded {len(cookies)} cookies from storage state")
        browser.close()

if __name__ == "__main__":
    test_storage_state_loading()
```

**Pass Criteria:** Cookies loaded from storage state

---

### 2. Selector Strategy Tests

#### Test 2.1: Selector List Validation
```python
# test_selectors.py
from utils.selector_strategies import (
    EMAIL_SELECTORS,
    PASSWORD_SELECTORS,
    MESSAGE_SELECTORS
)

def test_selector_lists():
    """Verify selector lists are not empty."""
    assert len(EMAIL_SELECTORS) >= 3
    assert len(PASSWORD_SELECTORS) >= 3
    assert len(MESSAGE_SELECTORS) >= 3
    
    print(f"✅ Email selectors: {len(EMAIL_SELECTORS)}")
    print(f"✅ Password selectors: {len(PASSWORD_SELECTORS)}")
    print(f"✅ Message selectors: {len(MESSAGE_SELECTORS)}")

if __name__ == "__main__":
    test_selector_lists()
```

**Pass Criteria:** All selector lists have at least 3 entries

---

#### Test 2.2: try_selectors Function
```python
# test_try_selectors.py
from playwright.sync_api import sync_playwright
from utils.selector_strategies import try_selectors

def test_try_selectors_function():
    """Test try_selectors helper function."""
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        
        # Navigate to simple HTML
        page.set_content("""
            <html>
                <body>
                    <input id="email" type="email" />
                    <input id="password" type="password" />
                </body>
            </html>
        """)
        
        # Try selectors
        selectors = ['#nonexistent', '#email', 'input[type="email"]']
        result = try_selectors(page, selectors, timeout=1000)
        
        assert result is not None
        assert result.get_attribute('type') == 'email'
        
        print("✅ try_selectors works correctly")
        browser.close()

if __name__ == "__main__":
    test_try_selectors_function()
```

**Pass Criteria:** Function returns first visible element

---

### 3. Authentication Tests

#### Test 3.1: Auth State Check
```python
# test_auth_state.py
from facebook_auth import check_auth_state
from pathlib import Path

def test_check_auth_state():
    """Test auth state detection."""
    auth_file = Path('auth_facebook.json')
    result = check_auth_state()
    
    if auth_file.exists():
        assert result == True
        print("✅ Auth state detected")
    else:
        assert result == False
        print("✅ No auth state detected")

if __name__ == "__main__":
    test_check_auth_state()
```

**Pass Criteria:** Returns correct boolean based on file existence

---

#### Test 3.2: Login Flow (Integration Test)
```python
# test_login_flow.py
# ⚠️ This test requires valid Facebook credentials
# Run manually, not in CI/CD

from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from facebook_auth import login_facebook, save_auth_state
import logging

logging.basicConfig(level=logging.INFO)

def test_login_flow():
    """Test complete Facebook login flow."""
    print("⚠️ This test will perform actual Facebook login")
    print("Make sure credentials are in .env file")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)  # Visible for debugging
        context = create_browser_context(browser)
        page = context.new_page()
        
        try:
            # Perform login
            login_facebook(page)
            
            # Save auth state
            save_auth_state(context, page)
            
            # Verify files created
            assert Path('auth_facebook.json').exists()
            assert Path('auth_facebook_session.json').exists()
            
            print("✅ Login flow successful")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_login_flow()
```

**Pass Criteria:** Login succeeds and auth files created

---

### 4. Content Extraction Tests

#### Test 4.1: Navigation with Retry
```python
# test_navigation.py
from playwright.sync_api import sync_playwright
from facebook_extractor import navigate_to_message
from exceptions import NavigationError
import logging

logging.basicConfig(level=logging.INFO)

def test_navigate_to_message():
    """Test navigation with retry logic."""
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        
        # Test with valid URL (Google as placeholder)
        try:
            result = navigate_to_message(page, "https://www.google.com", max_retries=2)
            assert result == True
            print("✅ Navigation successful")
        except NavigationError as e:
            print(f"❌ Navigation failed: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    test_navigate_to_message()
```

**Pass Criteria:** Navigation succeeds with valid URL

---

#### Test 4.2: Message Text Extraction (Integration Test)
```python
# test_extraction.py
# ⚠️ Requires authenticated session and valid message URL

from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from facebook_extractor import navigate_to_message, extract_message_text
import config
import logging

logging.basicConfig(level=logging.INFO)

def test_message_extraction():
    """Test message text extraction from real Facebook message."""
    print("⚠️ This test requires:")
    print("  1. auth_facebook.json (run login test first)")
    print("  2. Valid FACEBOOK_MESSAGE_URL in .env")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        context = create_browser_context(browser, 'auth_facebook.json')
        page = context.new_page()
        
        try:
            # Navigate to message
            navigate_to_message(page, config.FACEBOOK_MESSAGE_URL)
            
            # Extract text
            message_text = extract_message_text(page)
            
            # Validate
            assert message_text is not None
            assert len(message_text) > 0
            assert message_text.strip() != ''
            
            print(f"✅ Extracted message: {message_text[:100]}...")
            print(f"✅ Message length: {len(message_text)} characters")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_message_extraction()
```

**Pass Criteria:** Successfully extracts non-empty message text

---

### 5. Error Handling Tests

#### Test 5.1: Login Error Handling
```python
# test_login_errors.py
from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from facebook_auth import login_facebook
from exceptions import LoginError
import config

def test_login_with_invalid_credentials():
    """Test login error handling with invalid credentials."""
    # Temporarily override credentials
    original_email = config.FACEBOOK_EMAIL
    original_password = config.FACEBOOK_PASSWORD
    
    config.FACEBOOK_EMAIL = "invalid@example.com"
    config.FACEBOOK_PASSWORD = "wrongpassword"
    
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = create_browser_context(browser)
        page = context.new_page()
        
        try:
            login_facebook(page)
            print("❌ Should have raised LoginError")
        except LoginError as e:
            print(f"✅ LoginError raised correctly: {e}")
        finally:
            # Restore credentials
            config.FACEBOOK_EMAIL = original_email
            config.FACEBOOK_PASSWORD = original_password
            browser.close()

if __name__ == "__main__":
    test_login_with_invalid_credentials()
```

**Pass Criteria:** LoginError raised with invalid credentials

---

#### Test 5.2: Extraction Error Handling
```python
# test_extraction_errors.py
from playwright.sync_api import sync_playwright
from facebook_extractor import extract_message_text
from exceptions import ExtractionError

def test_extraction_on_empty_page():
    """Test extraction fails gracefully on page without message."""
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        
        # Navigate to page without message content
        page.goto('https://example.com')
        
        try:
            extract_message_text(page)
            print("❌ Should have raised ExtractionError")
        except ExtractionError as e:
            print(f"✅ ExtractionError raised correctly: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    test_extraction_on_empty_page()
```

**Pass Criteria:** ExtractionError raised when no message found

---

### 6. End-to-End Integration Tests

#### Test 6.1: Complete Phase 1 Flow
```python
# test_phase1_e2e.py
# ⚠️ Full integration test - requires valid credentials and message URL

from playwright.sync_api import sync_playwright
import logging
from pathlib import Path

import config
from utils.browser_config import create_browser_context
from facebook_auth import check_auth_state, login_facebook, save_auth_state
from facebook_extractor import navigate_to_message, extract_message_text

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def test_phase1_complete_flow():
    """Test complete Phase 1 end-to-end."""
    print("\n" + "="*50)
    print("Phase 1 End-to-End Test")
    print("="*50 + "\n")
    
    # Delete existing auth files for fresh test
    Path('auth_facebook.json').unlink(missing_ok=True)
    Path('auth_facebook_session.json').unlink(missing_ok=True)
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        
        try:
            # Step 1: Create context
            logger.info("Step 1: Creating browser context...")
            context = create_browser_context(browser)
            page = context.new_page()
            
            # Step 2: Login
            logger.info("Step 2: Performing login...")
            login_facebook(page)
            
            # Step 3: Save auth
            logger.info("Step 3: Saving authentication state...")
            save_auth_state(context, page)
            assert Path('auth_facebook.json').exists()
            
            # Step 4: Navigate
            logger.info("Step 4: Navigating to message...")
            navigate_to_message(page, config.FACEBOOK_MESSAGE_URL)
            
            # Step 5: Extract
            logger.info("Step 5: Extracting message text...")
            message_text = extract_message_text(page)
            assert len(message_text) > 0
            
            print("\n" + "="*50)
            print("✅ PHASE 1 COMPLETE")
            print(f"Extracted: {message_text[:100]}...")
            print("="*50 + "\n")
            
            return message_text
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_phase1_complete_flow()
```

**Pass Criteria:** All steps complete without errors

---

## Manual Testing Checklist

### Pre-Test Setup
- [ ] `.env` file contains valid Facebook credentials
- [ ] `FACEBOOK_MESSAGE_URL` points to accessible message
- [ ] Virtual environment activated
- [ ] All Phase 1 modules created

### First Run (No Saved Session)
- [ ] Browser launches with visible window
- [ ] Navigates to Facebook login page
- [ ] Email field fills correctly
- [ ] Password field fills correctly
- [ ] Human-like delays occur (500-1500ms)
- [ ] Login button clicks successfully
- [ ] Login completes (redirect to home/feed)
- [ ] `auth_facebook.json` created
- [ ] `auth_facebook_session.json` created
- [ ] Navigates to message URL
- [ ] Message content loads
- [ ] Text extracts successfully
- [ ] Extracted text is non-empty
- [ ] Logs show all major steps

### Second Run (Saved Session)
- [ ] Browser launches
- [ ] Loads session from `auth_facebook.json`
- [ ] Skips login flow
- [ ] Navigates directly to message
- [ ] Extracts text successfully
- [ ] Completes faster than first run (~15-30 seconds vs 30-60 seconds)

### Error Scenarios
- [ ] Test with invalid credentials (should raise LoginError)
- [ ] Test with wrong message URL (should raise NavigationError)
- [ ] Test with deleted message (should raise ExtractionError)
- [ ] Test with expired session (should re-authenticate)
- [ ] Test CAPTCHA detection (manual intervention works)

### Logging Validation
- [ ] Log file created in `logs/` directory
- [ ] All INFO messages present
- [ ] No unexpected ERROR messages
- [ ] Login status logged
- [ ] Extraction preview logged
- [ ] Session save/load logged

---

## Performance Benchmarks

### Expected Timing (First Run)
- Browser launch: 2-5 seconds
- Navigation to login: 3-5 seconds
- Login form fill: 3-5 seconds
- Authentication wait: 5-15 seconds
- Navigate to message: 3-5 seconds
- Extract content: 2-5 seconds
**Total: 20-40 seconds**

### Expected Timing (Cached Session)
- Browser launch: 2-5 seconds
- Load session: 1-2 seconds
- Navigate to message: 3-5 seconds
- Extract content: 2-5 seconds
**Total: 8-17 seconds**

### Performance Issues
If Phase 1 takes longer than 60 seconds:
- Check network latency
- Verify CAPTCHA not triggered
- Check for DOM changes (update selectors)
- Verify message URL is correct

---

## Phase 1 Test Summary

| Test Category | # of Tests | Priority |
|--------------|-----------|----------|
| Browser Config | 2 | HIGH |
| Selector Strategies | 2 | MEDIUM |
| Authentication | 2 | CRITICAL |
| Content Extraction | 2 | CRITICAL |
| Error Handling | 2 | HIGH |
| End-to-End | 1 | CRITICAL |

**Total: 11 automated tests**

---

## ✅ Phase 1 Test Completion Criteria

- [ ] All unit tests pass
- [ ] Integration tests pass with real credentials
- [ ] End-to-end test completes successfully
- [ ] Manual testing checklist completed
- [ ] Performance benchmarks met
- [ ] Error handling verified
- [ ] Logging validated
- [ ] Auth state persists across runs

**When all criteria are met, Phase 1 is fully tested!** ✅


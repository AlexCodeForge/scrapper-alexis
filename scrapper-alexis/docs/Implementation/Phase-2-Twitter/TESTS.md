# Phase 2: Testing & Validation

## Test Categories

### 1. Text Truncation Tests

#### Test 1.1: Text Within Limit
```python
# test_text_utils.py
from utils.text_utils import truncate_for_x, was_truncated

def test_truncate_within_limit():
    """Test that short text is not modified."""
    text = "This is a short message"
    result = truncate_for_x(text)
    
    assert result == text
    assert not was_truncated(text, result)
    print("✅ Short text unchanged")

if __name__ == "__main__":
    test_truncate_within_limit()
```

**Pass Criteria:** Original text returned unchanged

---

#### Test 1.2: Text Exceeds Limit
```python
# test_truncation.py
from utils.text_utils import truncate_for_x, was_truncated

def test_truncate_exceeds_limit():
    """Test that long text is truncated correctly."""
    text = "a" * 300  # 300 characters
    result = truncate_for_x(text)
    
    assert len(result) == 280
    assert result.endswith("...")
    assert was_truncated(text, result)
    print(f"✅ Text truncated from {len(text)} to {len(result)} chars")

if __name__ == "__main__":
    test_truncate_exceeds_limit()
```

**Pass Criteria:** Text truncated to 280 chars with ellipsis

---

#### Test 1.3: Edge Case - Exactly 280 Chars
```python
# test_edge_cases.py
from utils.text_utils import truncate_for_x

def test_exactly_280_chars():
    """Test text exactly at limit."""
    text = "a" * 280
    result = truncate_for_x(text)
    
    assert result == text
    assert len(result) == 280
    print("✅ Exactly 280 chars unchanged")

if __name__ == "__main__":
    test_exactly_280_chars()
```

**Pass Criteria:** Text returned unchanged at exactly 280 chars

---

#### Test 1.4: Special Characters
```python
# test_special_chars.py
from utils.text_utils import truncate_for_x

def test_truncate_with_special_chars():
    """Test truncation with emojis and unicode."""
    text = "Hello 👋 " * 50  # Creates long text with emojis
    result = truncate_for_x(text)
    
    assert len(result) == 280
    assert result.endswith("...")
    print("✅ Special characters handled correctly")

if __name__ == "__main__":
    test_truncate_with_special_chars()
```

**Pass Criteria:** Truncation works with unicode characters

---

### 2. Authentication Tests

#### Test 2.1: X Auth State Check
```python
# test_x_auth_state.py
from twitter_auth import check_x_auth_state
from pathlib import Path

def test_check_x_auth_state():
    """Test X auth state detection."""
    auth_file = Path('auth_x.json')
    result = check_x_auth_state()
    
    if auth_file.exists():
        assert result == True
        print("✅ X auth state detected")
    else:
        assert result == False
        print("✅ No X auth state detected")

if __name__ == "__main__":
    test_check_x_auth_state()
```

**Pass Criteria:** Returns correct boolean

---

#### Test 2.2: X Multi-Step Login (Integration Test)
```python
# test_x_login.py
# ⚠️ Requires valid X credentials

from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from twitter_auth import login_x, save_x_auth_state
from pathlib import Path
import logging

logging.basicConfig(level=logging.INFO)

def test_x_login_flow():
    """Test complete X multi-step login."""
    print("⚠️ This test will perform actual X login")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        context = create_browser_context(browser)
        page = context.new_page()
        
        try:
            # Perform login
            login_x(page)
            
            # Save auth
            save_x_auth_state(context, page)
            
            # Verify files
            assert Path('auth_x.json').exists()
            assert Path('auth_x_session.json').exists()
            
            print("✅ X login successful")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_x_login_flow()
```

**Pass Criteria:** Login completes and auth files created

---

### 3. Composer & Posting Tests

#### Test 3.1: Navigate to Compose
```python
# test_navigate_compose.py
from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from twitter_poster import navigate_to_compose
import logging

logging.basicConfig(level=logging.INFO)

def test_navigate_to_compose():
    """Test navigation to compose page."""
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        
        # Requires existing auth
        context = create_browser_context(browser, 'auth_x.json')
        page = context.new_page()
        
        try:
            result = navigate_to_compose(page)
            assert result == True
            
            # Verify composer visible
            composer = page.locator('div[role="textbox"]').first
            assert composer.is_visible()
            
            print("✅ Compose navigation successful")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_navigate_to_compose()
```

**Pass Criteria:** Navigates successfully and composer visible

---

#### Test 3.2: Post Short Message (Integration Test)
```python
# test_post_short.py
# ⚠️ This will actually post to X!

from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from twitter_poster import navigate_to_compose, post_to_x
from datetime import datetime
import logging

logging.basicConfig(level=logging.INFO)

def test_post_short_message():
    """Test posting short message to X."""
    print("⚠️ WARNING: This will post to X!")
    
    test_message = f"Test post from automation - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        context = create_browser_context(browser, 'auth_x.json')
        page = context.new_page()
        
        try:
            navigate_to_compose(page)
            posted_text = post_to_x(page, test_message)
            
            assert posted_text == test_message
            assert len(posted_text) < 280
            
            print(f"✅ Posted: {posted_text}")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_post_short_message()
```

**Pass Criteria:** Message posts successfully

---

#### Test 3.3: Post Long Message with Truncation
```python
# test_post_long.py
# ⚠️ This will post to X!

from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from twitter_poster import navigate_to_compose, post_to_x
from utils.text_utils import was_truncated
import logging

logging.basicConfig(level=logging.INFO)

def test_post_long_message():
    """Test posting message that requires truncation."""
    print("⚠️ WARNING: This will post to X!")
    
    # Create 350 character message
    test_message = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. " * 10
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        context = create_browser_context(browser, 'auth_x.json')
        page = context.new_page()
        
        try:
            navigate_to_compose(page)
            posted_text = post_to_x(page, test_message)
            
            # Verify truncation occurred
            assert was_truncated(test_message, posted_text)
            assert len(posted_text) == 280
            assert posted_text.endswith("...")
            
            print(f"✅ Message truncated from {len(test_message)} to {len(posted_text)} chars")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_post_long_message()
```

**Pass Criteria:** Long message truncated and posted

---

### 4. Error Handling Tests

#### Test 4.1: Composer Not Found
```python
# test_composer_error.py
from playwright.sync_api import sync_playwright
from twitter_poster import post_to_x
from exceptions import PostingError

def test_composer_not_found():
    """Test error handling when composer not found."""
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        
        # Navigate to page without composer
        page.goto('https://example.com')
        
        try:
            post_to_x(page, "Test message")
            print("❌ Should have raised PostingError")
        except PostingError as e:
            print(f"✅ PostingError raised correctly: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    test_composer_not_found()
```

**Pass Criteria:** PostingError raised when composer missing

---

#### Test 4.2: Invalid Login Credentials
```python
# test_x_invalid_login.py
from playwright.sync_api import sync_playwright
from utils.browser_config import create_browser_context
from twitter_auth import login_x
from exceptions import LoginError
import config

def test_x_invalid_credentials():
    """Test X login with invalid credentials."""
    # Temporarily override
    original = config.X_EMAIL
    config.X_EMAIL = "invalid@example.com"
    
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = create_browser_context(browser)
        page = context.new_page()
        
        try:
            login_x(page)
            print("❌ Should have raised LoginError")
        except LoginError as e:
            print(f"✅ LoginError raised correctly: {e}")
        finally:
            config.X_EMAIL = original
            browser.close()

if __name__ == "__main__":
    test_x_invalid_credentials()
```

**Pass Criteria:** LoginError raised with invalid credentials

---

### 5. End-to-End Integration Tests

#### Test 5.1: Complete Phase 2 Flow
```python
# test_phase2_e2e.py
# ⚠️ Full integration test - will post to X!

from playwright.sync_api import sync_playwright
from pathlib import Path
import logging

import config
from utils.browser_config import create_browser_context
from twitter_auth import check_x_auth_state, login_x, save_x_auth_state
from twitter_poster import navigate_to_compose, post_to_x
from utils.text_utils import truncate_for_x, was_truncated

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def test_phase2_complete_flow():
    """Test complete Phase 2 end-to-end."""
    print("\n" + "="*50)
    print("Phase 2 End-to-End Test")
    print("="*50 + "\n")
    print("⚠️ This will post to X!")
    
    # Test message
    test_message = "This is a test message from the relay agent automation. " * 6  # Long message
    
    # Delete existing auth for fresh test
    Path('auth_x.json').unlink(missing_ok=True)
    Path('auth_x_session.json').unlink(missing_ok=True)
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        
        try:
            # Step 1: Create context
            logger.info("Step 1: Creating browser context...")
            context = create_browser_context(browser)
            page = context.new_page()
            
            # Step 2: Login
            logger.info("Step 2: Performing X login...")
            login_x(page)
            
            # Step 3: Save auth
            logger.info("Step 3: Saving X auth state...")
            save_x_auth_state(context, page)
            assert Path('auth_x.json').exists()
            
            # Step 4: Navigate to compose
            logger.info("Step 4: Navigating to compose...")
            navigate_to_compose(page)
            
            # Step 5: Post message
            logger.info("Step 5: Posting message...")
            posted_text = post_to_x(page, test_message)
            
            # Verify truncation
            assert was_truncated(test_message, posted_text)
            assert len(posted_text) == 280
            
            print("\n" + "="*50)
            print("✅ PHASE 2 COMPLETE")
            print(f"Original: {len(test_message)} chars")
            print(f"Posted: {len(posted_text)} chars")
            print(f"Posted text: {posted_text[:100]}...")
            print("="*50 + "\n")
            
            return posted_text
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_phase2_complete_flow()
```

**Pass Criteria:** All steps complete successfully

---

#### Test 5.2: Phases 1 & 2 Combined
```python
# test_phases_1_2_combined.py
# ⚠️ Full integration - extracts from Facebook and posts to X!

from playwright.sync_api import sync_playwright
import logging

import config
from utils.browser_config import create_browser_context

# Phase 1
from facebook_auth import check_auth_state, login_facebook, save_auth_state
from facebook_extractor import navigate_to_message, extract_message_text

# Phase 2
from twitter_auth import check_x_auth_state, login_x, save_x_auth_state
from twitter_poster import navigate_to_compose, post_to_x

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def test_phases_1_2_combined():
    """Test Phases 1 & 2 working together."""
    print("\n" + "="*60)
    print("Phases 1 & 2 Combined Integration Test")
    print("="*60 + "\n")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        
        try:
            # === PHASE 1 ===
            logger.info("=== PHASE 1: Facebook Extraction ===")
            
            fb_context = create_browser_context(browser, 
                'auth_facebook.json' if check_auth_state() else None)
            fb_page = fb_context.new_page()
            
            if not check_auth_state():
                login_facebook(fb_page)
                save_auth_state(fb_context, fb_page)
            
            navigate_to_message(fb_page, config.FACEBOOK_MESSAGE_URL)
            message_text = extract_message_text(fb_page)
            
            logger.info(f"✅ Phase 1 complete: {message_text[:50]}...")
            
            # === PHASE 2 ===
            logger.info("=== PHASE 2: X Posting ===")
            
            x_context = create_browser_context(browser,
                'auth_x.json' if check_x_auth_state() else None)
            x_page = x_context.new_page()
            
            if not check_x_auth_state():
                login_x(x_page)
                save_x_auth_state(x_context, x_page)
            
            navigate_to_compose(x_page)
            posted_text = post_to_x(x_page, message_text)
            
            logger.info(f"✅ Phase 2 complete: {posted_text[:50]}...")
            
            print("\n" + "="*60)
            print("✅ PHASES 1 & 2 COMPLETE")
            print(f"Extracted: {len(message_text)} chars")
            print(f"Posted: {len(posted_text)} chars")
            print("="*60 + "\n")
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_phases_1_2_combined()
```

**Pass Criteria:** Message extracted from Facebook and posted to X

---

## Manual Testing Checklist

### Pre-Test Setup
- [ ] `.env` contains valid X credentials
- [ ] Phase 1 tested and working
- [ ] Virtual environment activated

### First Run (No Saved Session)
- [ ] Browser launches
- [ ] Navigates to X login
- [ ] Username field fills
- [ ] Next button clicks
- [ ] Password field appears and fills
- [ ] Login button clicks
- [ ] Login completes successfully
- [ ] `auth_x.json` created
- [ ] `auth_x_session.json` created

### Posting Tests
- [ ] Navigate to compose works
- [ ] Composer field found
- [ ] Text types with delays
- [ ] Post button found and enabled
- [ ] Post button clicks
- [ ] Post appears on X timeline
- [ ] Short message (< 280) posts unchanged
- [ ] Long message (> 280) truncates correctly
- [ ] Truncation logged appropriately

### Second Run (Saved Session)
- [ ] Loads session from `auth_x.json`
- [ ] Skips login flow
- [ ] Posts successfully
- [ ] Completes faster than first run

### Error Scenarios
- [ ] Invalid credentials raise LoginError
- [ ] Missing composer raises PostingError
- [ ] Navigation failures handled
- [ ] Post button disabled handled

---

## Performance Benchmarks

### Expected Timing (First Run)
- X login: 15-30 seconds
- Navigate to compose: 3-5 seconds
- Type message: 5-15 seconds (depends on length)
- Post submission: 2-5 seconds
**Total Phase 2: 25-55 seconds**

### Expected Timing (Cached Session)
- Load session: 2-3 seconds
- Navigate to compose: 3-5 seconds
- Type and post: 7-20 seconds
**Total Phase 2: 12-28 seconds**

---

## ✅ Phase 2 Test Completion Criteria

- [ ] All unit tests pass
- [ ] Text truncation works correctly
- [ ] Multi-step login succeeds
- [ ] Posting works with short messages
- [ ] Posting works with long messages (truncation)
- [ ] Error handling verified
- [ ] End-to-end test completes
- [ ] Phases 1 & 2 work together
- [ ] Manual testing checklist completed
- [ ] Performance benchmarks met

**When all criteria are met, Phase 2 is fully tested!** ✅


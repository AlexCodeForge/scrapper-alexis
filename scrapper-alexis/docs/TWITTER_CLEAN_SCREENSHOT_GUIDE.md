# Twitter Clean Screenshot Guide

Complete guide to capture clean, high-quality Twitter post screenshots by removing unwanted UI elements.

## Table of Contents
- [Overview](#overview)
- [Configuration Setup](#configuration-setup)
- [Element Structure Comparison](#element-structure-comparison)
- [Method 1: Playwright MCP](#method-1-playwright-mcp)
- [Method 2: Python with Playwright](#method-2-python-with-playwright)
- [Key Differences](#key-differences)
- [Troubleshooting](#troubleshooting)

---

## Overview

This guide shows how to capture a Twitter post screenshot containing **only**:
- ✅ Profile picture
- ✅ Display name
- ✅ Username handle
- ✅ Tweet text

**Removed elements:**
- ❌ Timestamp (e.g., "1:24 p. m. · 10 oct. 2025")
- ❌ View count (e.g., "14 Visualizaciones")
- ❌ "Ver las interacciones de post" link
- ❌ All interaction buttons (reply, retweet, like, bookmark, share)
- ❌ Grok button
- ❌ More options button (three dots)

---

## Configuration Setup

### Playwright MCP Config (`~/.cursor/playwright-mcp-config.json`)

```json
{
  "browser": {
    "browserName": "chromium",
    "launchOptions": {
      "headless": false
    },
    "contextOptions": {
      "viewport": {
        "width": 1440,
        "height": 3200
      },
      "deviceScaleFactor": 4,
      "userAgent": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1",
      "locale": "es-ES",
      "httpCredentials": {
        "username": "YOUR_PROXY_USERNAME",
        "password": "YOUR_PROXY_PASSWORD"
      }
    }
  },
  "capabilities": ["core", "tabs", "screenshot", "wait"]
}
```

**Key Settings:**
- `width: 1440, height: 3200` - Larger viewport for better quality
- `deviceScaleFactor: 4` - 4x pixel density for crisp text and images
- Mobile user agent for Twitter's mobile view

---

## Element Structure Comparison

### Original Element (Before Cleaning)

The original Twitter post article element contains:

```html
<article data-testid="tweet">
  <!-- Profile Picture & User Info -->
  <div class="css-175oi2r r-16y2uox r-1wbh5a2 r-1ny4l3l">
    <div class="css-175oi2r">
      <!-- Profile avatar -->
      <div data-testid="Tweet-User-Avatar">...</div>
    </div>
    
    <!-- User name and handle -->
    <div class="css-175oi2r r-1iusvr4 r-16y2uox r-1777fci">
      <div data-testid="User-Name">
        <a href="/soyemizapata">El Emiliano Zapata</a>
        <a href="/soyemizapata">@soyemizapata</a>
      </div>
      
      <!-- ❌ Grok button and More options (TO BE REMOVED) -->
      <div class="css-175oi2r r-zl2h9q">
        <button aria-label="Acciones de Grok">...</button>
        <button aria-label="Más opciones">...</button>
      </div>
    </div>
  </div>
  
  <!-- Tweet Content -->
  <div class="css-175oi2r">
    <div class="css-175oi2r r-1s2bzr4">
      <div data-testid="tweetText">Uno q sea mandilón y q m diga simiamor a todo</div>
    </div>
    
    <!-- ❌ EVERYTHING BELOW SHOULD BE REMOVED -->
    
    <!-- Timestamp and views -->
    <div class="css-175oi2r r-12kyg2d">
      <time>1:24 p. m. · 10 oct. 2025</time>
      <span>14 Visualizaciones</span>
    </div>
    
    <!-- Analytics button -->
    <div class="css-175oi2r r-1awozwy r-1dgieki">
      <a data-testid="analyticsButton">Ver las interacciones de post</a>
    </div>
    
    <!-- Interaction buttons -->
    <div aria-label="14 reproducciones" role="group">
      <button data-testid="reply">...</button>
      <button data-testid="retweet">...</button>
      <button data-testid="like">...</button>
      <button data-testid="bookmark">...</button>
      <button>Compartir post</button>
    </div>
  </div>
</article>
```

### Expected Element (After Cleaning)

```html
<article data-testid="tweet">
  <!-- Profile Picture & User Info -->
  <div class="css-175oi2r r-16y2uox r-1wbh5a2 r-1ny4l3l">
    <div class="css-175oi2r">
      <!-- Profile avatar -->
      <div data-testid="Tweet-User-Avatar">...</div>
    </div>
    
    <!-- User name and handle (Grok + More options buttons removed) -->
    <div class="css-175oi2r r-1iusvr4 r-16y2uox r-1777fci">
      <div data-testid="User-Name">
        <a href="/soyemizapata">El Emiliano Zapata</a>
        <a href="/soyemizapata">@soyemizapata</a>
      </div>
    </div>
  </div>
  
  <!-- Tweet Content ONLY -->
  <div class="css-175oi2r">
    <div class="css-175oi2r r-1s2bzr4">
      <div data-testid="tweetText">Uno q sea mandilón y q m diga simiamor a todo</div>
    </div>
  </div>
</article>
```

**What was removed:**
1. Container with Grok button and More options button (`<div class="css-175oi2r r-zl2h9q">`)
2. All siblings after the tweet text container

---

## Method 1: Playwright MCP

### Step 1: Navigate to Tweet

```javascript
// Navigate to the specific tweet URL
await page.goto('https://twitter.com/USERNAME/status/TWEET_ID');
```

### Step 2: Wait for Page Load

```javascript
// Wait for the page to fully load
await new Promise(f => setTimeout(f, 4000));
```

### Step 3: Clean the DOM (Remove Unwanted Elements)

```javascript
await page.evaluate(() => {
  // Get the tweet article
  const article = document.querySelector('article[data-testid="tweet"]');
  if (!article) return 'Article not found';
  
  // STEP 3A: Remove Grok button and More options button
  const grokButton = article.querySelector('button[aria-label*="Grok"]');
  if (grokButton) grokButton.remove();
  
  const moreButton = article.querySelector('button[aria-label*="Más opciones"]');
  if (moreButton) moreButton.remove();
  
  // Alternative: Remove entire container with both buttons
  const buttonsContainer = article.querySelector('div.css-175oi2r.r-1kkk96v');
  if (buttonsContainer) buttonsContainer.remove();
  
  // STEP 3B: Remove everything after tweet text
  const tweetText = article.querySelector('[data-testid="tweetText"]');
  if (!tweetText) return 'Tweet text not found';
  
  const tweetTextContainer = tweetText.closest('.css-175oi2r.r-1s2bzr4');
  if (!tweetTextContainer) return 'Container not found';
  
  // Remove all sibling elements after the tweet text
  let nextSibling = tweetTextContainer.nextElementSibling;
  while (nextSibling) {
    const toRemove = nextSibling;
    nextSibling = nextSibling.nextElementSibling;
    toRemove.remove();
  }
  
  return 'Cleaned successfully';
});
```

### Step 4: Scroll Tweet Into View

```javascript
await page.evaluate(() => {
  const article = document.querySelector('article[data-testid="tweet"]');
  if (article) {
    article.scrollIntoView({ behavior: 'instant', block: 'center' });
  }
});
```

### Step 5: Take Screenshot of Article Element

```javascript
// Screenshot only the article element (not full page)
await page.getByTestId('tweet').screenshot({
  path: './twitter_clean_screenshot.png',
  scale: 'css',
  type: 'png'
});
```

### Complete MCP Workflow

```javascript
// 1. Navigate
await page.goto('https://twitter.com/soyemizapata/status/1976700451778420746');

// 2. Wait for load
await new Promise(f => setTimeout(f, 4000));

// 3. Clean DOM
await page.evaluate(() => {
  const article = document.querySelector('article[data-testid="tweet"]');
  if (!article) return 'Article not found';
  
  // Remove Grok and More buttons
  const grokButton = article.querySelector('button[aria-label*="Grok"]');
  if (grokButton) grokButton.remove();
  
  const moreButton = article.querySelector('button[aria-label*="Más opciones"]');
  if (moreButton) moreButton.remove();
  
  // Remove everything after tweet text
  const tweetText = article.querySelector('[data-testid="tweetText"]');
  if (tweetText) {
    const container = tweetText.closest('.css-175oi2r.r-1s2bzr4');
    if (container) {
      let next = container.nextElementSibling;
      while (next) {
        const remove = next;
        next = next.nextElementSibling;
        remove.remove();
      }
    }
  }
  
  return 'Cleaned';
});

// 4. Scroll into view
await page.evaluate(() => {
  document.querySelector('article[data-testid="tweet"]')?.scrollIntoView({ 
    behavior: 'instant', 
    block: 'center' 
  });
});

// 5. Wait a moment for rendering
await new Promise(f => setTimeout(f, 1000));

// 6. Take screenshot
await page.getByTestId('tweet').screenshot({
  path: './twitter_clean.png',
  scale: 'css',
  type: 'png'
});
```

---

## Method 2: Python with Playwright

### Installation

```bash
pip install playwright
playwright install chromium
```

### Complete Python Script

```python
from playwright.sync_api import sync_playwright
import time

def capture_clean_twitter_screenshot(tweet_url: str, output_path: str = "twitter_clean.png"):
    """
    Capture a clean Twitter screenshot with only profile, name, handle, and tweet text.
    
    Args:
        tweet_url: Full URL to the tweet (e.g., https://twitter.com/user/status/123456)
        output_path: Path where the screenshot will be saved
    """
    
    with sync_playwright() as p:
        # Launch browser with mobile configuration
        browser = p.chromium.launch(
            headless=False  # Set to True for production
        )
        
        # Create context with high-quality settings
        context = browser.new_context(
            viewport={'width': 1440, 'height': 3200},
            device_scale_factor=4,
            user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            locale='es-ES'
        )
        
        page = context.new_page()
        
        try:
            # Step 1: Navigate to tweet
            print(f"Navigating to {tweet_url}...")
            page.goto(tweet_url, wait_until='domcontentloaded')
            
            # Step 2: Wait for page to load completely
            print("Waiting for page to load...")
            time.sleep(4)
            
            # Step 3: Clean the DOM - Remove unwanted elements
            print("Cleaning DOM elements...")
            clean_result = page.evaluate("""
                () => {
                    // Get the tweet article
                    const article = document.querySelector('article[data-testid="tweet"]');
                    if (!article) return 'Article not found';
                    
                    // Remove Grok button
                    const grokButton = article.querySelector('button[aria-label*="Grok"]');
                    if (grokButton) grokButton.remove();
                    
                    // Remove More options button (three dots)
                    const moreButton = article.querySelector('button[aria-label*="Más opciones"]');
                    if (moreButton) moreButton.remove();
                    
                    // Alternative: Remove entire container with both buttons
                    const buttonsContainer = article.querySelector('div.css-175oi2r.r-1kkk96v');
                    if (buttonsContainer) buttonsContainer.remove();
                    
                    // Remove everything after tweet text
                    const tweetText = article.querySelector('[data-testid="tweetText"]');
                    if (!tweetText) return 'Tweet text not found';
                    
                    const tweetTextContainer = tweetText.closest('.css-175oi2r.r-1s2bzr4');
                    if (!tweetTextContainer) return 'Container not found';
                    
                    // Remove all sibling elements after the tweet text container
                    let nextSibling = tweetTextContainer.nextElementSibling;
                    while (nextSibling) {
                        const toRemove = nextSibling;
                        nextSibling = nextSibling.nextElementSibling;
                        toRemove.remove();
                    }
                    
                    return 'Cleaned successfully';
                }
            """)
            print(f"Clean result: {clean_result}")
            
            # Step 4: Scroll tweet into view
            print("Scrolling tweet into view...")
            page.evaluate("""
                () => {
                    const article = document.querySelector('article[data-testid="tweet"]');
                    if (article) {
                        article.scrollIntoView({ behavior: 'instant', block: 'center' });
                    }
                }
            """)
            
            # Wait for smooth rendering
            time.sleep(1)
            
            # Step 5: Take screenshot of only the article element
            print("Taking screenshot...")
            article = page.locator('article[data-testid="tweet"]')
            article.screenshot(path=output_path, type='png')
            
            print(f"✅ Screenshot saved to: {output_path}")
            
        except Exception as e:
            print(f"❌ Error: {e}")
            raise
        
        finally:
            browser.close()


# Usage example
if __name__ == "__main__":
    tweet_url = "https://twitter.com/soyemizapata/status/1976700451778420746"
    capture_clean_twitter_screenshot(tweet_url, "twitter_clean_output.png")
```

### Alternative: Async Python Version

```python
from playwright.async_api import async_playwright
import asyncio

async def capture_clean_twitter_screenshot_async(tweet_url: str, output_path: str = "twitter_clean.png"):
    """
    Async version for better performance when processing multiple tweets.
    """
    
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=False)
        
        context = await browser.new_context(
            viewport={'width': 1440, 'height': 3200},
            device_scale_factor=4,
            user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            locale='es-ES'
        )
        
        page = await context.new_page()
        
        try:
            # Navigate
            await page.goto(tweet_url, wait_until='domcontentloaded')
            await asyncio.sleep(4)
            
            # Clean DOM
            await page.evaluate("""
                () => {
                    const article = document.querySelector('article[data-testid="tweet"]');
                    if (!article) return;
                    
                    // Remove buttons
                    article.querySelector('button[aria-label*="Grok"]')?.remove();
                    article.querySelector('button[aria-label*="Más opciones"]')?.remove();
                    
                    // Remove everything after tweet text
                    const tweetText = article.querySelector('[data-testid="tweetText"]');
                    if (tweetText) {
                        const container = tweetText.closest('.css-175oi2r.r-1s2bzr4');
                        if (container) {
                            let next = container.nextElementSibling;
                            while (next) {
                                const remove = next;
                                next = next.nextElementSibling;
                                remove.remove();
                            }
                        }
                    }
                }
            """)
            
            # Scroll and screenshot
            await page.evaluate("() => document.querySelector('article[data-testid=\"tweet\"]')?.scrollIntoView({ behavior: 'instant', block: 'center' })")
            await asyncio.sleep(1)
            
            article = page.locator('article[data-testid="tweet"]')
            await article.screenshot(path=output_path, type='png')
            
            print(f"✅ Screenshot saved: {output_path}")
            
        finally:
            await browser.close()


# Usage
asyncio.run(capture_clean_twitter_screenshot_async(
    "https://twitter.com/soyemizapata/status/1976700451778420746",
    "twitter_clean_async.png"
))
```

---

## Key Differences

### Playwright MCP vs Python Playwright

| Aspect | Playwright MCP | Python Playwright |
|--------|----------------|-------------------|
| **Setup** | Config file in `~/.cursor/playwright-mcp-config.json` | Context options in code |
| **Syntax** | JavaScript/TypeScript in tool calls | Python with async/sync API |
| **Use Case** | Interactive debugging, Cursor integration | Automation scripts, batch processing |
| **Screenshot Method** | `page.getByTestId('tweet').screenshot()` | `page.locator('[data-testid="tweet"]').screenshot()` |
| **Wait Method** | `await new Promise(f => setTimeout(f, ms))` | `time.sleep(seconds)` or `await asyncio.sleep()` |

### Critical Settings for Quality

Both methods require:

```javascript
{
  viewport: { width: 1440, height: 3200 },  // Large viewport
  deviceScaleFactor: 4,                      // 4x pixel density
  userAgent: "Mobile Safari iOS 16",         // Mobile view
}
```

Without these settings, the screenshot will be:
- ❌ Lower resolution
- ❌ Desktop layout (different structure)
- ❌ Blurry text and images

---

## Troubleshooting

### Issue 1: Elements Not Found

**Problem:** `querySelector` returns `null`

**Solution:** Wait longer for page load or check selector:

```javascript
// Add explicit wait for element
await page.waitForSelector('article[data-testid="tweet"]', { timeout: 10000 });
```

### Issue 2: Partial Screenshot (Side Elements Visible)

**Problem:** Screenshot includes sidebar or navigation

**Solution:** Ensure scrolling into view before screenshot:

```javascript
await page.evaluate(() => {
  document.querySelector('article[data-testid="tweet"]')?.scrollIntoView({ 
    behavior: 'instant', 
    block: 'center' 
  });
});
await new Promise(f => setTimeout(f, 1000)); // Wait for scroll animation
```

### Issue 3: Low Quality / Blurry Screenshot

**Problem:** Text appears pixelated

**Solution:** Increase `deviceScaleFactor`:

```javascript
// Increase from 3 to 4 (or even 5)
deviceScaleFactor: 4
```

### Issue 4: Wrong Language / Different Button Labels

**Problem:** Buttons have different `aria-label` in other languages

**Solution:** Update selectors for your locale:

```javascript
// English
const moreButton = article.querySelector('button[aria-label*="More"]');

// Spanish  
const moreButton = article.querySelector('button[aria-label*="Más opciones"]');

// Or use data-testid (language-independent)
const moreButton = article.querySelector('button[data-testid="caret"]');
```

### Issue 5: DOM Structure Changed

**Problem:** Twitter updated their HTML structure

**Solution:** Inspect the current structure and update selectors:

```javascript
// Fallback approach: Remove by class pattern
const unwantedDivs = article.querySelectorAll('.css-175oi2r.r-12kyg2d');
unwantedDivs.forEach(div => div.remove());
```

---

## Example Output

### Before Cleaning
- Profile picture ✓
- Name ✓
- Handle ✓
- Tweet text ✓
- Timestamp ✗
- View count ✗
- Interaction buttons ✗
- Grok button ✗
- More options ✗

### After Cleaning
- Profile picture ✓
- Name ✓
- Handle ✓
- Tweet text ✓

**Result:** Clean, minimal screenshot perfect for presentations or documentation!

---

## Complete Integration Example

### Python Script with Error Handling

```python
from playwright.sync_api import sync_playwright, TimeoutError
import time
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def capture_twitter_clean_screenshot(
    tweet_url: str,
    output_path: str = "twitter_clean.png",
    timeout: int = 30000,
    headless: bool = False
) -> bool:
    """
    Capture clean Twitter screenshot with comprehensive error handling.
    
    Returns:
        bool: True if successful, False otherwise
    """
    
    with sync_playwright() as p:
        try:
            # Launch browser
            browser = p.chromium.launch(headless=headless)
            
            # Configure context for high-quality mobile screenshots
            context = browser.new_context(
                viewport={'width': 1440, 'height': 3200},
                device_scale_factor=4,
                user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
                locale='es-ES'
            )
            
            page = context.new_page()
            
            # Navigate with timeout
            logger.info(f"Navigating to {tweet_url}")
            page.goto(tweet_url, wait_until='domcontentloaded', timeout=timeout)
            
            # Wait for tweet article to appear
            logger.info("Waiting for tweet to load...")
            page.wait_for_selector('article[data-testid="tweet"]', timeout=timeout)
            time.sleep(2)  # Additional wait for dynamic content
            
            # Clean the DOM
            logger.info("Cleaning unwanted elements...")
            clean_script = """
                () => {
                    const article = document.querySelector('article[data-testid="tweet"]');
                    if (!article) throw new Error('Tweet article not found');
                    
                    // Remove action buttons
                    const grokBtn = article.querySelector('button[aria-label*="Grok"], button[aria-label*="Acciones de Grok"]');
                    if (grokBtn) grokBtn.remove();
                    
                    const moreBtn = article.querySelector('button[aria-label*="More"], button[aria-label*="Más opciones"]');
                    if (moreBtn) moreBtn.remove();
                    
                    // Remove timestamp, stats, interactions
                    const tweetText = article.querySelector('[data-testid="tweetText"]');
                    if (!tweetText) throw new Error('Tweet text not found');
                    
                    const textContainer = tweetText.closest('.css-175oi2r.r-1s2bzr4');
                    if (textContainer) {
                        let sibling = textContainer.nextElementSibling;
                        while (sibling) {
                            const toRemove = sibling;
                            sibling = sibling.nextElementSibling;
                            toRemove.remove();
                        }
                    }
                    
                    return 'Success';
                }
            """
            
            result = page.evaluate(clean_script)
            logger.info(f"DOM cleaning result: {result}")
            
            # Scroll tweet into view
            page.evaluate("""
                () => {
                    const article = document.querySelector('article[data-testid="tweet"]');
                    if (article) {
                        article.scrollIntoView({ behavior: 'instant', block: 'center' });
                    }
                }
            """)
            time.sleep(1)
            
            # Capture screenshot
            logger.info(f"Capturing screenshot to {output_path}")
            article_locator = page.locator('article[data-testid="tweet"]')
            article_locator.screenshot(path=output_path, type='png')
            
            logger.info("✅ Screenshot captured successfully!")
            return True
            
        except TimeoutError:
            logger.error(f"❌ Timeout waiting for page or element (timeout={timeout}ms)")
            return False
        except Exception as e:
            logger.error(f"❌ Error capturing screenshot: {e}")
            return False
        finally:
            browser.close()


if __name__ == "__main__":
    success = capture_twitter_clean_screenshot(
        tweet_url="https://twitter.com/soyemizapata/status/1976700451778420746",
        output_path="twitter_final_clean.png",
        headless=False
    )
    
    if success:
        print("Screenshot saved successfully!")
    else:
        print("Failed to capture screenshot.")
```

---

## Summary

1. **Configure high-quality viewport**: 1440x3200 with 4x scale factor
2. **Navigate to tweet URL** and wait for full load
3. **Remove unwanted elements** via DOM manipulation:
   - Grok button
   - More options button
   - Everything after tweet text (timestamp, stats, interactions)
4. **Scroll tweet into view** for clean framing
5. **Screenshot only the article element** (not full page)

The result is a clean, professional screenshot with only the essential tweet content! 🎯


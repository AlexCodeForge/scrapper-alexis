# Twitter Mobile Screenshot Guide
## Creating Clean Tweet Screenshots in Spanish Mobile View

### Overview

This guide explains how to capture clean, mobile-style screenshots of Twitter posts showing only:
- Profile picture
- Display name
- Username (@handle)
- Tweet text

All buttons, timestamps, views, and navigation elements are removed for a clean presentation.

---

## Configuration Requirements

### 1. Mobile Viewport Settings

To get the authentic mobile view, configure these viewport settings:

- **Width**: 390px (iPhone size)
- **Height**: 844px
- **User Agent**: iPhone Safari
- **Locale**: Spanish (es-ES)

### 2. Proxy Configuration

If using a proxy (recommended for automation):

```json
{
  "server": "http://proxy_ip:proxy_port",
  "httpCredentials": {
    "username": "proxy_username",
    "password": "proxy_password"
  }
}
```

---

## Method 1: Using Playwright MCP

### Step 1: Configure Playwright MCP

Edit `~/.cursor/playwright-mcp-config.json`:

```json
{
  "browser": {
    "browserName": "chromium",
    "launchOptions": {
      "headless": false
    },
    "contextOptions": {
      "viewport": {
        "width": 390,
        "height": 844
      },
      "userAgent": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1",
      "locale": "es-ES",
      "httpCredentials": {
        "username": "your_proxy_username",
        "password": "your_proxy_password"
      }
    }
  },
  "capabilities": ["core", "tabs", "screenshot", "wait"]
}
```

Edit `~/.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "Playwright": {
      "command": "npx",
      "args": [
        "-y",
        "@playwright/mcp@latest",
        "--config=C:/Users/YourUsername/.cursor/playwright-mcp-config.json",
        "--proxy-server=http://proxy_ip:proxy_port"
      ]
    }
  }
}
```

**Important**: Restart Cursor/VS Code after making these changes!

### Step 2: Navigate to Tweet

```javascript
// Navigate to the tweet URL
await page.goto('https://x.com/username/status/tweet_id');

// Wait for content to load
await page.wait_for_load_state('networkidle');
```

### Step 3: Remove Unwanted Elements

Use JavaScript to remove specific DOM elements:

```javascript
const article = document.querySelector('article');
if (!article) return 'no article';

// 1. Remove Grok actions button
const grokButton = article.querySelector('button[aria-label*="Acciones de Grok"]');
if (grokButton) grokButton.closest('[class*="r-1kkk96v"]')?.remove();

// 2. Remove "More options" button
const moreButton = article.querySelector('button[aria-label*="Más opciones"]');
if (moreButton) moreButton.parentElement?.remove();

// 3. Remove timestamp and views section
const timeLink = article.querySelector('time');
if (timeLink) timeLink.closest('[class*="r-12kyg2d"]')?.remove();

// 4. Remove "View interactions" link
const analyticsLink = article.querySelector('a[data-testid="analyticsButton"]');
if (analyticsLink) analyticsLink.remove();

// 5. Remove action buttons (reply, retweet, like, etc.)
const actionButtons = article.querySelector('[role="group"]');
if (actionButtons) actionButtons.parentElement?.remove();

// 6. Style the article
article.style.backgroundColor = 'white';
article.style.padding = '16px';
```

### Step 4: Take Screenshot

```javascript
// Screenshot just the article element
await page.getByTestId('tweet').screenshot({
  path: 'twitter_mobile_clean.png',
  scale: 'css',
  type: 'png'
});
```

---

## Method 2: Python Playwright Script

### Installation

```bash
pip install playwright
playwright install chromium
```

### Complete Python Script

```python
"""
Clean Twitter Mobile Screenshot Script
Captures tweet with only profile, name, username, and text
"""
import asyncio
from playwright.async_api import async_playwright

async def capture_clean_tweet(tweet_url: str, output_path: str = 'tweet_screenshot.png'):
    """
    Capture a clean mobile screenshot of a Twitter post
    
    Args:
        tweet_url: Full URL to the tweet
        output_path: Path where screenshot will be saved
    """
    async with async_playwright() as p:
        # Launch browser with proxy if needed
        browser = await p.chromium.launch(
            headless=False,  # Set to True for production
            proxy={
                "server": "http://proxy_ip:proxy_port",  # Optional
            }
        )
        
        # Create context with mobile viewport and Spanish locale
        context = await browser.new_context(
            viewport={'width': 390, 'height': 844},
            user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            locale='es-ES',
            http_credentials={  # Optional, for proxy auth
                'username': 'proxy_username',
                'password': 'proxy_password'
            }
        )
        
        page = await context.new_page()
        
        # Navigate to tweet
        await page.goto(tweet_url)
        await page.wait_for_load_state('networkidle')
        
        # Wait for article to load
        await page.wait_for_selector('article', timeout=10000)
        
        # Remove unwanted elements
        await page.evaluate("""
            () => {
                const article = document.querySelector('article');
                if (!article) return;
                
                // Remove Grok button
                const grokButton = article.querySelector('button[aria-label*="Acciones de Grok"]');
                if (grokButton) grokButton.closest('[class*="r-1kkk96v"]')?.remove();
                
                // Remove More options button
                const moreButton = article.querySelector('button[aria-label*="Más opciones"]');
                if (moreButton) moreButton.parentElement?.remove();
                
                // Remove timestamp/views section
                const timeLink = article.querySelector('time');
                if (timeLink) timeLink.closest('[class*="r-12kyg2d"]')?.remove();
                
                // Remove "View interactions" link
                const analyticsLink = article.querySelector('a[data-testid="analyticsButton"]');
                if (analyticsLink) analyticsLink.remove();
                
                // Remove action buttons group
                const actionButtons = article.querySelector('[role="group"]');
                if (actionButtons) actionButtons.parentElement?.remove();
                
                // Style article
                article.style.backgroundColor = 'white';
                article.style.padding = '16px';
            }
        """)
        
        # Small delay to ensure DOM updates
        await page.wait_for_timeout(500)
        
        # Take screenshot of just the article
        article = await page.query_selector('article')
        await article.screenshot(path=output_path)
        
        print(f"✅ Screenshot saved: {output_path}")
        
        await browser.close()


# Example usage
if __name__ == "__main__":
    tweet_url = "https://x.com/soyemizapata/status/1976700451778420746"
    asyncio.run(capture_clean_tweet(tweet_url, 'clean_tweet.png'))
```

### Without Proxy

If you don't need a proxy, remove the proxy and http_credentials:

```python
browser = await p.chromium.launch(headless=False)

context = await browser.new_context(
    viewport={'width': 390, 'height': 844},
    user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
    locale='es-ES'
)
```

---

## Elements Removed (Technical Details)

### DOM Structure Changes

**Before** (Full tweet with all elements):
```html
<article>
  <div>Profile Avatar + Name/Username</div>
  <div>Grok Button</div>           <!-- ❌ REMOVED -->
  <div>More Options Button</div>    <!-- ❌ REMOVED -->
  <div>Tweet Text</div>
  <div>Timestamp + Views</div>      <!-- ❌ REMOVED -->
  <div>View Interactions Link</div> <!-- ❌ REMOVED -->
  <div role="group">                <!-- ❌ REMOVED -->
    Reply, Retweet, Like, Bookmark, Share buttons
  </div>
</article>
```

**After** (Clean tweet):
```html
<article style="background: white; padding: 16px;">
  <div>Profile Avatar</div>
  <div>El Emiliano Zapata</div>
  <div>@soyemizapata</div>
  <div>Uno q sea mandilón y q m diga simiamor a todo</div>
</article>
```

### CSS Selectors Reference

| Element | Selector | Removal Method |
|---------|----------|----------------|
| Grok Button | `button[aria-label*="Acciones de Grok"]` | Remove closest `[class*="r-1kkk96v"]` |
| More Options | `button[aria-label*="Más opciones"]` | Remove parent element |
| Timestamp/Views | `time` element | Remove closest `[class*="r-12kyg2d"]` |
| View Interactions | `a[data-testid="analyticsButton"]` | Direct remove |
| Action Buttons | `[role="group"]` | Remove parent element |

---

## Troubleshooting

### Issue: Elements Not Found

**Problem**: Selectors don't work because Twitter changed their HTML structure.

**Solution**: Inspect the tweet in browser DevTools and update selectors:
1. Open browser DevTools (F12)
2. Right-click the element you want to remove
3. Copy the selector or aria-label
4. Update the script

### Issue: Screenshot is Blank

**Problem**: Article has no content after removing elements.

**Solution**: Remove elements more carefully - check if you're removing too much:
```python
# Add logging to see what's being removed
print(f"Article HTML before: {await article.inner_html()}")
# ... remove elements ...
print(f"Article HTML after: {await article.inner_html()}")
```

### Issue: Not Mobile View

**Problem**: Page loads in desktop mode.

**Solution**: 
1. Verify viewport settings are correct (390x844)
2. Ensure User-Agent string includes "Mobile"
3. Restart browser/context after changing settings

### Issue: Proxy Authentication Fails

**Problem**: Getting 407 Proxy Authentication Required.

**Solution**:
1. For MCP: Use `httpCredentials` in `contextOptions`, not in proxy URL
2. For Python: Use `http_credentials` parameter in `new_context()`
3. Test proxy credentials separately first

---

## Best Practices

### 1. Error Handling

```python
try:
    await page.wait_for_selector('article', timeout=10000)
except TimeoutError:
    print("❌ Tweet failed to load")
    return None
```

### 2. Rate Limiting

```python
import time

for tweet_url in tweet_urls:
    await capture_clean_tweet(tweet_url)
    time.sleep(2)  # Wait 2 seconds between requests
```

### 3. Screenshot Optimization

```python
# For better quality
await article.screenshot(
    path=output_path,
    type='png',
    scale='css',  # Use CSS pixels (sharper on retina displays)
)
```

### 4. Batch Processing

```python
async def process_multiple_tweets(tweet_urls: list):
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(...)
        page = await context.new_page()
        
        for i, url in enumerate(tweet_urls):
            try:
                await page.goto(url)
                # ... process tweet ...
                await article.screenshot(path=f'tweet_{i}.png')
            except Exception as e:
                print(f"Failed {url}: {e}")
        
        await browser.close()
```

---

## Output Examples

### Mobile View Screenshot
- **Width**: ~374px (390px viewport minus padding)
- **Height**: ~147px (varies by tweet length)
- **Format**: PNG
- **Background**: White
- **Padding**: 16px

### File Naming Convention

```python
# Include timestamp and username
from datetime import datetime

timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
username = tweet_url.split('/')[-3]
filename = f"{timestamp}_{username}_tweet.png"
```

---

## Integration with Existing Projects

### Add to Facebook Scraper Project

```python
# In your facebook_extractor.py or similar
from twitter_screenshot import capture_clean_tweet

async def extract_social_media_post(url: str):
    if 'twitter.com' in url or 'x.com' in url:
        return await capture_clean_tweet(url)
    elif 'facebook.com' in url:
        return await capture_facebook_post(url)
```

### Use with Relay Agent

```python
# In relay_agent.py
async def process_tweet_url(tweet_url: str):
    screenshot_path = f"screenshots/tweet_{datetime.now().timestamp()}.png"
    await capture_clean_tweet(tweet_url, screenshot_path)
    return screenshot_path
```

---

## Advanced: Customization Options

### Change Language

```python
# For English tweets
context = await browser.new_context(
    locale='en-US',  # Change to English
    ...
)
```

### Different Mobile Devices

```python
# iPhone 14 Pro Max
viewport={'width': 430, 'height': 932}

# iPhone SE
viewport={'width': 375, 'height': 667}

# Pixel 7
viewport={'width': 412, 'height': 915}
```

### Custom Styling

```javascript
// Add custom styles to the article
article.style.backgroundColor = '#f0f0f0';  // Light gray
article.style.padding = '20px';
article.style.borderRadius = '12px';
article.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
```

---

## Security Considerations

1. **Never commit credentials**: Use environment variables
   ```python
   import os
   http_credentials={
       'username': os.getenv('PROXY_USERNAME'),
       'password': os.getenv('PROXY_PASSWORD')
   }
   ```

2. **Rate limiting**: Don't scrape too aggressively
   - Max 1 request per 2 seconds
   - Use delays between batches

3. **Respect robots.txt**: Check Twitter's scraping policies

4. **Use authentication**: For Twitter API access when possible

---

## Testing Checklist

- [ ] Mobile viewport loads correctly (390px width)
- [ ] Spanish language is displayed
- [ ] Profile picture visible
- [ ] Display name visible
- [ ] Username (@handle) visible
- [ ] Tweet text visible and complete
- [ ] No Grok button
- [ ] No "More options" button  
- [ ] No timestamp
- [ ] No view count
- [ ] No action buttons (reply, retweet, like, etc.)
- [ ] Clean white background
- [ ] Proper padding applied
- [ ] Screenshot saved successfully

---

## References

- [Playwright Python Documentation](https://playwright.dev/python/)
- [Playwright MCP Documentation](https://github.com/microsoft/playwright-mcp)
- [Twitter/X Mobile Web Viewport Sizes](https://www.whatismybrowser.com/guides/the-latest-user-agent/twitter)

---

**Last Updated**: October 10, 2025  
**Tested With**: Playwright 1.40+, Playwright MCP latest, Python 3.11+




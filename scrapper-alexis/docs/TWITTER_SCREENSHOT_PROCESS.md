# Twitter Screenshot Process Documentation

## Overview
This document explains the complete process of creating clean, high-quality screenshots of Twitter posts using Playwright MCP and a custom HTML template.

---

## Problem Statement
The goal was to capture a clean screenshot of a Twitter post without:
- Engagement buttons (like, retweet, share)
- Timestamps and view counts
- Analytics buttons
- Extra UI elements
- Large white spaces

And with:
- High quality/resolution
- Clean design matching Twitter's aesthetic
- Proper font rendering
- Sharp profile images

---

## Initial Approaches (What Didn't Work)

### Approach 1: Direct Screenshot from Live Page
**Method:** Navigate to Twitter and screenshot the tweet element directly.
**Problem:** 
- Captured too many unwanted UI elements
- Dynamic content made element references unstable
- TimeoutErrors due to page changes

### Approach 2: DOM Manipulation on Live Page
**Method:** Use `browser_evaluate` to hide unwanted elements via JavaScript, then screenshot.
**Problem:**
- Elements kept reappearing due to Twitter's React rendering
- Element references (`ref`) became stale/invalid
- Unreliable and inconsistent results

### Approach 3: Extract HTML and Create Standalone Template ✅
**Method:** Extract the tweet HTML and CSS, create a standalone HTML file, screenshot that.
**Result:** **SUCCESS** - This is the final solution!

---

## The Solution: Standalone HTML Template

### Why It Works
1. **No external interference** - The HTML file is static and controlled
2. **No dynamic re-rendering** - Content stays exactly as we define it
3. **Full control over styling** - We can adjust fonts, sizes, spacing
4. **Scalable** - Easy to increase resolution by scaling all dimensions
5. **Reusable** - Template can be used for any tweet

---

## MCP Configuration

### 1. MCP Server Configuration (`C:/Users/Alex/.cursor/mcp.json`)

```json
{
  "mcpServers": {
    "Playwright": {
      "command": "npx",
      "args": [
        "-y",
        "@playwright/mcp@latest",
        "--config=C:/Users/Alex/.cursor/playwright-mcp-config.json",
        "--proxy-server=http://77.47.156.7:50100"
      ]
    }
  }
}
```

**Key Points:**
- Uses `@playwright/mcp@latest` for browser automation
- References custom config file for browser settings
- Includes proxy server for network routing

### 2. Playwright Browser Configuration (`C:/Users/Alex/.cursor/playwright-mcp-config.json`)

```json
{
  "browser": {
    "browserName": "chromium",
    "launchOptions": {
      "headless": false,
      "args": ["--window-size=1280,800"]
    },
    "contextOptions": {
      "viewport": {
        "width": 1280,
        "height": 800
      },
      "deviceScaleFactor": 1,
      "isMobile": false,
      "hasTouch": false,
      "userAgent": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
      "locale": "es-ES",
      "httpCredentials": {
        "username": "gNhwRLuC",
        "password": "OZ7h82Gknc"
      }
    }
  },
  "capabilities": ["core", "tabs", "screenshot", "wait"]
}
```

**Key Configuration Details:**
- **Browser:** Chromium (stable, well-supported)
- **Headless:** `false` (visible browser for debugging)
- **Viewport:** 1280x800 (standard desktop size)
- **deviceScaleFactor:** 1 (no initial scaling)
- **Locale:** `es-ES` (Spanish for Twitter content)
- **HTTP Credentials:** For proxy authentication
- **Capabilities:** Core features + tabs + screenshot + wait functionality

---

## The HTML Template (`tweet_template.html`)

### Template Structure

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tweet</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: white;
        }
        .tweet-container {
            width: fit-content;
            max-width: 2200px;
            padding: 64px;
            background-color: white;
            box-sizing: border-box;
        }
        .tweet-header {
            display: flex;
            margin-bottom: 48px;
        }
        .profile-picture {
            width: 256px;
            height: 256px;
            border-radius: 50%;
            margin-right: 48px;
            flex-shrink: 0;
        }
        .user-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .display-name {
            font-size: 68px;
            font-weight: 700;
            color: #0F1419;
            line-height: 80px;
        }
        .username {
            font-size: 68px;
            font-weight: 400;
            color: #536471;
            line-height: 80px;
        }
        .tweet-text {
            font-size: 68px;
            font-weight: 400;
            color: #0F1419;
            line-height: 96px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            padding-left: 0;
        }
    </style>
</head>
<body>
    <div class="tweet-container" id="tweet">
        <div class="tweet-header">
            <img src="https://pbs.twimg.com/profile_images/1958793949692260352/PAlLs8Va_400x400.jpg" alt="El Emiliano Zapata" class="profile-picture">
            <div class="user-info">
                <span class="display-name">El Emiliano Zapata</span>
                <span class="username">@soyemizapata</span>
            </div>
        </div>
        <p class="tweet-text">Uno q sea mandilón y q m diga simiamor a todo</p>
    </div>
</body>
</html>
```

### Template Design Decisions

#### 1. **Scaling Strategy (4x Scale)**
All dimensions are **4x the original Twitter dimensions**:

| Element | Original Size | Template Size (4x) |
|---------|--------------|-------------------|
| Profile Picture | 64px | **256px** |
| Font Sizes | 17px | **68px** |
| Padding | 16px | **64px** |
| Margins | 12px | **48px** |
| Line Heights | 20-24px | **80-96px** |

**Why 4x?** 
- Creates high-resolution output
- Maintains exact proportions
- Looks professional and sharp
- Nobody notices it's "zoomed" - just looks like high quality

#### 2. **Font Stack**
```css
font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```

**Why this font stack?**
- `system-ui` - Modern standard, uses OS native font
- `-apple-system` - Apple devices (Safari)
- `BlinkMacSystemFont` - Chrome/Edge on macOS
- `"Segoe UI"` - Windows
- `Roboto` - Android
- Fallbacks to common sans-serif fonts

This matches Twitter's actual font rendering across platforms.

#### 3. **High-Quality Profile Image**
```html
<img src="https://pbs.twimg.com/profile_images/.../PAlLs8Va_400x400.jpg">
```

**Critical:** Use `_400x400.jpg` **NOT** `_normal.jpg`
- `_normal.jpg` = 48x48px (blurry when scaled)
- `_400x400.jpg` = 400x400px (sharp and crisp)

**How to get the high-quality URL:**
1. Navigate to the user's Twitter profile
2. Right-click profile image → Inspect
3. Find the image URL with `_normal.jpg`
4. Replace `_normal.jpg` with `_400x400.jpg`

#### 4. **Layout Structure**
```
.tweet-container
  ├── .tweet-header (flexbox)
  │   ├── .profile-picture (left)
  │   └── .user-info (right)
  │       ├── .display-name
  │       └── .username
  └── .tweet-text (aligned with container, not header)
```

**Key:** Tweet text has `padding-left: 0` to align with the left edge of the avatar, not indented.

#### 5. **Colors (Twitter Official)**
- Text: `#0F1419` (almost black)
- Secondary text (username): `#536471` (gray)
- Background: `white`

---

## Step-by-Step Process

### Step 1: Prepare the HTML Template

1. Copy `tweet_template.html` to your project root
2. Update the following fields for your target tweet:
   - `<img src="...">` - Profile image URL (use `_400x400.jpg` version)
   - `.display-name` content - User's display name
   - `.username` content - User's @handle
   - `.tweet-text` content - The tweet text

**Example:**
```html
<img src="https://pbs.twimg.com/profile_images/YOUR_IMAGE_ID/YOUR_IMAGE_NAME_400x400.jpg">
<span class="display-name">Your Name</span>
<span class="username">@yourhandle</span>
<p class="tweet-text">Your tweet content here</p>
```

### Step 2: Navigate to the Template with Playwright MCP

Use the `browser_navigate` tool:

```javascript
await page.goto('file:///C:/Users/Alex/Desktop/alexis%20scrapper/tweet_template.html');
```

**Important:** 
- Use `file:///` protocol for local files
- URL-encode spaces as `%20`
- Use absolute paths

### Step 3: Wait for Content to Load

Use the `browser_wait_for` tool to ensure fonts and images load:

```javascript
await new Promise(f => setTimeout(f, 2 * 1000)); // Wait 2 seconds
```

**Why wait?**
- Profile image needs time to download (especially 400x400 version)
- Fonts need to load
- Prevents capturing half-loaded content

### Step 4: Take Element Screenshot

Use `browser_take_screenshot` targeting the tweet container:

```javascript
await page.getByText('El Emiliano Zapata @soyemizapata ...').screenshot({
  path: 'screenshots/tweet.png',
  scale: 'css',
  type: 'png'
});
```

**Tips:**
- Target the container element using its text content
- Use `type: 'png'` for best quality (not JPEG)
- `scale: 'css'` respects CSS pixel ratios

### Step 5: Copy Screenshot to Destination

```bash
copy "source_path" "destination_path"
```

---

## Complete MCP Tool Call Sequence

### Example Session:

```javascript
// 1. Navigate to template
mcp_Playwright_browser_navigate({
  url: "file:///C:/Users/Alex/Desktop/alexis%20scrapper/tweet_template.html"
})

// 2. Wait for content to load
mcp_Playwright_browser_wait_for({
  time: 2
})

// 3. Take snapshot to get element references
mcp_Playwright_browser_snapshot()

// 4. Screenshot the tweet container element
mcp_Playwright_browser_take_screenshot({
  filename: "screenshots/tweet_final.png",
  element: "tweet container",
  ref: "e2",  // From snapshot
  type: "png"
})

// 5. Copy to final destination (via terminal)
run_terminal_cmd({
  command: "copy \"source\" \"destination\"",
  is_background: false
})
```

---

## Tips & Tricks

### 1. The "Old Trick" - Scaling for Quality
Want bigger, higher quality screenshots? Scale everything proportionally!

**Original dimensions (1x):**
```css
.profile-picture { width: 64px; height: 64px; }
.display-name { font-size: 17px; }
```

**High quality (4x):**
```css
.profile-picture { width: 256px; height: 256px; }
.display-name { font-size: 68px; }
```

**Rule:** Multiply EVERYTHING by the same factor:
- Widths, heights
- Font sizes
- Paddings, margins
- Line heights

**Result:** Same proportions, much higher resolution!

### 2. Profile Image Quality
Always use the highest resolution available:
- ❌ `_normal.jpg` (48x48) - Blurry
- ❌ `_bigger.jpg` (73x73) - Still pixelated
- ✅ `_400x400.jpg` (400x400) - Crystal clear
- ✅ Original (remove all suffixes) - Highest possible (if available)

### 3. Font Rendering
Use `system-ui` first in the font stack - it provides the most native-looking text across all platforms.

### 4. Dealing with TimeoutErrors
If screenshots timeout:
1. Add `browser_wait_for` with 2-3 seconds
2. Increase the wait time if images are large
3. Check that the profile image URL is accessible

### 5. Text Alignment
To match Twitter's layout:
- Tweet text should align with the LEFT edge of the avatar
- Set `padding-left: 0` on `.tweet-text`
- Don't indent the text under the username

### 6. Container Width
Use `width: fit-content` with `max-width` to:
- Wrap tightly around content (no extra right space)
- Prevent overflow on long tweets
- Keep the screenshot compact

---

## Troubleshooting

### Problem: Blurry Profile Picture
**Solution:** Change `_normal.jpg` to `_400x400.jpg` in the image URL

### Problem: Screenshot Too Small
**Solution:** Scale all dimensions by 2x, 3x, or 4x (multiply every number)

### Problem: Wrong Font
**Solution:** Ensure `system-ui` is first in the font-family stack

### Problem: Too Much White Space on Right
**Solution:** Change `.tweet-container` width to `fit-content`

### Problem: Text Not Aligned with Avatar
**Solution:** Set `.tweet-text { padding-left: 0; }`

### Problem: TimeoutError on Screenshot
**Solution:** Add `browser_wait_for({ time: 2 })` before screenshot

### Problem: Element Reference Invalid
**Solution:** Take a fresh `browser_snapshot` before screenshotting

---

## File Structure

```
project/
├── tweet_template.html          # The HTML template (keep this!)
├── screenshots/                 # Output directory
│   └── tweet_final.png         # Final screenshot
├── .playwright-mcp/            # Playwright working directory
│   └── screenshots/            # Temp screenshots
└── docs/
    └── TWITTER_SCREENSHOT_PROCESS.md  # This file
```

---

## Customization Guide

### To Screenshot a Different Tweet:

1. **Get the tweet details:**
   - Display name
   - Username (@handle)
   - Tweet text
   - Profile image ID

2. **Update `tweet_template.html`:**
   ```html
   <img src="https://pbs.twimg.com/profile_images/YOUR_ID/YOUR_NAME_400x400.jpg">
   <span class="display-name">New Name</span>
   <span class="username">@newhandle</span>
   <p class="tweet-text">New tweet text here</p>
   ```

3. **Run the screenshot process:**
   - Navigate to template
   - Wait 2 seconds
   - Screenshot
   - Copy to destination

### To Adjust Size/Quality:

Change the scale factor in all CSS dimensions:

**For 2x (smaller):**
```css
.profile-picture { width: 128px; height: 128px; }
.display-name { font-size: 34px; }
.tweet-text { font-size: 34px; line-height: 48px; }
/* ... etc, divide current values by 2 */
```

**For 6x (larger):**
```css
.profile-picture { width: 384px; height: 384px; }
.display-name { font-size: 102px; }
.tweet-text { font-size: 102px; line-height: 144px; }
/* ... etc, multiply current values by 1.5 */
```

---

## Summary

**What We Learned:**
1. ✅ Direct DOM manipulation on live pages is unreliable
2. ✅ Standalone HTML templates provide full control
3. ✅ Scaling all dimensions proportionally creates high-quality output
4. ✅ Use `_400x400.jpg` profile images for sharp rendering
5. ✅ Wait for content to load before screenshotting
6. ✅ Use `system-ui` font for native rendering

**The Winning Formula:**
```
Standalone HTML Template + 4x Scaling + High-Res Images + Playwright MCP = Perfect Screenshots
```

---

## Additional Resources

- **Playwright MCP Documentation:** https://github.com/playwright/mcp
- **Twitter Image URLs:** Pattern is `https://pbs.twimg.com/profile_images/{ID}/{NAME}_{SIZE}.jpg`
- **CSS System Fonts:** https://systemfontstack.com/

---

**Last Updated:** October 11, 2025  
**Template Version:** 4x Scale (256px avatar, 68px fonts)  
**Status:** ✅ Production Ready


# Facebook Scraper - VPS Crash Issue & Solution

**Date:** October 10, 2025  
**Status:** ✅ **SOLVED**  
**Final Result:** 209 messages extracted (target: 200)

---

## 🔴 **THE PROBLEM**

### Symptoms
The Facebook scraper worked perfectly on Windows (200+ messages extracted) but **crashed immediately on Linux VPS** when attempting to extract content from complex Facebook pages.

### Crash Pattern
```
✅ Login: WORKING
✅ Navigation: WORKING
❌ Content Extraction: CRASHING

Error: "Page.evaluate: Target crashed"
Error: "Page.content: Target crashed"
```

### What We Tried (That DIDN'T Work)
1. ❌ Memory optimization flags (`--js-flags=--max-old-space-size=512`)
2. ❌ Reducing screenshot frequency
3. ❌ Using lxml for HTML parsing instead of Playwright DOM
4. ❌ Longer wait times (4s, 6s, 10s)
5. ❌ Smaller scroll increments (800px)
6. ❌ Disabling GPU (`--disable-gpu`)
7. ❌ Browser flags for VPS (`--disable-dev-shm-usage`, `--no-sandbox`)

**All attempts with Chromium in headless mode: FAILED**

---

## 🔍 **ROOT CAUSE ANALYSIS**

### Why It Worked on Windows But Not on VPS

The issue was **NOT** about:
- ❌ RAM (VPS had 8.2GB available)
- ❌ CPU resources
- ❌ Disk space
- ❌ Python/Playwright versions

The issue **WAS** about:

| Factor | Windows (Working) | VPS (Failing) |
|--------|------------------|---------------|
| **Browser** | Native Chrome (visible) | Chromium (headless) |
| **Rendering** | Hardware GPU + full GUI | Software rendering only |
| **Mode** | Headed (visible window) | Headless (no display) |
| **Browser Engine** | Chromium | Chromium |

### The Real Problem

**Chromium in headless mode on Linux** has known stability issues with:
- Complex JavaScript frameworks (React/Vue)
- Infinite scroll pages with heavy DOM manipulation
- Real-time updates (Facebook continuously loads content)
- Pages with thousands of dynamic elements

**Facebook's `Asirisinfinity5` profile page** is exceptionally heavy:
- Infinite scroll with lazy loading
- Heavy React-based virtual DOM
- Real-time updates and notifications
- Auto-loading media (images, videos)
- Complex nested components

---

## ✅ **THE SOLUTION**

### 3-Part Fix

#### 1. **Switch from Chromium to Firefox** 🦊
Firefox's rendering engine handles complex JavaScript pages better than Chromium in non-standard environments.

```python
# relay_agent.py
browser = p.firefox.launch(
    headless=config.HEADLESS,
    slow_mo=config.SLOW_MO if not config.HEADLESS else 0,
)
```

**Why Firefox?**
- More stable for heavy JavaScript applications
- Better memory management for long-running sessions
- Native Linux support with fewer quirks
- Handles React/Vue virtual DOM updates better

#### 2. **Use Xvfb (Virtual Display)** 🖥️
Run browser in **headed mode** (visible window) but use a virtual display instead of physical screen.

```bash
xvfb-run -a python3 relay_agent.py
```

**Why Xvfb?**
- Browser thinks it has a real display
- Avoids headless-specific rendering optimizations that cause instability
- No need for physical monitor
- Mimics Windows environment (visible browser)

#### 3. **Optimize Scroll Logic** 📜

**Problems with original scroll logic:**
- Too conservative scrolling (800px) → page thought it reached end
- Gave up too quickly (2 stuck attempts)
- Wait times too long for Firefox (10s unnecessary)

**New optimized logic:**

```python
# Larger scroll increments
page.evaluate("window.scrollBy(0, 1500)")  # Was 800px

# Aggressive scroll when stuck
if stuck:
    page.evaluate("window.scrollTo(0, document.body.scrollHeight)")

# Balanced wait time for Firefox
page.wait_for_timeout(7000)  # Was 10000ms

# More attempts before giving up
if stuck_scroll_count >= 3:  # Was 2
    break
```

---

## 📊 **PERFORMANCE RESULTS**

### Before vs After

| Metric | Chromium Headless | Firefox + Xvfb |
|--------|------------------|----------------|
| **Scrolls** | 1 | 19 |
| **Messages** | 10-14 | 209 |
| **Time** | ~30 seconds | ~3 minutes |
| **Crash Rate** | 100% | 0% |
| **Success** | ❌ | ✅ |

### Final Performance
```
Browser:    Firefox 141.0
Mode:       Headed (via Xvfb)
Scrolls:    19 successful scrolls
Messages:   209 unique messages extracted
Time:       ~3 minutes
Success:    100%
```

---

## 🚀 **HOW TO USE**

### Quick Start (Recommended)
```bash
cd /var/www/scrapper-alexis
./run_scraper.sh
```

### Manual Execution
```bash
cd /var/www/scrapper-alexis
source venv/bin/activate
xvfb-run -a python3 relay_agent.py
```

### Requirements
1. **Xvfb installed:**
   ```bash
   sudo apt-get install xvfb
   ```

2. **Firefox browser for Playwright:**
   ```bash
   playwright install firefox
   ```

3. **Configuration in `.env`:**
   ```bash
   HEADLESS=false  # Required for Xvfb approach
   ```

---

## 🔧 **KEY CODE CHANGES**

### 1. Browser Selection (`relay_agent.py`)

**Before:**
```python
browser = p.chromium.launch(headless=True, args=[...])
```

**After:**
```python
use_firefox = config.BROWSER_TYPE == 'firefox' if hasattr(config, 'BROWSER_TYPE') else True

if use_firefox:
    browser = p.firefox.launch(
        headless=config.HEADLESS,
        slow_mo=config.SLOW_MO if not config.HEADLESS else 0,
    )
```

### 2. Extraction Method (`facebook_extractor.py`)

**Before (HTML parsing - crashed):**
```python
page_html = page.content()  # Crashes on heavy pages
tree = lxml_html.fromstring(page_html)
```

**After (Direct JavaScript evaluation):**
```python
messages_on_page = page.evaluate('''
    () => {
        const elements = document.querySelectorAll('div[dir="auto"]');
        const texts = [];
        for (let i = 0; i < Math.min(elements.length, 50); i++) {
            const text = elements[i].innerText || elements[i].textContent || '';
            // ... filtering logic
            texts.push(cleaned);
        }
        return texts;
    }
''')
```

### 3. Scroll Logic (`facebook_extractor.py`)

**Key improvements:**
```python
# Larger scroll increments
page.evaluate("window.scrollBy(0, 1500)")  # Was 800px

# Aggressive scroll when stuck (instead of giving up)
if stuck:
    page.evaluate("window.scrollTo(0, document.body.scrollHeight)")

# More attempts before stopping
if stuck_scroll_count >= 3:  # Was 2
    break

# Balanced wait time
page.wait_for_timeout(7000)  # Was 10000ms
```

---

## 💡 **KEY INSIGHTS**

### What We Learned

1. **Headless vs Headed Matters More Than We Thought**
   - Not all browsers handle headless mode equally
   - "Headless" is fundamentally different code paths in browsers
   - Xvfb provides best of both worlds

2. **Browser Choice is Critical**
   - Firefox ≠ Chromium for complex JavaScript
   - Firefox's Gecko engine handles heavy React apps better on Linux
   - Chrome optimizations can backfire in non-standard environments

3. **Resource Issues Are Often Misdiagnosed**
   - VPS had plenty of RAM/CPU
   - Real issue was browser rendering stability
   - Don't assume "crash" = "out of memory"

4. **Conservative Scrolling Can Fail**
   - Small scrolls made page think it reached end
   - Aggressive scrolling (when needed) is sometimes better
   - Balance between speed and stability

5. **The Windows vs Linux Gap**
   - What works on Windows may fail on Linux
   - Browser engines behave differently per OS
   - Always test in production-like environments

---

## 📝 **TROUBLESHOOTING**

### If scraper still crashes:

1. **Verify Firefox is installed:**
   ```bash
   playwright install firefox
   ls ~/.cache/ms-playwright/firefox-*
   ```

2. **Check Xvfb is working:**
   ```bash
   which xvfb-run
   xvfb-run -a firefox --version
   ```

3. **Verify .env settings:**
   ```bash
   grep HEADLESS .env  # Should be: false
   ```

4. **Check available memory:**
   ```bash
   free -h  # Should have 2GB+ available
   ```

5. **Monitor during execution:**
   ```bash
   # In another terminal:
   htop
   tail -f logs/relay_agent_$(date +%Y%m%d).log
   ```

---

## 🎯 **SUMMARY**

### The Winning Formula

```
Firefox (stable browser)
  +
Xvfb (virtual display / headed mode)
  +
Optimized scrolling (larger increments + aggressive recovery)
  +
JavaScript extraction (avoid HTML transfer overhead)
  =
200+ messages extracted reliably on VPS ✅
```

### Bottom Line

The issue was **never about resources**. It was about:
1. Browser choice (Firefox > Chromium for this use case)
2. Rendering mode (Headed > Headless for complex pages)
3. Scroll strategy (Aggressive > Conservative for infinite scroll)

**Result:** From 0% success to 100% success with 209 messages extracted.

---

## 📚 **REFERENCES**

- [Playwright Browser Types](https://playwright.dev/python/docs/browsers)
- [Xvfb - Virtual Framebuffer](https://www.x.org/releases/X11R7.6/doc/man/man1/Xvfb.1.xhtml)
- [Firefox Headless Issues (GitHub)](https://github.com/microsoft/playwright/issues)
- [Puppeteer Target Crashed Solutions](https://stackoverflow.com/questions/tagged/puppeteer+crash)

---

**Last Updated:** October 10, 2025  
**Version:** 2.0 - Firefox + Xvfb Solution  
**Status:** Production Ready ✅



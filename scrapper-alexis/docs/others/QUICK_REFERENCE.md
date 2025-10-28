# Debug Helper - Quick Reference Card

## 🎯 Where Do Screenshots Go?

### ✅ **Answer: In the Respective Run Folder!**

```
debug_output/
└── run_20251010_120000_my_session/  ← YOUR RUN FOLDER
    ├── session.log
    ├── login/
    │   └── screenshot.png           ⬅️ YOUR SCREENSHOTS HERE
    ├── navigation/
    │   └── screenshot.png           ⬅️ YOUR SCREENSHOTS HERE
    ├── extraction/
    │   └── screenshot.png           ⬅️ YOUR SCREENSHOTS HERE
    └── errors/
        └── screenshot.png           ⬅️ YOUR SCREENSHOTS HERE
```

---

## 📝 Basic Usage

```python
from debug_helper import DebugSession, take_debug_screenshot

# 1. Create session → Creates run folder
session = DebugSession("my_task")

try:
    # 2. Take screenshots → Saved in run folder
    take_debug_screenshot(page, "step1", category="login")
    take_debug_screenshot(page, "step2", category="extraction")
    
finally:
    # 3. Close session
    session.close()
```

---

## 📁 Categories

| Category | Purpose | Example |
|----------|---------|---------|
| `login` | Authentication | Login forms, credentials |
| `navigation` | Page navigation | URL changes, page loads |
| `extraction` | Data scraping | Found data, parsing |
| `verification` | Validation | Checks, verifications |
| `errors` | Error conditions | Timeouts, failures |
| `other` | Miscellaneous | Everything else |

---

## 🔍 Examples

### Login Screenshot
```python
take_debug_screenshot(page, "01_login_form", category="login")
# → Saves to: debug_output/run_XXX/login/TIMESTAMP_01_login_form.png
```

### Navigation Screenshot
```python
take_debug_screenshot(page, "01_group_page", category="navigation")
# → Saves to: debug_output/run_XXX/navigation/TIMESTAMP_01_group_page.png
```

### Extraction Screenshot
```python
take_debug_screenshot(page, "01_posts_found", category="extraction")
# → Saves to: debug_output/run_XXX/extraction/TIMESTAMP_01_posts_found.png
```

### Error Screenshot
```python
log_error(page, "Timeout error", exception)
# → Auto-saves to: debug_output/run_XXX/errors/TIMESTAMP_error_Timeout_error.png
```

---

## ✅ Key Facts

1. **One Session = One Run Folder** ✅
   - Each `DebugSession()` creates ONE timestamped folder

2. **All Screenshots Go There** ✅
   - Every screenshot for that session goes into THAT folder

3. **Organized by Category** ✅
   - Within the run folder, screenshots are in category subfolders

4. **No Mixing Between Runs** ✅
   - Run 1 screenshots → Run 1 folder
   - Run 2 screenshots → Run 2 folder
   - Never mixed!

5. **Easy to Find** ✅
   - Go to run folder → Go to category → Find your screenshot

---

## 🚀 Complete Example

```python
from playwright.sync_api import sync_playwright
from debug_helper import (
    DebugSession,
    take_debug_screenshot,
    log_success,
    log_error
)

# Start session
session = DebugSession("facebook_scraper")

try:
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        
        # Login phase - screenshots go to run_XXX/login/
        page.goto("https://facebook.com")
        take_debug_screenshot(page, "01_login_page", category="login")
        
        # ... login code ...
        
        take_debug_screenshot(page, "02_logged_in", category="login")
        log_success("Login completed", category="login")
        
        # Navigation - screenshots go to run_XXX/navigation/
        page.goto("https://facebook.com/groups/example")
        take_debug_screenshot(page, "01_group_page", category="navigation")
        
        # Extraction - screenshots go to run_XXX/extraction/
        take_debug_screenshot(page, "01_posts_found", category="extraction")
        
        browser.close()
        
except Exception as e:
    log_error(page, "Fatal error", e)  # → run_XXX/errors/
    
finally:
    session.close()

print(f"All debug files at: {session.run_dir}")
```

**Result:** All screenshots and logs in ONE organized run folder! 🎯

---

## 📖 Full Documentation

- **DEBUG_HELPER_README.md** - Complete guide
- **SCREENSHOT_ORGANIZATION.md** - Detailed structure explanation
- **IMPROVEMENTS_SUMMARY.md** - What changed and why

---

## 🧪 Test It

```bash
# Run demo
python3 demo_screenshot_organization.py

# Check structure
ls -la debug_output/run_*/
```

---

## ❓ FAQ

**Q: Do screenshots go in the run folder?**  
A: ✅ YES! All screenshots go in the respective run folder.

**Q: Are they organized?**  
A: ✅ YES! By category (login, navigation, extraction, etc.)

**Q: Do different runs mix?**  
A: ❌ NO! Each run has its own isolated folder.

**Q: How do I find my screenshots?**  
A: Navigate to `debug_output/run_TIMESTAMP_NAME/category/`

---

**🎯 Remember: One session = One run folder = All your screenshots there!**


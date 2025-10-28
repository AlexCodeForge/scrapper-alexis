# Debug Helper - Documentation

## Overview

The improved `debug_helper.py` module provides organized debugging with separate folders per run and categorized logging/screenshots.

## Folder Structure

✅ **IMPORTANT: All screenshots/images are saved in the respective run folder!**

Each debug session creates the following structure:

```
debug_output/
├── run_20251010_120534_facebook_scraper/    ← All Run 1 content here
│   ├── session.log                          # Main session log
│   ├── login/                               # Login-related debugging
│   │   ├── 20251010_120534_001_login_attempt.png    ⬅️ Screenshots in RUN folder
│   │   ├── 20251010_120536_002_after_login.png      ⬅️ Screenshots in RUN folder
│   │   └── login_20251010_120540.log
│   ├── navigation/                          # Navigation debugging
│   │   ├── 20251010_120542_001_page_load.png        ⬅️ Screenshots in RUN folder
│   │   └── 20251010_120544_002_group_page.png       ⬅️ Screenshots in RUN folder
│   ├── extraction/                          # Data extraction debugging
│   │   ├── 20251010_120546_001_data_found.png       ⬅️ Screenshots in RUN folder
│   │   └── extraction_20251010_120550.log
│   ├── verification/                        # Verification debugging
│   │   └── 20251010_120555_001_verify.png           ⬅️ Screenshots in RUN folder
│   ├── errors/                              # Error screenshots
│   │   └── 20251010_120600_001_error_timeout.png    ⬅️ Screenshots in RUN folder
│   └── other/                               # Miscellaneous
│       └── ...
└── run_20251010_130200_another_run/         ← All Run 2 content here (separate!)
    └── ... (same structure with Run 2 screenshots)
```

**Key Points:**
- Each run gets its own isolated folder with timestamp
- ALL screenshots for that run are saved IN that run's folder
- Screenshots are organized by category (login, navigation, extraction, etc.)
- Different runs never mix - each has its own complete set of folders

## Usage

### Basic Usage

```python
from debug_helper import DebugSession, take_debug_screenshot, log_debug_info

# Initialize a debug session
session = DebugSession(session_name="my_scraper")

try:
    # Your scraping code here
    log_debug_info("Starting process", category="login")
    
    # Take categorized screenshots
    take_debug_screenshot(page, "login_form", category="login")
    
finally:
    # Always close the session
    session.close()
```

### Available Functions

#### `DebugSession(session_name="")`
Creates a new debug session with organized folder structure.

**Parameters:**
- `session_name` (str, optional): Custom name for the session

**Example:**
```python
session = DebugSession("facebook_groups")
# Creates: debug_output/run_20251010_120534_facebook_groups/
```

#### `take_debug_screenshot(page, step_name, category="other", description="")`
Takes a screenshot and saves it in the appropriate category folder.

**Parameters:**
- `page`: Playwright Page instance
- `step_name`: Name for the screenshot file
- `category`: Category folder (login, navigation, extraction, verification, errors, other)
- `description`: Additional description for logging

**Example:**
```python
take_debug_screenshot(page, "01_login_form", category="login", description="Login form loaded")
```

#### `log_page_state(page, context="", category="other")`
Logs detailed page state and takes a screenshot.

**Parameters:**
- `page`: Playwright Page instance
- `context`: Context description
- `category`: Category for organizing

**Example:**
```python
log_page_state(page, "After successful login", category="login")
```

#### `log_debug_info(message, level="INFO", category="other")`
Logs debug information with category tags.

**Parameters:**
- `message`: Debug message
- `level`: Log level (INFO, DEBUG, WARNING, ERROR)
- `category`: Category for organizing

**Example:**
```python
log_debug_info("Processing started", level="INFO", category="extraction")
```

#### `log_error(page, error_msg, exception=None)`
Logs error with automatic screenshot in errors category.

**Parameters:**
- `page`: Playwright Page instance
- `error_msg`: Error message
- `exception`: Optional exception object

**Example:**
```python
try:
    # ... code that might fail ...
except Exception as e:
    log_error(page, "Failed to extract data", e)
```

#### `log_success(message, category="other")`
Logs success message.

**Example:**
```python
log_success("Login completed", category="login")
```

#### `create_category_log(category, content)`
Creates a dedicated log file within a category folder.

**Parameters:**
- `category`: Category name
- `content`: Content to write

**Example:**
```python
create_category_log("extraction", """
Extraction Results
==================
Posts: 50
Comments: 150
""")
```

### Categories

The following categories are predefined:

- **login**: Authentication and login processes
- **navigation**: Page navigation and URL changes
- **extraction**: Data scraping and extraction
- **verification**: Data validation and verification
- **errors**: Error conditions and failures
- **other**: Miscellaneous operations

## Complete Example

```python
from playwright.sync_api import sync_playwright
from debug_helper import (
    DebugSession,
    take_debug_screenshot,
    log_page_state,
    log_debug_info,
    log_error,
    log_success,
    create_category_log
)

def scrape_with_debugging():
    # Create session
    session = DebugSession("facebook_groups")
    
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=False)
            page = browser.new_page()
            
            # Login phase
            log_debug_info("Starting login", category="login")
            page.goto("https://www.facebook.com")
            take_debug_screenshot(page, "01_landing", category="login")
            
            # ... login code ...
            
            log_page_state(page, "After login", category="login")
            log_success("Login completed", category="login")
            
            # Navigation phase
            log_debug_info("Navigating to group", category="navigation")
            page.goto("https://www.facebook.com/groups/example")
            take_debug_screenshot(page, "01_group_page", category="navigation")
            
            # Extraction phase
            log_debug_info("Extracting data", category="extraction")
            try:
                # ... extraction code ...
                take_debug_screenshot(page, "01_data", category="extraction")
                log_success("Data extracted", category="extraction")
            except Exception as e:
                log_error(page, "Extraction failed", e)
            
            browser.close()
            
    except Exception as e:
        session.logger.error(f"Fatal error: {e}", exc_info=True)
    finally:
        session.close()

if __name__ == "__main__":
    scrape_with_debugging()
```

## Benefits

1. **Organized Structure**: Each run gets its own folder with timestamp
2. **Categorized Debugging**: Screenshots and logs organized by purpose
3. **Easy Troubleshooting**: Find exactly what you need quickly
4. **Session Logs**: Complete session log in addition to category-specific logs
5. **Backward Compatible**: Works with existing code using fallback mode

## Migration from Old Version

Old code:
```python
from debug_helper import take_debug_screenshot
take_debug_screenshot(page, "my_screenshot")
```

New code:
```python
from debug_helper import DebugSession, take_debug_screenshot

session = DebugSession("my_session")
try:
    take_debug_screenshot(page, "my_screenshot", category="login")
finally:
    session.close()
```

The old code will still work but screenshots will go to `debug_output/legacy/` folder.


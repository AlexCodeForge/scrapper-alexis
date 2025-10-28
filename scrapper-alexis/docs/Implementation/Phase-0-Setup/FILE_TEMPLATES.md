# Phase 0: File Templates

## requirements.txt
```txt
playwright>=1.40.0
python-dotenv>=1.0.0
tabulate>=0.9.0
```

---

## .env.example
```bash
# Facebook Credentials
FACEBOOK_EMAIL=your_email@example.com
FACEBOOK_PASSWORD=your_facebook_password

# X/Twitter Credentials
X_EMAIL=your_x_username_or_email
X_PASSWORD=your_x_password

# Target Facebook Message URL
FACEBOOK_MESSAGE_URL=https://www.facebook.com/messages/t/...

# Browser Configuration
HEADLESS=false                    # Set to 'true' for production
SLOW_MO=50                        # Milliseconds delay between actions (0 for production)

# Timeouts (milliseconds)
DEFAULT_TIMEOUT=30000
NAVIGATION_TIMEOUT=30000
LOGIN_TIMEOUT=60000               # Extra time for 2FA/CAPTCHA

# Rate Limiting
MAX_RETRIES=3
BASE_RETRY_DELAY=2                # Seconds
MAX_RETRY_DELAY=60                # Seconds

# Logging
LOG_LEVEL=INFO                    # DEBUG, INFO, WARNING, ERROR

# Browser Fingerprint
USER_AGENT=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36
LOCALE=en-US
TIMEZONE=America/New_York

# Screenshots
SCREENSHOT_DIR=screenshots
SCREENSHOT_QUALITY=100            # PNG quality (0-100)

# Database Configuration
DATABASE_PATH=relay_agent.db
DUPLICATE_CHECK_HOURS=24        # Prevent processing same message within X hours
AUTO_BACKUP=true                # Auto-backup database after each run
BACKUP_RETENTION_DAYS=7         # Keep backups for N days
```

---

## .gitignore
```
.env
auth_*.json
logs/
screenshots/
backups/
__pycache__/
*.pyc
.pytest_cache/
*.log
.venv/
venv/
env/
.DS_Store
Thumbs.db

# Database (optional - uncomment if you don't want to track DB)
# *.db
# *.db-journal
```

---

## config.py
```python
"""Configuration management for Relay Agent."""
import os
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Facebook Credentials
FACEBOOK_EMAIL = os.getenv('FACEBOOK_EMAIL')
FACEBOOK_PASSWORD = os.getenv('FACEBOOK_PASSWORD')

# X/Twitter Credentials
X_EMAIL = os.getenv('X_EMAIL')
X_PASSWORD = os.getenv('X_PASSWORD')

# Target URL
FACEBOOK_MESSAGE_URL = os.getenv('FACEBOOK_MESSAGE_URL')

# Browser Configuration
HEADLESS = os.getenv('HEADLESS', 'false').lower() == 'true'
SLOW_MO = int(os.getenv('SLOW_MO', '50'))

# Timeouts (in milliseconds)
DEFAULT_TIMEOUT = int(os.getenv('DEFAULT_TIMEOUT', '30000'))
NAVIGATION_TIMEOUT = int(os.getenv('NAVIGATION_TIMEOUT', '30000'))
LOGIN_TIMEOUT = int(os.getenv('LOGIN_TIMEOUT', '60000'))

# Rate Limiting
MAX_RETRIES = int(os.getenv('MAX_RETRIES', '3'))
BASE_RETRY_DELAY = int(os.getenv('BASE_RETRY_DELAY', '2'))
MAX_RETRY_DELAY = int(os.getenv('MAX_RETRY_DELAY', '60'))

# Logging
LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')

# Browser Fingerprint
USER_AGENT = os.getenv(
    'USER_AGENT',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
)
LOCALE = os.getenv('LOCALE', 'en-US')
TIMEZONE = os.getenv('TIMEZONE', 'America/New_York')

# Screenshots
SCREENSHOT_DIR = Path(os.getenv('SCREENSHOT_DIR', 'screenshots'))
SCREENSHOT_QUALITY = int(os.getenv('SCREENSHOT_QUALITY', '100'))

# Database
DATABASE_PATH = os.getenv('DATABASE_PATH', 'relay_agent.db')
DUPLICATE_CHECK_HOURS = int(os.getenv('DUPLICATE_CHECK_HOURS', '24'))
AUTO_BACKUP = os.getenv('AUTO_BACKUP', 'true').lower() == 'true'
BACKUP_RETENTION_DAYS = int(os.getenv('BACKUP_RETENTION_DAYS', '7'))

# X/Twitter Constants
X_CHAR_LIMIT = 280

# Validate required configuration
def validate_config():
    """Validate that all required configuration is present."""
    required = {
        'FACEBOOK_EMAIL': FACEBOOK_EMAIL,
        'FACEBOOK_PASSWORD': FACEBOOK_PASSWORD,
        'X_EMAIL': X_EMAIL,
        'X_PASSWORD': X_PASSWORD,
        'FACEBOOK_MESSAGE_URL': FACEBOOK_MESSAGE_URL
    }
    
    missing = [key for key, value in required.items() if not value]
    
    if missing:
        raise ValueError(f"Missing required configuration: {', '.join(missing)}")
    
    return True
```

---

## exceptions.py
```python
"""Custom exception classes for Relay Agent."""

class RelayAgentError(Exception):
    """Base exception for relay agent errors."""
    pass


class LoginError(RelayAgentError):
    """Authentication/login failures."""
    pass


class ExtractionError(RelayAgentError):
    """Content extraction failures."""
    pass


class PostingError(RelayAgentError):
    """X/Twitter posting failures."""
    pass


class ScreenshotError(RelayAgentError):
    """Screenshot capture failures."""
    pass


class NavigationError(RelayAgentError):
    """Page navigation failures."""
    pass


class DatabaseError(RelayAgentError):
    """Database operation failures."""
    pass


class ConfigurationError(RelayAgentError):
    """Configuration validation failures."""
    pass
```

---

## relay_agent.py (Placeholder)
```python
"""Playwright Social Content Relay Agent - Main Script."""
import logging
from pathlib import Path
from datetime import datetime

import config
from exceptions import RelayAgentError, ConfigurationError

# Create logs directory
logs_dir = Path('logs')
logs_dir.mkdir(exist_ok=True)

# Configure logging
logging.basicConfig(
    level=getattr(logging, config.LOG_LEVEL),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(
            logs_dir / f'relay_agent_{datetime.now().strftime("%Y%m%d")}.log'
        ),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)


def main():
    """Main execution function."""
    try:
        # Validate configuration
        config.validate_config()
        logger.info("Configuration validated successfully")
        
        logger.info("Relay Agent starting...")
        logger.info("Phase 0: Setup complete")
        
        # TODO: Implement Phase 1 - Facebook Content Acquisition
        # TODO: Implement Phase 2 - X/Twitter Posting
        # TODO: Implement Phase 3 - Screenshot & Database Storage
        
        logger.info("Relay Agent execution complete")
        
    except ConfigurationError as e:
        logger.error(f"Configuration error: {e}")
        raise
    except RelayAgentError as e:
        logger.error(f"Relay agent error: {e}")
        raise
    except Exception as e:
        logger.error(f"Unexpected error: {e}", exc_info=True)
        raise


if __name__ == "__main__":
    main()
```

---

## README.md (Initial)
```markdown
# Playwright Social Content Relay Agent

Automate the extraction of a specific Facebook message's text, post it to X (Twitter), and capture a screenshot of the source message.

## ⚠️ Security Warning
This project handles sensitive credentials. Never commit `.env` files or authentication state files to version control.

## Prerequisites
- Python 3.9 or higher
- pip package manager
- Stable internet connection

## Quick Start

### 1. Setup Virtual Environment
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

### 2. Install Dependencies
```bash
pip install -r requirements.txt
playwright install chromium
```

### 3. Configure Credentials
```bash
cp .env.example .env
# Edit .env with your Facebook and X/Twitter credentials
```

### 4. Run the Agent
```bash
python relay_agent.py
```

## Project Status
🚧 **Phase 0: Setup** - Complete  
⏳ **Phase 1: Facebook Content Acquisition** - Pending  
⏳ **Phase 2: X/Twitter Posting** - Pending  
⏳ **Phase 3: Screenshot & Database Storage** - Pending  

## Documentation
See `docs/Implementation/` for detailed phase-by-phase implementation guides.

## License
Private project - Not for distribution
```


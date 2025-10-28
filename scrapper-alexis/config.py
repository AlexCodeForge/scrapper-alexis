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

# Database Configuration
DATABASE_PATH = os.getenv('DATABASE_PATH', 'data/scraper.db')
DUPLICATE_CHECK_HOURS = int(os.getenv('DUPLICATE_CHECK_HOURS', '24'))
AUTO_BACKUP = os.getenv('AUTO_BACKUP', 'true').lower() == 'true'
BACKUP_RETENTION_DAYS = int(os.getenv('BACKUP_RETENTION_DAYS', '7'))

# Multi-Profile Configuration
MAX_PROFILES_PER_RUN = int(os.getenv('MAX_PROFILES_PER_RUN', '10'))
PROFILE_SCRAPING_DELAY = int(os.getenv('PROFILE_SCRAPING_DELAY', '30'))  # seconds between profiles
DUPLICATE_STOP_ENABLED = os.getenv('DUPLICATE_STOP_ENABLED', 'true').lower() == 'true'

# Facebook Profiles to Scrape
FACEBOOK_PROFILES = os.getenv('FACEBOOK_PROFILES', '').split(',') if os.getenv('FACEBOOK_PROFILES') else []

# Proxy Configuration (CRITICAL for Twitter, recommended for Facebook)
PROXY_SERVER = os.getenv('PROXY_SERVER', '')
PROXY_USERNAME = os.getenv('PROXY_USERNAME', '')
PROXY_PASSWORD = os.getenv('PROXY_PASSWORD', '')

# Build proxy config dict
PROXY_CONFIG = {
    'server': PROXY_SERVER,
    'username': PROXY_USERNAME,
    'password': PROXY_PASSWORD
} if PROXY_SERVER else None

# Interval Settings (in minutes)
FACEBOOK_INTERVAL_MIN = int(os.getenv('FACEBOOK_INTERVAL_MIN', '45'))
FACEBOOK_INTERVAL_MAX = int(os.getenv('FACEBOOK_INTERVAL_MAX', '80'))
TWITTER_INTERVAL_MIN = int(os.getenv('TWITTER_INTERVAL_MIN', '1'))
TWITTER_INTERVAL_MAX = int(os.getenv('TWITTER_INTERVAL_MAX', '60'))

# X/Twitter Constants
X_CHAR_LIMIT = 280

# X/Twitter Profile Information (for image generation)
X_DISPLAY_NAME = os.getenv('X_DISPLAY_NAME', 'Twitter User')
X_USERNAME = os.getenv('X_USERNAME', '@username')
X_AVATAR_URL = os.getenv('X_AVATAR_URL', '')

# Validate required configuration
def validate_config(phase: str = 'all'):
    """
    Validate that required configuration is present for the specified phase.
    
    Args:
        phase: 'phase1', 'phase2', 'phase3', or 'all' (default)
    """
    from exceptions import ConfigurationError
    
    # Phase 1 requirements
    phase1_required = {
        'FACEBOOK_EMAIL': FACEBOOK_EMAIL,
        'FACEBOOK_PASSWORD': FACEBOOK_PASSWORD,
        'FACEBOOK_MESSAGE_URL': FACEBOOK_MESSAGE_URL
    }
    
    # Phase 2 requirements (in addition to Phase 1)
    phase2_required = {
        'X_EMAIL': X_EMAIL,
        'X_PASSWORD': X_PASSWORD
    }
    
    # Determine what to validate
    if phase == 'phase1':
        required = phase1_required
    elif phase == 'phase2':
        required = {**phase1_required, **phase2_required}
    elif phase == 'all':
        required = {**phase1_required, **phase2_required}
    else:
        required = phase1_required  # Default to Phase 1
    
    missing = [key for key, value in required.items() if not value]
    
    if missing:
        raise ConfigurationError(
            f"Missing required configuration for {phase}: {', '.join(missing)}"
        )
    
    return True




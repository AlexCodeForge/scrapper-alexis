"""Configuration management for Relay Agent.

All dynamic settings (credentials, intervals, proxy) are stored in the database.
No .env fallback - database is the single source of truth.
Configure settings via web interface: http://213.199.33.207:8006/settings
"""
import os
import sqlite3
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables (ONLY for static config - not credentials/intervals)
load_dotenv()

# Database path for dynamic settings (single source of truth)
# Search for database in multiple possible locations
def _get_database_path():
    possible_paths = [
        '/var/www/scrapper-alexis/data/scraper.db',  # Laravel database (shared)
        '/var/www/alexis-scrapper-docker/scrapper-alexis/data/scraper.db',  # Local path
        'data/scraper.db',  # Relative path (fallback)
    ]
    for path in possible_paths:
        if os.path.exists(path):
            return path
    # Default to relative path if none found
    return 'data/scraper.db'

DATABASE_PATH = os.getenv('DATABASE_PATH') or _get_database_path()

def get_settings_from_db():
    """
    Load dynamic settings from database.
    This allows live updates from the web interface without restarting scripts.
    
    Raises:
        Exception: If database is not accessible or settings are not configured.
    """
    import logging
    
    try:
        # Laravel database path (shared with web interface)
        # This is where the scraper_settings table lives
        possible_paths = [
            '/var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite',  # Laravel database (Nginx)
            '/var/www/scrapper-alexis/data/scraper.db',  # OLD Laravel database
            '/var/www/alexis-scrapper-docker/scrapper-alexis/data/scraper.db',  # Fallback
            'data/scraper.db',  # Relative path
        ]
        
        db_path = None
        for path in possible_paths:
            if os.path.exists(path):
                db_path = path
                break
        
        if not db_path or not os.path.exists(db_path):
            raise FileNotFoundError(f"Laravel database not found. Tried: {possible_paths}. Please configure settings via web interface.")
        
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        cursor = conn.execute("SELECT * FROM scraper_settings LIMIT 1")
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            raise ValueError("No settings found in database. Please configure settings via web interface at http://213.199.33.207:8006/settings")
        
        return dict(row)
        
    except Exception as e:
        logging.error(f"CRITICAL: Failed to load settings from database: {e}")
        logging.error("Settings MUST be configured in the database via the web interface.")
        logging.error("No .env fallback - database is the single source of truth.")
        raise

# Load settings from database (no .env fallback)
try:
    _db_settings = get_settings_from_db()
    
    # Use database settings (passwords are already decrypted by Laravel before being stored)
    FACEBOOK_EMAIL = _db_settings.get('facebook_email') or ''
    FACEBOOK_PASSWORD = _db_settings.get('facebook_password') or ''
    X_EMAIL = _db_settings.get('twitter_email') or ''
    X_PASSWORD = _db_settings.get('twitter_password') or ''
    X_DISPLAY_NAME = _db_settings.get('twitter_display_name') or 'Twitter User'
    X_USERNAME = _db_settings.get('twitter_username') or '@username'
    X_AVATAR_URL = _db_settings.get('twitter_avatar_url') or ''
    X_VERIFIED = bool(_db_settings.get('twitter_verified', False))
    
    # Handle facebook_profiles (can be None or empty string)
    profiles_str = _db_settings.get('facebook_profiles') or ''
    FACEBOOK_PROFILES = [p.strip() for p in profiles_str.split(',') if p.strip()] if profiles_str else []
    
    PROXY_SERVER = _db_settings.get('proxy_server') or ''
    PROXY_USERNAME = _db_settings.get('proxy_username') or ''
    PROXY_PASSWORD = _db_settings.get('proxy_password') or ''
    FACEBOOK_INTERVAL_MIN = int(_db_settings.get('facebook_interval_min', 45))
    FACEBOOK_INTERVAL_MAX = int(_db_settings.get('facebook_interval_max', 80))
    TWITTER_INTERVAL_MIN = int(_db_settings.get('twitter_interval_min', 8))
    TWITTER_INTERVAL_MAX = int(_db_settings.get('twitter_interval_max', 60))
    
except Exception as e:
    import logging
    logging.critical("="*70)
    logging.critical("CONFIGURATION ERROR: Cannot load settings from database")
    logging.critical("="*70)
    logging.critical(f"Error: {e}")
    logging.critical("Action required: Configure settings at http://213.199.33.207:8006/settings")
    logging.critical("="*70)
    # Re-raise to prevent script from running with invalid config
    raise SystemExit("Configuration error: Settings must be configured in database. No .env fallback available.")

# Target URL (still from .env - not a dynamic setting)
FACEBOOK_MESSAGE_URL = os.getenv('FACEBOOK_MESSAGE_URL')

# Browser Configuration (static)
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
DUPLICATE_CHECK_HOURS = int(os.getenv('DUPLICATE_CHECK_HOURS', '24'))
AUTO_BACKUP = os.getenv('AUTO_BACKUP', 'true').lower() == 'true'
BACKUP_RETENTION_DAYS = int(os.getenv('BACKUP_RETENTION_DAYS', '7'))

# Multi-Profile Configuration
MAX_PROFILES_PER_RUN = int(os.getenv('MAX_PROFILES_PER_RUN', '10'))
PROFILE_SCRAPING_DELAY = int(os.getenv('PROFILE_SCRAPING_DELAY', '30'))  # seconds between profiles
DUPLICATE_STOP_ENABLED = os.getenv('DUPLICATE_STOP_ENABLED', 'true').lower() == 'true'

# Build proxy config dict
PROXY_CONFIG = {
    'server': PROXY_SERVER,
    'username': PROXY_USERNAME,
    'password': PROXY_PASSWORD
} if PROXY_SERVER else None

# X/Twitter Constants
X_CHAR_LIMIT = 280

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




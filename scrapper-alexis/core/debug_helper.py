"""Debug helper for tracking script execution with screenshots.

Debug output is now controlled per-script via database settings.
Configure at http://213.199.33.207:8006/settings

Supported script types:
- 'facebook': Facebook scraper debug
- 'twitter': Twitter poster debug
- 'page_posting': Page posting debug
"""
import logging
import os
from pathlib import Path
from datetime import datetime
from typing import Optional, TYPE_CHECKING

if TYPE_CHECKING:
    from playwright.sync_api import Page

# Global variables for current run
_current_run_dir: Optional[Path] = None
_run_logger: Optional[logging.Logger] = None
_debug_enabled: bool = False  # Will be set dynamically per script
_script_type: str = 'unknown'  # Track which script type is running

# Base debug directory (created only when debug is enabled)
BASE_DEBUG_DIR = Path('debug_output')


class NoOpDebugSession:
    """No-op debug session when debug output is disabled."""
    
    def __init__(self, session_name: str = ""):
        """Initialize a no-op debug session that does nothing."""
        self.logger = logging.getLogger(__name__)
        self.run_dir = None
        self.categories = {}
    
    def _setup_logger(self, run_name: str) -> logging.Logger:
        """Return the default logger."""
        return self.logger
    
    def get_category_dir(self, category: str) -> Optional[Path]:
        """Return None as no directories are created."""
        return None
    
    def close(self):
        """No-op close method."""
        pass


class DebugSession:
    """Manages a debug session with organized folders per run.
    
    Debug is controlled per-script via database settings.
    """
    
    def __new__(cls, session_name: str = "", script_type: str = "facebook"):
        """
        Create a DebugSession or NoOpDebugSession based on database settings.
        
        Args:
            session_name: Optional name for the session (default: timestamp)
            script_type: Script type ('facebook', 'twitter', 'page_posting')
        """
        global _debug_enabled, _script_type
        
        # Check debug status from database
        try:
            from config import get_debug_enabled
            _debug_enabled = get_debug_enabled(script_type)
            _script_type = script_type
        except Exception as e:
            logging.error(f"Failed to get debug setting from database: {e}")
            _debug_enabled = False
        
        if not _debug_enabled:
            # Return a no-op session if debug is disabled
            return NoOpDebugSession(session_name)
        
        # Create and return a real debug session
        instance = super().__new__(cls)
        return instance
    
    def __init__(self, session_name: str = "", script_type: str = "facebook"):
        """
        Initialize a debug session.
        
        Args:
            session_name: Optional name for the session (default: timestamp)
            script_type: Script type ('facebook', 'twitter', 'page_posting')
        """
        # Skip initialization if this is a NoOpDebugSession
        if isinstance(self, NoOpDebugSession):
            return
        
        # Create base debug directory if it doesn't exist
        BASE_DEBUG_DIR.mkdir(exist_ok=True)
        
        # Create unique run directory
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        if session_name:
            run_name = f"run_{timestamp}_{session_name}"
        else:
            run_name = f"run_{timestamp}"
        
        self.run_dir = BASE_DEBUG_DIR / run_name
        self.run_dir.mkdir(exist_ok=True)
        
        # Create category subdirectories
        self.categories = {
            'login': self.run_dir / 'login',
            'navigation': self.run_dir / 'navigation',
            'extraction': self.run_dir / 'extraction',
            'verification': self.run_dir / 'verification',
            'errors': self.run_dir / 'errors',
            'other': self.run_dir / 'other'
        }
        
        for category_dir in self.categories.values():
            category_dir.mkdir(exist_ok=True)
        
        # Setup session logger
        self.logger = self._setup_logger(run_name)
        
        # Set global variables
        global _current_run_dir, _run_logger
        _current_run_dir = self.run_dir
        _run_logger = self.logger
        
        self.logger.info("="*70)
        self.logger.info(f"🚀 DEBUG SESSION STARTED: {run_name}")
        self.logger.info(f"📁 Output directory: {self.run_dir}")
        self.logger.info("="*70)
    
    def _setup_logger(self, run_name: str) -> logging.Logger:
        """Setup a dedicated logger for this run."""
        logger = logging.getLogger(f"debug_session_{run_name}")
        logger.setLevel(logging.DEBUG)
        
        # Remove existing handlers
        logger.handlers.clear()
        
        # File handler - main log file
        log_file = self.run_dir / "session.log"
        file_handler = logging.FileHandler(log_file, mode='w', encoding='utf-8')
        file_handler.setLevel(logging.DEBUG)
        
        # Console handler
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.INFO)
        
        # Formatter
        formatter = logging.Formatter(
            '%(asctime)s | %(levelname)-8s | %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        file_handler.setFormatter(formatter)
        console_handler.setFormatter(formatter)
        
        logger.addHandler(file_handler)
        logger.addHandler(console_handler)
        
        return logger
    
    def get_category_dir(self, category: str) -> Path:
        """Get directory for a specific category."""
        return self.categories.get(category, self.categories['other'])
    
    def close(self):
        """Close the debug session."""
        self.logger.info("="*70)
        self.logger.info("✅ DEBUG SESSION COMPLETED")
        self.logger.info("="*70)
        
        # Close handlers
        for handler in self.logger.handlers:
            handler.close()


def take_debug_screenshot(page: "Page", step_name: str, category: str = "other", description: str = "") -> str:
    """
    Take a screenshot for debugging purposes.
    
    ✅ IMPORTANT: Screenshots are saved in TWO locations:
    1. debug_output/run_TIMESTAMP_NAME/category/screenshot.png (organized by run)
    2. pictures/ folder (accessible from web interface for debugging)
    - If DEBUG_OUTPUT_ENABLED=false, this function returns immediately without taking a screenshot
    
    Args:
        page: Playwright Page instance
        step_name: Name of the step (e.g., "login_attempt", "after_navigation")
        category: Category for organizing (login, navigation, extraction, verification, errors, other)
        description: Additional description for logging
        
    Returns:
        Path to saved screenshot (empty string if debug is disabled)
    """
    # Skip screenshot if debug output is disabled
    if not _debug_enabled:
        return ""
    
    try:
        # Use global run directory or fallback to default
        # NOTE: This ensures screenshots go into the CURRENT RUN's folder
        if _current_run_dir:
            base_dir = _current_run_dir  # ← Screenshots go into THIS run's folder!
            logger = _run_logger or logging.getLogger(__name__)
        else:
            # Fallback for backward compatibility (no active session)
            base_dir = BASE_DEBUG_DIR / "legacy"
            base_dir.mkdir(parents=True, exist_ok=True)
            logger = logging.getLogger(__name__)
        
        # Sanitize category to remove invalid characters
        safe_category = "".join(c if c.isalnum() or c in (' ', '_', '-') else '_' for c in category)
        safe_category = safe_category.strip()[:50]  # Limit length
        if not safe_category:
            safe_category = "other"
        
        # Determine category directory WITHIN the run folder
        category_dir = base_dir / safe_category  # ← Category subfolder in run folder
        category_dir.mkdir(parents=True, exist_ok=True)
        
        # Sanitize step_name for filename
        safe_step_name = "".join(c if c.isalnum() or c in ('_', '-') else '_' for c in step_name)
        safe_step_name = safe_step_name[:100]  # Limit length
        
        # Create filename with timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S_%f")[:-3]
        filename = f"{timestamp}_{safe_step_name}.png"
        filepath = category_dir / filename  # ← Final path: run_folder/category/screenshot.png
        
        # Take screenshot (viewport only to prevent crashes on heavy pages)
        # Using full_page=False is much more stable on VPS with limited resources
        page.screenshot(path=str(filepath), full_page=False)
        
        # ALSO save a copy to pictures/ folder for easy web access
        try:
            pictures_dir = Path('/pictures')  # Shared volume accessible from web
            pictures_dir.mkdir(parents=True, exist_ok=True)
            
            # Use simpler naming for pictures folder: category_timestamp_step.png
            pictures_filename = f"{safe_category}_{timestamp}_{safe_step_name}.png"
            pictures_path = pictures_dir / pictures_filename
            
            # Copy screenshot to pictures folder
            import shutil
            shutil.copy2(str(filepath), str(pictures_path))
            logger.info(f"   📋 Also saved to: {pictures_path}")
        except Exception as copy_err:
            logger.warning(f"Failed to copy screenshot to pictures folder: {copy_err}")
        
        # Get current URL for context
        current_url = page.url
        
        log_msg = f"📸 SCREENSHOT [{category.upper()}]: {step_name}"
        if description:
            log_msg += f" - {description}"
        log_msg += f"\n   URL: {current_url}\n   Saved: {filepath}"
        
        logger.info(log_msg)
        return str(filepath)
        
    except Exception as e:
        logger = _run_logger or logging.getLogger(__name__)
        logger.error(f"Failed to take debug screenshot '{step_name}': {e}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        return ""


def log_page_state(page: "Page", context: str = "", category: str = "other"):
    """
    Log detailed information about the current page state.
    
    Args:
        page: Playwright Page instance
        context: Context description (e.g., "After login attempt")
        category: Category for organizing (login, navigation, extraction, verification, errors, other)
    """
    # Skip if debug output is disabled
    if not _debug_enabled:
        return
    
    try:
        logger = _run_logger or logging.getLogger(__name__)
        
        logger.debug("="*60)
        logger.debug(f"PAGE STATE DEBUG: {context}")
        logger.debug("="*60)
        logger.debug(f"URL: {page.url}")
        logger.debug(f"Title: {page.title()}")
        
        # Check for common indicators
        try:
            # Check if login form visible
            email_input = page.locator('input[name="email"]').first
            if email_input.is_visible(timeout=1000):
                logger.debug("  [!] Login form detected (email input visible)")
        except:
            pass
            
        try:
            # Check for logged-in indicators
            profile_menu = page.locator('[aria-label*="Your profile"], [aria-label*="Tu perfil"]').first
            if profile_menu.is_visible(timeout=1000):
                logger.debug("  [✓] Appears logged in (profile menu visible)")
        except:
            pass
            
        try:
            # Check for popups/dialogs
            dialogs = page.locator('div[role="dialog"]').count()
            if dialogs > 0:
                logger.debug(f"  [!] {dialogs} dialog(s) detected on page")
        except:
            pass
            
        logger.debug("="*60)
        
        # Also take a screenshot for this state
        take_debug_screenshot(page, f"state_{context.replace(' ', '_')}", category=category, description=context)
        
    except Exception as e:
        logger = _run_logger or logging.getLogger(__name__)
        logger.error(f"Failed to log page state: {e}")


def log_debug_info(message: str, level: str = "INFO", category: str = "other"):
    """
    Log debug information with enhanced formatting.
    
    Args:
        message: Debug message
        level: Log level (INFO, DEBUG, WARNING, ERROR)
        category: Category for organizing the log entry
    """
    logger = _run_logger or logging.getLogger(__name__)
    formatted = f"🔍 [{category.upper()}] DEBUG: {message}"
    
    if level == "DEBUG":
        logger.debug(formatted)
    elif level == "WARNING":
        logger.warning(formatted)
    elif level == "ERROR":
        logger.error(formatted)
    else:
        logger.info(formatted)


def log_error(page: "Page", error_msg: str, exception: Optional[Exception] = None):
    """
    Log error with screenshot in the errors category.
    
    Args:
        page: Playwright Page instance
        error_msg: Error message
        exception: Optional exception object
    """
    logger = _run_logger or logging.getLogger(__name__)
    
    full_msg = f"❌ ERROR: {error_msg}"
    if exception:
        full_msg += f"\n   Exception: {type(exception).__name__}: {str(exception)}"
    
    logger.error(full_msg)
    
    # Take error screenshot
    try:
        take_debug_screenshot(page, f"error_{error_msg[:30].replace(' ', '_')}", category="errors", description=error_msg)
    except:
        pass


def log_success(message: str, category: str = "other"):
    """
    Log success message.
    
    Args:
        message: Success message
        category: Category for organizing the log entry
    """
    logger = _run_logger or logging.getLogger(__name__)
    logger.info(f"✅ [{category.upper()}] SUCCESS: {message}")


def get_current_session_dir() -> Optional[Path]:
    """Get the current debug session directory."""
    return _current_run_dir


def is_debug_enabled() -> bool:
    """Check if debug output is enabled via environment variable."""
    return _debug_enabled


def create_category_log(category: str, content: str):
    """
    Create a specific log file within a category folder.
    
    Args:
        category: Category name (login, navigation, extraction, etc.)
        content: Content to write to the log file
    """
    # Skip if debug output is disabled or no run directory
    if not _debug_enabled or not _current_run_dir:
        return
    
    category_dir = _current_run_dir / category
    category_dir.mkdir(exist_ok=True)
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    log_file = category_dir / f"{category}_{timestamp}.log"
    
    with open(log_file, 'w', encoding='utf-8') as f:
        f.write(f"=== {category.upper()} LOG ===\n")
        f.write(f"Timestamp: {datetime.now().isoformat()}\n")
        f.write("="*70 + "\n\n")
        f.write(content)
    
    logger = _run_logger or logging.getLogger(__name__)
    logger.info(f"📝 Category log created: {log_file}")


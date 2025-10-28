"""
Browser configuration utility with anti-detection settings.

This module provides browser context creation with realistic fingerprinting
to avoid bot detection on Facebook and X/Twitter.
"""

from playwright.sync_api import Browser, BrowserContext
from pathlib import Path
import logging

import config

logger = logging.getLogger(__name__)


def create_browser_context(
    browser: Browser,
    storage_state_path: str = None
) -> BrowserContext:
    """
    Create browser context with anti-detection configuration.
    
    Args:
        browser: Playwright Browser instance
        storage_state_path: Optional path to saved storage state (auth session)
        
    Returns:
        BrowserContext configured with anti-detection settings
    """
    context_options = {
        'user_agent': config.USER_AGENT,
        'viewport': {'width': 1920, 'height': 1080},
        'locale': config.LOCALE,
        'timezone_id': config.TIMEZONE,
        'device_scale_factor': 1,
        'has_touch': False,
        'java_script_enabled': True
    }
    
    # Load saved session if available
    if storage_state_path and Path(storage_state_path).exists():
        context_options['storage_state'] = storage_state_path
        logger.info(f"Loading storage state from: {storage_state_path}")
    else:
        logger.info("No storage state provided or file not found")
    
    context = browser.new_context(**context_options)
    
    logger.info("Browser context created with anti-detection settings")
    logger.debug(f"User Agent: {config.USER_AGENT}")
    logger.debug(f"Viewport: 1920x1080")
    logger.debug(f"Locale: {config.LOCALE}, Timezone: {config.TIMEZONE}")
    
    return context



"""
Selector strategies with fallback patterns for Facebook.

This module provides multiple fallback selectors for each element
to handle Facebook's dynamic DOM changes.

Selectors validated via Playwright MCP on 2025-10-09.
"""

from playwright.sync_api import Page, Locator
from typing import List, Optional
import logging

logger = logging.getLogger(__name__)


# Facebook Login Selectors (validated via MCP)
EMAIL_SELECTORS = [
    '#email',                           # Primary: ID selector
    'input[name="email"]',              # Fallback: Name attribute
    'input[type="text"]',               # Fallback: Type attribute
    'input[autocomplete="username"]'    # Fallback: Autocomplete attribute
]

PASSWORD_SELECTORS = [
    '#pass',                                # Primary: ID selector
    'input[name="pass"]',                   # Fallback: Name attribute
    'input[type="password"]',               # Fallback: Type attribute
    'input[autocomplete="current-password"]' # Fallback: Autocomplete attribute
]

LOGIN_BUTTON_SELECTORS = [
    'button[name="login"]',     # Primary: Name attribute (validated)
    'button[type="submit"]',    # Fallback: Submit button
    'input[type="submit"]'      # Fallback: Input submit
]

# Facebook Message Content Selectors
# Prioritized based on MCP validation results (Oct 9, 2025)
# Validated on live Facebook profile: https://www.facebook.com/Asirisinfinity5
MESSAGE_SELECTORS = [
    'div[dir="auto"]',                  # PRIMARY: Found 10+ messages (MCP validated)
    'div[data-ad-preview="message"]',   # SECONDARY: Good for specific posts
    'div[role="article"]',              # TERTIARY: Structural elements only
]


def try_selectors(page: Page, selectors: List[str], timeout: int = 5000) -> Optional[Locator]:
    """
    Try multiple selectors and return first visible element.
    
    Args:
        page: Playwright Page instance
        selectors: List of CSS selectors to try in order
        timeout: Milliseconds to wait for each selector
        
    Returns:
        First visible Locator, or None if none found
    """
    for selector in selectors:
        try:
            logger.debug(f"Trying selector: {selector}")
            locator = page.locator(selector)
            
            # Wait for element to be visible
            locator.first.wait_for(state='visible', timeout=timeout)
            
            logger.info(f"[OK] Found element with selector: {selector}")
            return locator.first
            
        except Exception as e:
            logger.debug(f"Selector '{selector}' failed: {e}")
            continue
    
    logger.warning(f"❌ No element found with any of {len(selectors)} selectors")
    return None



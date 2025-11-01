"""
Facebook Page Mode Manager

This module handles detecting and switching to Facebook page mode for posting.
"""

import logging
import time
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError

logger = logging.getLogger(__name__)


def is_in_page_mode(page: Page, page_name: str = None) -> bool:
    """
    Check if currently in page mode by looking for post composer.
    
    Args:
        page: Playwright Page instance
        page_name: Name of the Facebook page (optional, for better validation)
        
    Returns:
        True if in page mode (can post), False otherwise
    """
    try:
        logger.info("Checking if in page mode...")
        
        # Check URL - page mode usually has /profile.php?id= or page-specific URL
        current_url = page.url
        logger.info(f"Current URL: {current_url}")
        
        # Bugfix: The composer is NOT a button! It's clickable text
        # Look for "¿Qué estás pensando, [PageName]?" which proves we're in page mode
        if page_name:
            try:
                # Spanish with page name
                composer_text = f"¿Qué estás pensando, {page_name}?"
                composer = page.get_by_text(composer_text, exact=False).first
                if composer.is_visible(timeout=2000):
                    logger.info(f"✅ In page mode - found composer: '{composer_text}'")
                    return True
            except:
                pass
            
            try:
                # English with page name
                composer_text = f"What's on your mind, {page_name}?"
                composer = page.get_by_text(composer_text, exact=False).first
                if composer.is_visible(timeout=2000):
                    logger.info(f"✅ In page mode - found composer: '{composer_text}'")
                    return True
            except:
                pass
        
        # Fallback: Look for ANY composer (without checking page name)
        post_composer_indicators = [
            "¿Qué estás pensando",
            "What's on your mind",
            "Crear publicación",
            "Create post",
        ]
        
        for indicator in post_composer_indicators:
            try:
                composer = page.get_by_text(indicator, exact=False).first
                if composer.is_visible(timeout=2000):
                    # Get the actual text to see WHO it's asking about
                    actual_text = composer.inner_text(timeout=1000)
                    logger.info(f"Found composer: '{actual_text[:80]}'")
                    
                    # If we have a page name, verify it's in the composer
                    if page_name and page_name in actual_text:
                        logger.info(f"✅ In page mode - composer shows {page_name}")
                        return True
                    elif not page_name:
                        # No page name provided, assume we're good
                        logger.info("✅ In page mode - found composer")
                        return True
                    else:
                        logger.info(f"⚠️  Composer found but doesn't show {page_name}")
            except:
                continue
        
        logger.info("No post composer found - cannot post from this view")
        return False
        
    except Exception as e:
        logger.error(f"Failed to check page mode: {e}")
        return False


def switch_to_page_mode(page: Page, page_name: str, page_url: str = None, max_retries: int = 3) -> bool:
    """
    Switch from personal profile to page mode.
    
    Args:
        page: Playwright Page instance
        page_name: Name of the Facebook page to switch to
        page_url: URL of the Facebook page (needed to reload after profile switch)
        max_retries: Maximum number of attempts
        
    Returns:
        True if successfully switched to page mode
        
    Raises:
        Exception: If switch fails after all retries
    """
    try:
        logger.info(f"Attempting to switch to page mode for: {page_name}")
        
        # Bugfix: Log current state for debugging
        logger.info(f"Current URL before switch: {page.url}")
        
        # Bugfix: Always navigate to Facebook home FIRST before switching
        # This ensures we're not already on the target page, which can cause switch issues
        logger.info("Navigating to Facebook home before switching profiles...")
        page.goto('https://www.facebook.com', wait_until='domcontentloaded')
        page.wait_for_timeout(2000)
        
        for attempt in range(1, max_retries + 1):
            logger.info(f"Switch attempt {attempt}/{max_retries}")
            
            try:
                # Bugfix: Use getByRole approach (from manual test) instead of generic selectors
                # This is more reliable and matches what actually works in the manual recording
                switcher_clicked = False
                
                # Try Spanish first (Tu perfil)
                try:
                    switcher = page.get_by_role('button', name='Tu perfil', exact=True)
                    if switcher.is_visible(timeout=3000):
                        logger.info("Found 'Tu perfil' button (Spanish) using getByRole")
                        switcher.click(timeout=3000)
                        page.wait_for_timeout(2000)
                        switcher_clicked = True
                except Exception as e:
                    logger.debug(f"Spanish 'Tu perfil' button not found: {e}")
                
                # Try English if Spanish failed
                if not switcher_clicked:
                    try:
                        switcher = page.get_by_role('button', name='Your profile', exact=True)
                        if switcher.is_visible(timeout=3000):
                            logger.info("Found 'Your profile' button (English) using getByRole")
                            switcher.click(timeout=3000)
                            page.wait_for_timeout(2000)
                            switcher_clicked = True
                    except Exception as e:
                        logger.debug(f"English 'Your profile' button not found: {e}")
                
                # Fallback to old selectors if getByRole fails
                if not switcher_clicked:
                    logger.warning("getByRole failed, trying legacy selectors...")
                    switcher_selectors = [
                        'div[aria-label*="profile"]',
                        'div[aria-label*="perfil"]',
                        'button[aria-label*="Your profile"]',
                        'button[aria-label*="Tu perfil"]',
                    ]
                    
                    for selector in switcher_selectors:
                        try:
                            switcher = page.locator(selector).first
                            if switcher.is_visible(timeout=2000):
                                logger.info(f"Found profile switcher (legacy): {selector}")
                                switcher.click(timeout=3000)
                                page.wait_for_timeout(2000)
                                switcher_clicked = True
                                break
                        except:
                            continue
                
                if not switcher_clicked:
                    if attempt < max_retries:
                        logger.info(f"Retrying... (attempt {attempt + 1})")
                        page.wait_for_timeout(3000)
                        continue
                    else:
                        logger.error("Failed to find profile switcher after all attempts")
                        return False
                
                # Bugfix: Look for page switch button using getByRole (like manual test)
                # Pattern: "Cambiar a [PageName]" or "Switch to [PageName]"
                logger.info(f"Looking for 'Cambiar a {page_name}' or 'Switch to {page_name}' button...")
                
                page_clicked = False
                
                # Try Spanish: "Cambiar a [PageName]"
                try:
                    switch_button = page.get_by_role('button', name=f'Cambiar a {page_name}')
                    if switch_button.is_visible(timeout=3000):
                        logger.info(f"Found 'Cambiar a {page_name}' button (Spanish)")
                        switch_button.click(timeout=3000)
                        page.wait_for_timeout(3000)
                        page_clicked = True
                except Exception as e:
                    logger.debug(f"Spanish 'Cambiar a' button not found: {e}")
                
                # Try English: "Switch to [PageName]"
                if not page_clicked:
                    try:
                        switch_button = page.get_by_role('button', name=f'Switch to {page_name}')
                        if switch_button.is_visible(timeout=3000):
                            logger.info(f"Found 'Switch to {page_name}' button (English)")
                            switch_button.click(timeout=3000)
                            page.wait_for_timeout(3000)
                            page_clicked = True
                    except Exception as e:
                        logger.debug(f"English 'Switch to' button not found: {e}")
                
                # Fallback to text-based search if getByRole fails
                if not page_clicked:
                    logger.warning("getByRole for page switch failed, trying text-based search...")
                    page_selectors = [
                        f'div[role="button"]:has-text("{page_name}")',
                        f'button:has-text("{page_name}")',
                        f'a:has-text("{page_name}")',
                    ]
                    
                    for selector in page_selectors:
                        try:
                            page_element = page.locator(selector).first
                            if page_element.is_visible(timeout=3000):
                                logger.info(f"Found page element (legacy): {selector}")
                                page_element.click(timeout=3000)
                                page.wait_for_timeout(3000)
                                page_clicked = True
                                break
                        except Exception as e:
                            logger.debug(f"Page selector '{selector}' not found: {e}")
                            continue
                
                if not page_clicked:
                    if attempt < max_retries:
                        logger.info(f"Retrying... (attempt {attempt + 1})")
                        continue
                    else:
                        logger.error(f"Failed to find page '{page_name}' after all attempts")
                        return False
                
                # Bugfix: After clicking "Cambiar a [Page]", the page needs time to switch
                # and may need a reload to show the updated feed as the page
                logger.info("Waiting for profile switch to complete...")
                page.wait_for_timeout(5000)  # Increased wait time
                
                # Bugfix: Log URL after switch for debugging
                logger.info(f"URL after switch attempt: {page.url}")
                
                # Bugfix: Reload the page to ensure we see the switched profile's feed
                logger.info("Reloading page to refresh feed with switched profile...")
                try:
                    page.reload(wait_until='domcontentloaded')
                    page.wait_for_timeout(4000)  # Wait for reload to complete
                    logger.info(f"Page reloaded, current URL: {page.url}")
                except Exception as reload_e:
                    logger.warning(f"Page reload failed: {reload_e}")
                
                # If page_url provided and still no post button, try navigating there as fallback
                if page_url:
                    logger.info("Checking if post button appeared after reload...")
                    try:
                        # Quick check for post button
                        if page.get_by_role('button', name='¿Qué estás pensando?').is_visible(timeout=2000):
                            logger.info("✅ Post button found after reload!")
                        else:
                            logger.info("Post button not found after reload, trying page URL as fallback...")
                            page.goto(page_url, wait_until='domcontentloaded')
                            page.wait_for_timeout(3000)
                            logger.info(f"Navigated to page URL: {page.url}")
                    except Exception as check_e:
                        logger.debug(f"Post button check/fallback failed: {check_e}")
                
                # Check if we can see indicators that we're now in page mode
                if is_in_page_mode(page, page_name):
                    logger.info(f"✅ Successfully switched to page mode: {page_name}")
                    return True
                else:
                    logger.warning("Page mode indicators not detected after switch")
                    if attempt < max_retries:
                        logger.info("Retrying switch...")
                        continue
                    
            except Exception as e:
                logger.error(f"Error during switch attempt {attempt}: {e}")
                if attempt < max_retries:
                    logger.info("Retrying...")
                    page.wait_for_timeout(3000)
                    continue
        
        # All attempts exhausted
        logger.error(f"Failed to switch to page mode after {max_retries} attempts")
        return False
        
    except Exception as e:
        logger.error(f"Failed to switch to page mode: {e}")
        return False


def ensure_page_mode(page: Page, page_name: str, page_url: str = None) -> bool:
    """
    Ensure we're in page mode, switch if necessary.
    
    Hybrid approach:
    1. Try direct navigation to page URL (fastest, most reliable)
    2. Fallback to profile switcher if direct navigation fails
    
    Args:
        page: Playwright Page instance
        page_name: Name of the Facebook page
        page_url: Direct URL to the Facebook page (optional but recommended)
        
    Returns:
        True if in page mode (or successfully switched)
    """
    try:
        # Check if already in page mode
        if is_in_page_mode(page, page_name):
            logger.info("✅ Already in page mode - can post")
            return True
        
        # Bugfix: Method 1 - Direct navigation to page URL (preferred method)
        if page_url:
            logger.info(f"Method 1: Attempting direct navigation to page URL: {page_url}")
            try:
                page.goto(page_url, wait_until='domcontentloaded')
                page.wait_for_timeout(3000)
                
                # Verify we can now post
                if is_in_page_mode(page, page_name):
                    logger.info("✅ Direct navigation successful - ready to post")
                    return True
                else:
                    logger.warning("Direct navigation completed but cannot post yet")
            except Exception as e:
                logger.warning(f"Direct navigation failed: {e}")
        else:
            logger.info("No page URL provided - skipping direct navigation method")
        
        # Bugfix: Method 2 - Profile switcher (fallback method)
        logger.info("Method 2: Attempting profile switcher...")
        if switch_to_page_mode(page, page_name, page_url):
            logger.info("✅ Profile switcher successful")
            return True
        
        logger.error("Both methods failed - cannot switch to page mode")
        return False
        
    except Exception as e:
        logger.error(f"Failed to ensure page mode: {e}")
        return False


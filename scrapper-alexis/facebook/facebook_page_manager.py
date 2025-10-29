"""
Facebook Page Mode Manager

This module handles detecting and switching to Facebook page mode for posting.
"""

import logging
import time
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError

logger = logging.getLogger(__name__)


def is_in_page_mode(page: Page) -> bool:
    """
    Check if currently in page mode.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        True if in page mode, False if in personal profile mode
    """
    try:
        logger.info("Checking if in page mode...")
        
        # Check URL - page mode usually has /profile.php?id= or page-specific URL
        current_url = page.url
        logger.info(f"Current URL: {current_url}")
        
        # Look for page mode indicators in the UI
        # Facebook shows different UI elements when you're posting as a page
        try:
            # Look for "Switch Profile" or page name indicator
            page_indicators = [
                'div[aria-label*="witch"]',  # "Switch" button
                'div[role="button"]:has-text("Cambiar")',
                'div[role="button"]:has-text("Switch")',
            ]
            
            for selector in page_indicators:
                try:
                    indicator = page.locator(selector).first
                    if indicator.is_visible(timeout=2000):
                        logger.info(f"Found page mode indicator: {selector}")
                        return True
                except:
                    continue
            
            logger.info("No page mode indicators found - assuming personal profile mode")
            return False
            
        except Exception as e:
            logger.warning(f"Error checking page mode: {e}")
            return False
            
    except Exception as e:
        logger.error(f"Failed to check page mode: {e}")
        return False


def switch_to_page_mode(page: Page, page_name: str, max_retries: int = 3) -> bool:
    """
    Switch from personal profile to page mode.
    
    Args:
        page: Playwright Page instance
        page_name: Name of the Facebook page to switch to
        max_retries: Maximum number of attempts
        
    Returns:
        True if successfully switched to page mode
        
    Raises:
        Exception: If switch fails after all retries
    """
    try:
        logger.info(f"Attempting to switch to page mode for: {page_name}")
        
        # Navigate to Facebook home if not already there
        if 'facebook.com' not in page.url:
            logger.info("Navigating to Facebook...")
            page.goto('https://www.facebook.com', wait_until='domcontentloaded')
            page.wait_for_timeout(3000)
        
        for attempt in range(1, max_retries + 1):
            logger.info(f"Switch attempt {attempt}/{max_retries}")
            
            try:
                # Look for profile/page switcher button
                switcher_selectors = [
                    'div[aria-label*="profile"]',
                    'div[aria-label*="perfil"]',
                    'button[aria-label*="Your profile"]',
                    'button[aria-label*="Tu perfil"]',
                    # Generic role buttons that might be the switcher
                    'div[role="button"]:has-text("Your profile")',
                    'div[role="button"]:has-text("Tu perfil")',
                ]
                
                switcher_clicked = False
                for selector in switcher_selectors:
                    try:
                        switcher = page.locator(selector).first
                        if switcher.is_visible(timeout=2000):
                            logger.info(f"Found profile switcher: {selector}")
                            switcher.click(timeout=3000)
                            page.wait_for_timeout(2000)
                            switcher_clicked = True
                            break
                    except:
                        continue
                
                if not switcher_clicked:
                    logger.warning("Could not find profile switcher button")
                    
                    # Try alternative: Click on profile name/avatar area
                    try:
                        # Look for any clickable element with the current user's info
                        page.locator('img[alt*="perfil"]').first.click(timeout=3000)
                        page.wait_for_timeout(2000)
                        switcher_clicked = True
                    except:
                        pass
                
                if not switcher_clicked:
                    if attempt < max_retries:
                        logger.info(f"Retrying... (attempt {attempt + 1})")
                        page.wait_for_timeout(3000)
                        continue
                    else:
                        logger.error("Failed to find profile switcher after all attempts")
                        return False
                
                # Look for the page name in the dropdown/modal
                logger.info(f"Looking for page: {page_name}")
                
                # Try to find and click on the page name
                page_selectors = [
                    f'div[role="button"]:has-text("{page_name}")',
                    f'button:has-text("{page_name}")',
                    f'a:has-text("{page_name}")',
                    f'span:has-text("{page_name}")',
                ]
                
                page_clicked = False
                for selector in page_selectors:
                    try:
                        page_element = page.locator(selector).first
                        if page_element.is_visible(timeout=3000):
                            logger.info(f"Found page element: {selector}")
                            page_element.click(timeout=3000)
                            page.wait_for_timeout(3000)
                            page_clicked = True
                            break
                    except Exception as e:
                        logger.debug(f"Page selector '{selector}' not found: {e}")
                        continue
                
                if not page_clicked:
                    logger.warning(f"Could not find page '{page_name}' in switcher")
                    
                    # Try case-insensitive search
                    try:
                        page_element = page.get_by_text(page_name, exact=False).first
                        if page_element.is_visible(timeout=2000):
                            logger.info("Found page using case-insensitive search")
                            page_element.click(timeout=3000)
                            page.wait_for_timeout(3000)
                            page_clicked = True
                    except:
                        pass
                
                if not page_clicked:
                    if attempt < max_retries:
                        logger.info(f"Retrying... (attempt {attempt + 1})")
                        continue
                    else:
                        logger.error(f"Failed to find page '{page_name}' after all attempts")
                        return False
                
                # Verify we switched successfully
                logger.info("Verifying page mode switch...")
                page.wait_for_timeout(2000)
                
                # Check if we can see indicators that we're now in page mode
                if is_in_page_mode(page):
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


def ensure_page_mode(page: Page, page_name: str) -> bool:
    """
    Ensure we're in page mode, switch if necessary.
    
    Args:
        page: Playwright Page instance
        page_name: Name of the Facebook page
        
    Returns:
        True if in page mode (or successfully switched)
    """
    try:
        # Check if already in page mode
        if is_in_page_mode(page):
            logger.info("Already in page mode")
            return True
        
        # Not in page mode, attempt to switch
        logger.info("Not in page mode, switching...")
        return switch_to_page_mode(page, page_name)
        
    except Exception as e:
        logger.error(f"Failed to ensure page mode: {e}")
        return False


#!/usr/bin/env python3
"""
Facebook Page Poster
Posts approved images to a Facebook page.
"""

import sys
import os
from pathlib import Path

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent))

from dotenv import load_dotenv
load_dotenv('copy.env')

import logging
from datetime import datetime
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

import config
from core.exceptions import LoginError, NavigationError
from utils.browser_config import create_browser_context
from facebook.facebook_auth import check_auth_state, verify_logged_in, login_facebook_with_retry, save_auth_state
from facebook.facebook_page_manager import ensure_page_mode
from core.database import get_database, initialize_database
from core.debug_helper import take_debug_screenshot, DebugSession

# Create logs directory
logs_dir = Path('logs')
logs_dir.mkdir(exist_ok=True)

# Configure logging
logging.basicConfig(
    level=getattr(logging, config.LOG_LEVEL),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(
            logs_dir / f'page_poster_{datetime.now().strftime("%Y%m%d")}.log',
            encoding='utf-8'
        ),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)


def get_posting_settings():
    """Get posting settings from database."""
    try:
        import sqlite3
        
        # Connect to Laravel database
        db_path = '/var/www/html/database/database.sqlite'
        if not os.path.exists(db_path):
            logger.error(f"Laravel database not found at {db_path}")
            return None
        
        conn = sqlite3.connect(db_path)
        cursor = conn.execute("SELECT page_name, interval_min, interval_max, enabled FROM posting_settings LIMIT 1")
        result = cursor.fetchone()
        conn.close()
        
        if result:
            return {
                'page_name': result[0],
                'interval_min': result[1],
                'interval_max': result[2],
                'enabled': bool(result[3])
            }
        
        return None
        
    except Exception as e:
        logger.error(f"Failed to get posting settings: {e}")
        return None


def get_next_approved_image():
    """Get the next approved image to post."""
    try:
        import sqlite3
        
        # Connect to scraper database
        db = get_database()
        
        # Get oldest approved but not posted image
        query = """
            SELECT m.id, m.message_text, m.image_path
            FROM messages m
            WHERE m.image_generated = 1
              AND m.approved_for_posting = 1
              AND (m.posted_to_page = 0 OR m.posted_to_page IS NULL)
            ORDER BY m.approved_at ASC
            LIMIT 1
        """
        
        with db.get_connection() as conn:
            cursor = conn.execute(query)
            result = cursor.fetchone()
            
            if result:
                return {
                    'id': result['id'],
                    'message_text': result['message_text'],
                    'image_path': result['image_path']
                }
        
        return None
        
    except Exception as e:
        logger.error(f"Failed to get next approved image: {e}")
        return None


def mark_as_posted(message_id):
    """Mark message as posted to page."""
    try:
        import sqlite3
        
        # Update scraper database
        db = get_database()
        
        with db.get_connection() as conn:
            conn.execute(
                "UPDATE messages SET posted_to_page = 1, posted_to_page_at = ? WHERE id = ?",
                (datetime.now(), message_id)
            )
            conn.commit()
        
        logger.info(f"Marked message {message_id} as posted to page")
        return True
        
    except Exception as e:
        logger.error(f"Failed to mark message as posted: {e}")
        return False


def post_image_to_page(page, image_path: str) -> bool:
    """
    Post an image to the Facebook page.
    
    Args:
        page: Playwright Page instance
        image_path: Path to the image file
        
    Returns:
        True if successfully posted
    """
    try:
        logger.info("="*70)
        logger.info("POSTING IMAGE TO FACEBOOK PAGE")
        logger.info("="*70)
        
        # Navigate to Facebook home
        logger.info("Navigating to Facebook...")
        page.goto('https://www.facebook.com', wait_until='domcontentloaded')
        page.wait_for_timeout(3000)
        
        take_debug_screenshot(page, "01_facebook_home", "page_posting", "Facebook home page")
        
        # Look for "What's on your mind?" or posting box
        logger.info("Looking for post creation area...")
        
        post_box_selectors = [
            'div[role="button"][aria-label*="Create a post"]',
            'div[role="button"][aria-label*="Crear una publicación"]',
            'div[role="button"]:has-text("What\'s on your mind")',
            'div[role="button"]:has-text("¿Qué estás pensando")',
            'span:has-text("What\'s on your mind")',
            'span:has-text("¿Qué estás pensando")',
        ]
        
        post_box_clicked = False
        for selector in post_box_selectors:
            try:
                post_box = page.locator(selector).first
                if post_box.is_visible(timeout=3000):
                    logger.info(f"Found post box: {selector}")
                    post_box.click(timeout=3000)
                    page.wait_for_timeout(2000)
                    post_box_clicked = True
                    break
            except:
                continue
        
        if not post_box_clicked:
            logger.error("Could not find post creation box")
            return False
        
        take_debug_screenshot(page, "02_post_dialog_opened", "page_posting", "Post dialog opened")
        
        # Click on "Photo/video" button
        logger.info("Looking for Photo/video button...")
        
        photo_button_selectors = [
            'div[role="button"]:has-text("Photo/video")',
            'div[role="button"]:has-text("Foto/video")',
            'button:has-text("Photo/video")',
            'button:has-text("Foto/video")',
            'div[aria-label*="Photo"]',
            'div[aria-label*="Foto"]',
        ]
        
        photo_button_clicked = False
        for selector in photo_button_selectors:
            try:
                photo_button = page.locator(selector).first
                if photo_button.is_visible(timeout=3000):
                    logger.info(f"Found photo button: {selector}")
                    photo_button.click(timeout=3000)
                    page.wait_for_timeout(2000)
                    photo_button_clicked = True
                    break
            except:
                continue
        
        if not photo_button_clicked:
            logger.error("Could not find Photo/video button")
            return False
        
        take_debug_screenshot(page, "03_before_file_upload", "page_posting", "Before file upload")
        
        # Upload the image file
        logger.info(f"Uploading image: {image_path}")
        
        # Get the full path to the image
        if not os.path.isabs(image_path):
            image_full_path = os.path.join('/app/data/message_images', os.path.basename(image_path))
        else:
            image_full_path = image_path
        
        if not os.path.exists(image_full_path):
            logger.error(f"Image file not found: {image_full_path}")
            return False
        
        logger.info(f"Full image path: {image_full_path}")
        
        # Find file input and upload
        try:
            file_input = page.locator('input[type="file"]').first
            file_input.set_input_files(image_full_path, timeout=10000)
            logger.info("Image uploaded successfully")
            page.wait_for_timeout(3000)
        except Exception as e:
            logger.error(f"Failed to upload image: {e}")
            return False
        
        take_debug_screenshot(page, "04_image_uploaded", "page_posting", "Image uploaded")
        
        # Wait for image to be processed
        logger.info("Waiting for image to be processed...")
        page.wait_for_timeout(5000)
        
        take_debug_screenshot(page, "05_before_post", "page_posting", "Before clicking Post")
        
        # Click "Post" button
        logger.info("Looking for Post button...")
        
        post_button_selectors = [
            'div[role="button"]:has-text("Post")',
            'div[role="button"]:has-text("Publicar")',
            'button:has-text("Post")',
            'button:has-text("Publicar")',
            'div[aria-label="Post"]',
            'div[aria-label="Publicar"]',
        ]
        
        post_button_clicked = False
        for selector in post_button_selectors:
            try:
                post_button = page.locator(selector).first
                if post_button.is_visible(timeout=3000):
                    logger.info(f"Found Post button: {selector}")
                    post_button.click(timeout=3000)
                    page.wait_for_timeout(5000)
                    post_button_clicked = True
                    break
            except:
                continue
        
        if not post_button_clicked:
            logger.error("Could not find Post button")
            return False
        
        take_debug_screenshot(page, "06_after_post", "page_posting", "After clicking Post")
        
        # Wait for post to complete
        logger.info("Waiting for post to complete...")
        page.wait_for_timeout(5000)
        
        logger.info("✅ Image posted successfully!")
        return True
        
    except Exception as e:
        logger.error(f"Failed to post image: {e}")
        take_debug_screenshot(page, "error_posting", "page_posting", f"Error: {e}")
        return False


def main():
    """Main execution function."""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    debug_session = DebugSession(f"page_poster_{timestamp}")
    
    try:
        logger.info("="*70)
        logger.info("FACEBOOK PAGE POSTER")
        logger.info("="*70)
        
        # Get posting settings
        logger.info("Loading posting settings...")
        settings = get_posting_settings()
        
        if not settings:
            logger.error("Failed to load posting settings")
            return 1
        
        if not settings['enabled']:
            logger.info("Page posting is disabled in settings")
            return 0
        
        page_name = settings['page_name']
        if not page_name:
            logger.error("Page name not configured")
            return 1
        
        logger.info(f"Target page: {page_name}")
        
        # Get next approved image
        logger.info("Looking for approved images...")
        image_data = get_next_approved_image()
        
        if not image_data:
            logger.info("No approved images to post")
            return 0
        
        logger.info(f"Found approved image: ID {image_data['id']}")
        logger.info(f"Image path: {image_data['image_path']}")
        
        # Initialize database
        initialize_database()
        
        with sync_playwright() as p:
            # Launch browser
            logger.info("Launching browser...")
            
            firefox_options = {
                'headless': config.HEADLESS,
                'slow_mo': config.SLOW_MO if not config.HEADLESS else 0,
            }
            
            if config.PROXY_CONFIG:
                firefox_options['proxy'] = config.PROXY_CONFIG
                logger.info(f"Using proxy: {config.PROXY_CONFIG['server']}")
            
            browser = p.firefox.launch(**firefox_options)
            logger.info("Browser launched")
            
            try:
                # Create browser context with auth state
                auth_file_exists = check_auth_state()
                
                if auth_file_exists:
                    logger.info("Loading saved Facebook session...")
                    context = create_browser_context(browser, 'auth/auth_facebook.json')
                else:
                    logger.info("No saved session - will need to login")
                    context = create_browser_context(browser)
                
                page = context.new_page()
                
                # Verify login
                logger.info("Verifying login status...")
                is_logged_in = verify_logged_in(page)
                
                if not is_logged_in:
                    logger.info("Not logged in - performing login...")
                    
                    # Get credentials
                    from core.profile_manager import get_profile_manager
                    profile_manager = get_profile_manager()
                    facebook_creds = profile_manager.get_facebook_credentials()
                    
                    if not facebook_creds:
                        raise LoginError("Facebook credentials not found")
                    
                    config.FACEBOOK_EMAIL = facebook_creds['username']
                    config.FACEBOOK_PASSWORD = facebook_creds['password']
                    
                    login_facebook_with_retry(page, max_retries=3, wait_time=50)
                    save_auth_state(context, page)
                    logger.info("Login successful")
                else:
                    logger.info("Already logged in")
                
                # Ensure we're in page mode
                logger.info(f"Ensuring page mode for: {page_name}")
                if not ensure_page_mode(page, page_name):
                    logger.error("Failed to switch to page mode")
                    return 1
                
                # Post the image
                logger.info("Posting image to page...")
                success = post_image_to_page(page, image_data['image_path'])
                
                if success:
                    # Mark as posted
                    mark_as_posted(image_data['id'])
                    logger.info("="*70)
                    logger.info("✅ POST SUCCESSFUL")
                    logger.info("="*70)
                    return 0
                else:
                    logger.error("Failed to post image")
                    return 1
                
            finally:
                logger.info("Closing browser...")
                browser.close()
        
    except Exception as e:
        logger.error(f"Fatal error: {e}", exc_info=True)
        return 1
    
    finally:
        try:
            debug_session.close()
        except:
            pass


if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)


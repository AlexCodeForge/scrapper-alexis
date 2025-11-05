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
        
        # Connect to shared database
        possible_paths = [
            '/var/www/scrapper-alexis/data/scraper.db',
            '/var/www/alexis-scrapper-docker/scrapper-alexis/data/scraper.db',
            'data/scraper.db',
        ]
        
        db_path = None
        for path in possible_paths:
            if os.path.exists(path):
                db_path = path
                break
        
        if not db_path:
            logger.error(f"Database not found in any of: {possible_paths}")
            return None
        
        conn = sqlite3.connect(db_path)
        cursor = conn.execute("SELECT page_name, page_url, interval_min, interval_max, enabled FROM posting_settings LIMIT 1")
        result = cursor.fetchone()
        conn.close()
        
        if result:
            return {
                'page_name': result[0],
                'page_url': result[1],
                'interval_min': result[2],
                'interval_max': result[3],
                'enabled': bool(result[4])
            }
        
        return None
        
    except Exception as e:
        logger.error(f"Failed to get posting settings: {e}")
        return None


def get_next_approved_image():
    """Get the next approved image to post (only auto-post enabled)."""
    try:
        import sqlite3
        
        # Connect to scraper database
        db = get_database()
        
        # Get oldest approved but not posted image (only auto-post enabled)
        query = """
            SELECT m.id, m.message_text, m.image_path
            FROM messages m
            WHERE m.image_generated = 1
              AND m.approved_for_posting = 1
              AND m.auto_post_enabled = 1
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


def get_specific_image(image_id: int):
    """Get a specific image by ID for manual posting."""
    try:
        import sqlite3
        
        # Connect to scraper database
        db = get_database()
        
        # Get specific image by ID
        query = """
            SELECT m.id, m.message_text, m.image_path, m.approved_for_posting, m.posted_to_page
            FROM messages m
            WHERE m.id = ?
              AND m.image_generated = 1
        """
        
        with db.get_connection() as conn:
            cursor = conn.execute(query, (image_id,))
            result = cursor.fetchone()
            
            if result:
                return {
                    'id': result['id'],
                    'message_text': result['message_text'],
                    'image_path': result['image_path'],
                    'approved_for_posting': bool(result['approved_for_posting']),
                    'posted_to_page': bool(result['posted_to_page'])
                }
        
        return None
        
    except Exception as e:
        logger.error(f"Failed to get specific image {image_id}: {e}")
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


def validate_page_login(page, page_url: str) -> bool:
    """
    Validate that we're logged into the correct page by navigating to it.
    
    Args:
        page: Playwright Page instance
        page_url: URL of the Facebook page to validate
        
    Returns:
        True if successfully validated
    """
    try:
        logger.info("="*70)
        logger.info("VALIDATING PAGE LOGIN")
        logger.info("="*70)
        
        # Bugfix: Use the same validation logic as is_in_page_mode() for consistency
        from facebook.facebook_page_manager import is_in_page_mode
        
        logger.info(f"Current URL: {page.url}")
        
        # Check if we can already post (we're on the right page)
        if is_in_page_mode(page):
            logger.info("✅ Already on page and can post - validation successful")
            take_debug_screenshot(page, "01_page_validation_success", "page_posting", f"Page validation successful")
            return True
        
        # If not, try navigating to page URL
        logger.info(f"Not ready to post, navigating to page URL: {page_url}")
        page.goto(page_url, wait_until='domcontentloaded')
        page.wait_for_timeout(3000)
        
        take_debug_screenshot(page, "01_page_validation_after_nav", "page_posting", f"After navigation to: {page_url}")
        
        # Check again after navigation
        if is_in_page_mode(page):
                    logger.info("✅ Page login validated - can post to this page")
                    return True
        
        logger.error("❌ Could not find post creation area - may not have permission to post")
        return False
        
    except Exception as e:
        logger.error(f"Failed to validate page login: {e}")
        return False


def post_image_to_page(page, image_path: str, page_name: str = None) -> bool:
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
        
        # Bugfix: Click the post composer to open the "Crear publicación" modal
        logger.info(f"Current URL: {page.url}")
        logger.info("Clicking post composer to open creation modal...")
        
        take_debug_screenshot(page, "02_before_clicking_composer", "page_posting", "Before clicking composer")
        
        # Bugfix: The composer is NOT a button! It's a clickable text/div element
        # Look for "¿Qué estás pensando, [PageName]?" and click it
        composer_clicked = False
        
        # Try with page name first (proves we're in page mode)
        if page_name:
            try:
                composer_text = f"¿Qué estás pensando, {page_name}?"
                logger.info(f"Looking for composer: {composer_text}")
                composer = page.get_by_text(composer_text).first
                if composer.is_visible(timeout=3000):
                    logger.info(f"✅ Found composer with page name: {composer_text}")
                    composer.click(timeout=3000)
                    page.wait_for_timeout(3000)
                    composer_clicked = True
            except Exception as e:
                logger.debug(f"Composer with page name not found: {e}")
        
        # Fallback: Try generic search
        if not composer_clicked:
            try:
                logger.info("Trying generic composer search...")
                # Look for clickable element with "¿Qué estás pensando"
                composer = page.locator('[aria-label*="¿Qué estás pensando"], [placeholder*="¿Qué estás pensando"], div:has-text("¿Qué estás pensando")').first
                if composer.is_visible(timeout=3000):
                    logger.info("✅ Found composer using generic search")
                    composer.click(timeout=3000)
                    page.wait_for_timeout(3000)
                    composer_clicked = True
            except Exception as e:
                logger.debug(f"Generic composer search failed: {e}")
        
        # Last resort: Try English
        if not composer_clicked:
            try:
                logger.info("Trying English composer...")
                composer = page.locator('[aria-label*="What\'s on your mind"], [placeholder*="What\'s on your mind"]').first
                if composer.is_visible(timeout=3000):
                    logger.info("✅ Found English composer")
                    composer.click(timeout=3000)
                    page.wait_for_timeout(3000)
                    composer_clicked = True
            except Exception as e:
                logger.debug(f"English composer search failed: {e}")
        
        if not composer_clicked:
            logger.error("❌ Could not find or click post composer")
            take_debug_screenshot(page, "ERROR_no_composer_click", "page_posting", "ERROR: Composer not clicked")
            return False
        
        logger.info("✅ Composer clicked - 'Crear publicación' modal should be open")
        take_debug_screenshot(page, "03_modal_opened", "page_posting", "Create post modal opened")
        
        # Get the full path to the image first
        if not os.path.isabs(image_path):
            # Try multiple possible data directories
            possible_data_dirs = [
                '/var/www/alexis-scrapper-docker/scrapper-alexis/data',
                '/var/www/scrapper-alexis/data',
                'data',
            ]
            
            for data_dir in possible_data_dirs:
                potential_path = os.path.join(data_dir, 'message_images', os.path.basename(image_path))
                if os.path.exists(potential_path):
                    image_full_path = potential_path
                    break
            else:
                image_full_path = os.path.join(possible_data_dirs[0], 'message_images', os.path.basename(image_path))
        else:
            image_full_path = image_path
        
        if not os.path.exists(image_full_path):
            logger.error(f"Image file not found: {image_full_path}")
            return False
        
        logger.info(f"Full image path: {image_full_path}")
        
        # Bugfix: Find the file input BEFORE clicking Foto/video button
        # Then use with_file_chooser to handle the upload properly
        logger.info("Looking for Foto/video button...")
        
        upload_success = False
        photo_button = None
        
        try:
            # Try Spanish first
            photo_button = page.get_by_role('button', name='Foto/video')
            if photo_button.is_visible(timeout=5000):
                logger.info("Found Foto/video button (Spanish)")
                
                # Use expect_file_chooser context manager to handle file upload
                with page.expect_file_chooser() as fc_info:
                    photo_button.click(timeout=3000)
                    file_chooser = fc_info.value
                    file_chooser.set_files(image_full_path)
                
                logger.info("Image uploaded successfully")
                page.wait_for_timeout(3000)
                upload_success = True
        except Exception as e:
            logger.debug(f"Spanish Foto/video button failed: {e}")
        
        if not upload_success:
            try:
                # Try English
                photo_button = page.get_by_role('button', name='Photo/video')
                if photo_button.is_visible(timeout=5000):
                    logger.info("Found Photo/video button (English)")
                    
                    # Use expect_file_chooser context manager
                    with page.expect_file_chooser() as fc_info:
                        photo_button.click(timeout=3000)
                    file_chooser = fc_info.value
                    file_chooser.set_files(image_full_path)
                    
                    logger.info("Image uploaded successfully")
                    page.wait_for_timeout(3000)
                    upload_success = True
            except Exception as e:
                logger.debug(f"English Photo/video button failed: {e}")
        
        if not upload_success:
            logger.error("Could not find Foto/video button or upload file")
            take_debug_screenshot(page, "ERROR_no_photo_button", "page_posting", "ERROR: No Foto/video button found")
            
            # Bugfix: Log all visible buttons for debugging
            try:
                buttons = page.locator('button, div[role="button"]').all()
                logger.error(f"Found {len(buttons)} buttons on page")
                for i, btn in enumerate(buttons[:10]):  # Log first 10
                    try:
                        text = btn.inner_text(timeout=1000)
                        logger.error(f"Button {i}: {text[:50]}")
                    except:
                        pass
            except Exception as debug_e:
                logger.error(f"Failed to debug buttons: {debug_e}")
            
            return False
        
        take_debug_screenshot(page, "05_image_uploaded", "page_posting", "Image uploaded")
        
        # Wait for image to be processed
        logger.info("Waiting for image to be processed...")
        page.wait_for_timeout(5000)
        
        take_debug_screenshot(page, "06_before_next", "page_posting", "Before clicking Siguiente/Next")
        
        # Bugfix: Click "Siguiente" / "Next" button (from manual recording workflow)
        logger.info("Looking for Siguiente/Next button...")
        
        next_button_clicked = False
        try:
            # Try Spanish first
            next_button = page.get_by_role('button', name='Siguiente')
            if next_button.is_visible(timeout=5000):
                logger.info("Found Siguiente button (Spanish)")
                next_button.click(timeout=3000)
                page.wait_for_timeout(3000)
                next_button_clicked = True
        except Exception as e:
            logger.debug(f"Spanish Siguiente button not found: {e}")
        
        if not next_button_clicked:
            try:
                # Try English
                next_button = page.get_by_role('button', name='Next')
                if next_button.is_visible(timeout=5000):
                    logger.info("Found Next button (English)")
                    next_button.click(timeout=3000)
                    page.wait_for_timeout(3000)
                    next_button_clicked = True
            except Exception as e:
                logger.debug(f"English Next button not found: {e}")
        
        if not next_button_clicked:
            logger.warning("Could not find Siguiente/Next button - trying to post directly")
            take_debug_screenshot(page, "WARNING_no_next_button", "page_posting", "WARNING: No Siguiente button found")
        
        take_debug_screenshot(page, "07_before_post", "page_posting", "Before clicking Publicar/Post")
        
        # Bugfix: Click "Publicar" / "Post" button with getByRole (exact match from manual recording)
        logger.info("Looking for Publicar/Post button...")
        
        post_button_clicked = False
        try:
            # Try Spanish first - with exact=True like manual recording
            post_button = page.get_by_role('button', name='Publicar', exact=True)
            if post_button.is_visible(timeout=5000):
                logger.info("Found Publicar button (Spanish, exact match)")
                post_button.click(timeout=3000)
                page.wait_for_timeout(5000)
                post_button_clicked = True
        except Exception as e:
            logger.debug(f"Spanish Publicar button not found: {e}")
        
        if not post_button_clicked:
            try:
                # Try English
                post_button = page.get_by_role('button', name='Post', exact=True)
                if post_button.is_visible(timeout=5000):
                    logger.info("Found Post button (English, exact match)")
                    post_button.click(timeout=3000)
                    page.wait_for_timeout(5000)
                    post_button_clicked = True
            except Exception as e:
                logger.debug(f"English Post button not found: {e}")
        
        if not post_button_clicked:
            logger.error("Could not find Publicar/Post button")
            take_debug_screenshot(page, "ERROR_no_publish_button", "page_posting", "ERROR: No Publicar button found")
            
            # Debug: Log all buttons
            try:
                buttons = page.locator('button, div[role="button"]').all()
                logger.error(f"Found {len(buttons)} buttons on page")
                for i, btn in enumerate(buttons[:15]):
                    try:
                        text = btn.inner_text(timeout=1000)
                        logger.error(f"Button {i}: {text[:50]}")
                    except:
                        pass
            except Exception as debug_e:
                logger.error(f"Failed to debug buttons: {debug_e}")
            
            return False
        
        take_debug_screenshot(page, "08_after_first_publicar_click", "page_posting", "After clicking first Publicar")
        
        # Bugfix: Handle intermediate dialogs (event creation, etc.)
        logger.info("Checking for intermediate dialogs...")
        page.wait_for_timeout(3000)
        
        # Check if Facebook is showing an event creation dialog or confirmation
        intermediate_dialog_handled = False
        try:
            # Look for "Realizar publicación original" or similar buttons
            original_post_buttons = [
                'button:has-text("Realizar publicación original")',
                'button:has-text("Make original post")',
                'div[role="button"]:has-text("Realizar publicación original")',
                'div[role="button"]:has-text("Make original post")',
                # Also try getByRole
            ]
            
            for selector in original_post_buttons:
                try:
                    original_btn = page.locator(selector).first
                    if original_btn.is_visible(timeout=2000):
                        logger.info(f"Found intermediate dialog button: {selector}")
                        take_debug_screenshot(page, "09_intermediate_dialog_found", "page_posting", "Intermediate dialog found")
                        original_btn.click(timeout=3000)
                        logger.info("Clicked 'Realizar publicación original' button")
                        page.wait_for_timeout(2000)
                        intermediate_dialog_handled = True
                        break
                except:
                    continue
            
            # Try with getByRole as well
            if not intermediate_dialog_handled:
                try:
                    original_btn = page.get_by_role('button', name='Realizar publicación original')
                    if original_btn.is_visible(timeout=2000):
                        logger.info("Found 'Realizar publicación original' with getByRole")
                        take_debug_screenshot(page, "09_intermediate_dialog_found", "page_posting", "Intermediate dialog found")
                        original_btn.click(timeout=3000)
                        logger.info("Clicked 'Realizar publicación original' button")
                        page.wait_for_timeout(2000)
                        intermediate_dialog_handled = True
                except:
                    pass
        except Exception as e:
            logger.debug(f"No intermediate dialog found: {e}")
        
        if intermediate_dialog_handled:
            logger.info("Intermediate dialog handled - now clicking Publicar again...")
            take_debug_screenshot(page, "10_after_dialog_dismissed", "page_posting", "After dismissing intermediate dialog")
            
            # Click "Publicar" AGAIN after handling the dialog
            second_post_clicked = False
            try:
                post_button = page.get_by_role('button', name='Publicar', exact=True)
                if post_button.is_visible(timeout=5000):
                    logger.info("Found Publicar button again (Spanish)")
                    post_button.click(timeout=3000)
                    page.wait_for_timeout(5000)
                    second_post_clicked = True
            except Exception as e:
                logger.debug(f"Spanish Publicar button not found second time: {e}")
            
            if not second_post_clicked:
                try:
                    post_button = page.get_by_role('button', name='Post', exact=True)
                    if post_button.is_visible(timeout=5000):
                        logger.info("Found Post button again (English)")
                        post_button.click(timeout=3000)
                        page.wait_for_timeout(5000)
                        second_post_clicked = True
                except Exception as e:
                    logger.debug(f"English Post button not found second time: {e}")
            
            if not second_post_clicked:
                logger.error("Could not find Publicar button after handling intermediate dialog")
                take_debug_screenshot(page, "ERROR_no_second_publicar", "page_posting", "ERROR: No Publicar after dialog")
                return False
            
            take_debug_screenshot(page, "11_after_second_publicar_click", "page_posting", "After clicking Publicar second time")
        else:
            logger.info("No intermediate dialog detected - proceeding with verification")
        
        # Bugfix: VERIFY the post actually succeeded
        logger.info("Verifying post was published...")
        page.wait_for_timeout(5000)
        
        # Check for error dialogs or failure indicators
        try:
            # Look for common error messages
            error_indicators = [
                'text=Something went wrong',
                'text=Algo salió mal',
                'text=Try again',
                'text=Intentar de nuevo',
                'text=Error',
            ]
            
            for indicator in error_indicators:
                error = page.locator(indicator).first
                if error.is_visible(timeout=1000):
                    logger.error(f"❌ Post failed - error found: {indicator}")
                    take_debug_screenshot(page, "ERROR_post_failed", "page_posting", "ERROR: Post failed")
                    return False
        except Exception as e:
            logger.debug(f"No error indicators found (this is good): {e}")
        
        # Check if we're back on the page (post composer should be closed)
        try:
            # If the post dialog is still open, posting might have failed
            post_dialog = page.locator('div[role="dialog"]').first
            if post_dialog.is_visible(timeout=2000):
                logger.warning("⚠️  Post dialog still open - post might have failed")
                take_debug_screenshot(page, "WARNING_dialog_still_open", "page_posting", "WARNING: Dialog still open")
                # Don't return False yet, wait a bit more
                page.wait_for_timeout(3000)
        except:
            logger.info("✅ Post dialog closed - good sign")
        
        # Wait a bit more for the post to appear
        page.wait_for_timeout(3000)
        
        take_debug_screenshot(page, "09_final_verification", "page_posting", "Final verification")
        
        # Log success
        logger.info("✅ Image posted successfully!")
        return True
        
    except Exception as e:
        logger.error(f"Failed to post image: {e}")
        take_debug_screenshot(page, "error_posting", "page_posting", f"Error: {e}")
        return False


def main():
    """Main execution function."""
    # Debug is controlled via database settings at http://213.199.33.207:8006/settings
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    debug_session = DebugSession(f"page_poster_{timestamp}", script_type="page_posting")
    
    try:
        logger.info("="*70)
        logger.info("FACEBOOK PAGE POSTER")
        logger.info("="*70)
        
        # Bugfix: Check if this is a manual run
        is_manual_run = os.environ.get('MANUAL_RUN') == '1'
        # Feature: Check if specific image ID is provided for manual posting
        specific_image_id = os.environ.get('IMAGE_ID')
        
        if is_manual_run:
            logger.info("Manual run detected (MANUAL_RUN=1) - bypassing enabled check")
            if specific_image_id:
                logger.info(f"Specific image ID provided: {specific_image_id}")
        else:
            logger.info("Scheduled run detected")
        
        # Get posting settings
        logger.info("Loading posting settings...")
        settings = get_posting_settings()
        
        if not settings:
            logger.error("Failed to load posting settings")
            return 1
        
        # Bugfix: Only check enabled status for scheduled runs
        if not is_manual_run and not settings['enabled']:
            logger.info("Page posting is disabled in settings (scheduled runs only)")
            return 0
        
        page_name = settings['page_name']
        page_url = settings['page_url']
        
        if not page_name:
            logger.error("Page name not configured")
            return 1
        
        if not page_url:
            logger.error("Page URL not configured")
            return 1
        
        logger.info(f"Target page: {page_name}")
        logger.info(f"Page URL: {page_url}")
        
        # Feature: Get specific image if IMAGE_ID provided, otherwise get next from queue
        if specific_image_id:
            logger.info(f"Fetching specific image ID: {specific_image_id}")
            image_data = get_specific_image(int(specific_image_id))
            
            if not image_data:
                logger.error(f"Image ID {specific_image_id} not found or invalid")
                return 1
            
            # Validate image is approved and not already posted
            if not image_data['approved_for_posting']:
                logger.error(f"Image ID {specific_image_id} is not approved for posting")
                return 1
            
            if image_data['posted_to_page']:
                logger.error(f"Image ID {specific_image_id} has already been posted")
                return 1
            
            logger.info(f"✅ Image ID {specific_image_id} is valid for manual posting")
        else:
            # Get next approved image from auto-post queue
            logger.info("Looking for approved images in auto-post queue...")
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
                
                # Bugfix: Check if we're already logged in as the page, or switch if needed
                # The auth state might already have us logged in as Miltoner!
                logger.info("Checking current profile state...")
                
                # Navigate to home first
                logger.info("Navigating to Facebook home...")
                page.goto('https://www.facebook.com/', wait_until='domcontentloaded')
                page.wait_for_timeout(4000)  # Give it time to load
                
                logger.info(f"Current URL: {page.url}")
                take_debug_screenshot(page, "01_home_loaded", "page_posting", "Home page loaded")
                
                # Bugfix: Check if composer shows page name (means we're already logged in as page)
                logger.info("Checking if already logged in as page...")
                composer_text_with_page = f"¿Qué estás pensando, {page_name}?"
                
                try:
                    composer_check = page.get_by_text(composer_text_with_page, exact=False).first
                    if composer_check.is_visible(timeout=3000):
                        logger.info(f"✅ Already logged in as {page_name}! Composer shows: {composer_text_with_page}")
                        logger.info("No need to switch profiles - ready to post!")
                    else:
                        logger.info(f"Not logged in as {page_name}, need to switch profiles...")
                        
                        # Import and try to switch
                        from facebook.facebook_page_manager import switch_to_page_mode
                        
                        if not switch_to_page_mode(page, page_name, None):
                            logger.error("❌ Failed to switch to page profile")
                            logger.error(f"Current URL: {page.url}")
                            take_debug_screenshot(page, "ERROR_cannot_switch_profile", "page_posting", "Failed to switch profile")
                            return 1
                        
                        logger.info("✅ Successfully switched to page profile")
                        page.wait_for_timeout(3000)
                        take_debug_screenshot(page, "02_after_switch", "page_posting", "After profile switch")
                        
                except Exception as e:
                    logger.warning(f"Could not check composer text: {e}")
                    logger.info("Will attempt to post anyway...")
                
                # Post the image (pass page_name for composer detection)
                logger.info("Posting image to page...")
                success = post_image_to_page(page, image_data['image_path'], page_name)
                
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


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
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError, Error as PlaywrightError, Page

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

# Configure logging with Mexico timezone
from utils.logging_config import setup_basicConfig_with_mexico_timezone
from zoneinfo import ZoneInfo

log_file = logs_dir / f'page_poster_{datetime.now(tz=ZoneInfo("America/Mexico_City")).strftime("%Y%m%d")}.log'
setup_basicConfig_with_mexico_timezone(log_file, getattr(logging, config.LOG_LEVEL))

logger = logging.getLogger(__name__)


def is_page_alive(page: Page) -> bool:
    """
    Check if page/context/browser is still alive and operational.
    
    Bugfix: Prevents TargetClosedError when trying to use a closed page.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        True if page is alive, False otherwise
    """
    try:
        # Try to access page properties - will fail if closed
        _ = page.url
        return True
    except Exception as e:
        error_msg = str(e).lower()
        if 'closed' in error_msg or 'target' in error_msg:
            logger.error(f"❌ Bugfix: Page is closed and cannot be used: {str(e)[:100]}")
            return False
        # Other errors might not mean the page is closed
        logger.warning(f"⚠️ Bugfix: Page state check returned error: {str(e)[:100]}")
        return False


def get_posting_settings():
    """Get posting settings from database."""
    try:
        import sqlite3
        
        # Connect to shared database (Laravel web app uses this primary database)
        possible_paths = [
            '/var/www/alexis-scrapper-docker/scrapper-alexis-web/database/database.sqlite',  # Primary: Laravel web app database
            '/var/www/scrapper-alexis/data/scraper.db',  # Legacy path
            '/var/www/alexis-scrapper-docker/scrapper-alexis/data/scraper.db',  # Current Python path
            'data/scraper.db',  # Relative path fallback
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
        
        # Get posting settings
        cursor = conn.execute("SELECT page_name, page_url, interval_min, interval_max, enabled FROM posting_settings LIMIT 1")
        result = cursor.fetchone()
        
        if not result:
            conn.close()
            return None
        
        settings = {
            'page_name': result[0],
            'page_url': result[1],
            'interval_min': result[2],
            'interval_max': result[3],
            'enabled': bool(result[4])
        }
        
        # Get operating hours from scraper_settings
        cursor = conn.execute("SELECT posting_start_hour, posting_start_period, posting_stop_hour, posting_stop_period FROM scraper_settings LIMIT 1")
        hours_result = cursor.fetchone()
        conn.close()
        
        if hours_result:
            settings['posting_start_hour'] = hours_result[0]
            settings['posting_start_period'] = hours_result[1]
            settings['posting_stop_hour'] = hours_result[2]
            settings['posting_stop_period'] = hours_result[3]
        else:
            # Default values if not set
            settings['posting_start_hour'] = 7
            settings['posting_start_period'] = 'AM'
            settings['posting_stop_hour'] = 1
            settings['posting_stop_period'] = 'AM'
        
        return settings
        
    except Exception as e:
        logger.error(f"Failed to get posting settings: {e}")
        return None


def is_within_operating_hours(start_hour: int, start_period: str, stop_hour: int, stop_period: str) -> bool:
    """
    Check if current time is within operating hours.
    System should STOP posting at stop_hour and RESUME at start_hour.
    
    Args:
        start_hour: Hour to resume posting (1-12)
        start_period: AM or PM for start time
        stop_hour: Hour to stop posting (1-12)
        stop_period: AM or PM for stop time
    
    Returns:
        True if posting should occur, False if in quiet period
    """
    from datetime import datetime
    from zoneinfo import ZoneInfo
    
    # Get current time in configured timezone
    now = datetime.now(tz=ZoneInfo("America/Mexico_City"))
    current_hour_24 = now.hour  # 0-23
    
    # Convert 12-hour format to 24-hour format
    def to_24_hour(hour_12: int, period: str) -> int:
        if period == 'AM':
            return 0 if hour_12 == 12 else hour_12
        else:  # PM
            return 12 if hour_12 == 12 else hour_12 + 12
    
    start_hour_24 = to_24_hour(start_hour, start_period)
    stop_hour_24 = to_24_hour(stop_hour, stop_period)
    
    logger.info(f"Operating hours check: Current={current_hour_24:02d}:00, Stop={stop_hour_24:02d}:00 ({stop_hour} {stop_period}), Start={start_hour_24:02d}:00 ({start_hour} {start_period})")
    
    # If stop time is AFTER start time (in 24h), we have an overnight quiet period
    # Example: Stop at 11 PM (23:00), Start at 6 AM (06:00)
    # Quiet period is: 23:00 - 06:00 (overnight)
    if stop_hour_24 > start_hour_24:
        # Overnight quiet period: between stop and start (crossing midnight)
        # Current hour is in quiet period if >= stop OR < start
        is_in_quiet_period = current_hour_24 >= stop_hour_24 or current_hour_24 < start_hour_24
        if is_in_quiet_period:
            logger.info(f"❌ Currently in overnight quiet period (between {stop_hour} {stop_period} and {start_hour} {start_period})")
            return False
        else:
            logger.info(f"✅ Within operating hours (outside overnight quiet period)")
            return True
    else:
        # Normal daytime quiet period (or same hour - always allowed)
        # Example: Stop at 6 AM (06:00), Start at 11 PM (23:00)
        # Quiet period is: 06:00 - 23:00 (daytime)
        if stop_hour_24 == start_hour_24:
            # Same hour means no restrictions
            logger.info(f"✅ No operating hours restrictions (start == stop)")
            return True
        
        is_in_quiet_period = current_hour_24 >= stop_hour_24 and current_hour_24 < start_hour_24
        if is_in_quiet_period:
            logger.info(f"❌ Currently in quiet period (between {stop_hour} {stop_period} and {start_hour} {start_period})")
            return False
        else:
            logger.info(f"✅ Within operating hours")
            return True


def get_next_approved_image():
    """Get the next approved image to post (only auto-post enabled).
    Orders by priority (highest first), then by approved_at (oldest first)."""
    try:
        import sqlite3
        
        # Connect to scraper database
        db = get_database()
        
        # Get next approved image by priority (highest first), then oldest approved
        # Feature: post_priority field - images with priority 1 (from "generar ahora") post first
        query = """
            SELECT m.id, m.message_text, m.image_path
            FROM messages m
            WHERE m.image_generated = 1
              AND m.approved_for_posting = 1
              AND m.auto_post_enabled = 1
              AND (m.posted_to_page = 0 OR m.posted_to_page IS NULL)
            ORDER BY m.post_priority DESC, m.approved_at ASC
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
            # BUGFIX: Store timestamp in UTC format for Laravel compatibility
            # Laravel's accessor will convert from UTC to app timezone (Mexico) for display
            from datetime import timezone as dt_timezone
            timestamp = datetime.now(tz=dt_timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
            conn.execute(
                "UPDATE messages SET posted_to_page = 1, posted_to_page_at = ? WHERE id = ?",
                (timestamp, message_id)
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
        
        # Bugfix: NEW - Check for "Habla con las personas directamente" modal and click "Ahora no"
        # CRITICAL: This modal is a PROMO overlay - clicking "Ahora no" dismisses it and post CONTINUES TO PUBLISH
        # NO second Publicar click is needed after this modal!
        ahora_no_modal_handled = False
        try:
            logger.info("Checking for 'Habla con las personas directamente' modal...")
            dismiss_buttons = [
                'button:has-text("Ahora no")',
                'button:has-text("Not now")',
                'div[role="button"]:has-text("Ahora no")',
                'div[role="button"]:has-text("Not now")',
            ]
            
            for selector in dismiss_buttons:
                try:
                    dismiss_btn = page.locator(selector).first
                    if dismiss_btn.is_visible(timeout=2000):
                        logger.info(f"Bugfix: Found 'Ahora no' button - dismissing promo modal: {selector}")
                        take_debug_screenshot(page, "09_habla_personas_modal_found", "page_posting", "Habla con personas modal found")
                        dismiss_btn.click(timeout=3000)
                        logger.info("Bugfix: Clicked 'Ahora no' - promo dismissed, post continues to publish automatically")
                        page.wait_for_timeout(3000)  # Wait for post to finish publishing
                        ahora_no_modal_handled = True
                        break
                except:
                    continue
            
            # Try with getByRole as well for "Ahora no"
            if not ahora_no_modal_handled:
                try:
                    dismiss_btn = page.get_by_role('button', name='Ahora no')
                    if dismiss_btn.is_visible(timeout=2000):
                        logger.info("Bugfix: Found 'Ahora no' button with getByRole - dismissing promo modal")
                        take_debug_screenshot(page, "09_habla_personas_modal_found", "page_posting", "Habla con personas modal found")
                        dismiss_btn.click(timeout=3000)
                        logger.info("Bugfix: Clicked 'Ahora no' - promo dismissed, post continues to publish automatically")
                        page.wait_for_timeout(3000)  # Wait for post to finish publishing
                        ahora_no_modal_handled = True
                except:
                    pass
        except Exception as e:
            logger.debug(f"Bugfix: No 'Ahora no' modal found: {e}")
        
        # Bugfix: If "Ahora no" was handled, post is ALREADY PUBLISHED - skip to verification
        if ahora_no_modal_handled:
            logger.info("Bugfix: 'Ahora no' modal was dismissed - post should be published, proceeding to verification")
            take_debug_screenshot(page, "10_after_ahora_no_dismissed", "page_posting", "After dismissing Ahora no - post published")
        else:
            # Check if Facebook is showing an event creation dialog or confirmation
            # ONLY these dialogs require a second Publicar click
            original_post_dialog_handled = False
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
                            original_post_dialog_handled = True
                            break
                    except:
                        continue
                
                # Try with getByRole as well
                if not original_post_dialog_handled:
                    try:
                        original_btn = page.get_by_role('button', name='Realizar publicación original')
                        if original_btn.is_visible(timeout=2000):
                            logger.info("Found 'Realizar publicación original' with getByRole")
                            take_debug_screenshot(page, "09_intermediate_dialog_found", "page_posting", "Intermediate dialog found")
                            original_btn.click(timeout=3000)
                            logger.info("Clicked 'Realizar publicación original' button")
                            page.wait_for_timeout(2000)
                            original_post_dialog_handled = True
                    except:
                        pass
            except Exception as e:
                logger.debug(f"No intermediate dialog found: {e}")
            
            # ONLY if "Realizar publicación original" was handled, we need second Publicar click
            if original_post_dialog_handled:
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
        dialog_still_open = False
        try:
            # Bugfix: More robust dialog detection - try multiple selectors
            dialog_selectors = [
                'div[role="dialog"]',
                'div[aria-label="Create post"]',
                'div[aria-label="Crear publicación"]',
                'form[method="POST"]',  # Post creation form
            ]
            
            for selector in dialog_selectors:
                try:
                    dialog = page.locator(selector).first
                    if dialog.is_visible(timeout=1000):
                        logger.warning(f"⚠️  Dialog still open (detected via {selector}) - post might have failed")
                        take_debug_screenshot(page, "WARNING_dialog_still_open", "page_posting", "WARNING: Dialog still open")
                        dialog_still_open = True
                        break
                except:
                    continue
            
            if dialog_still_open:
                # Wait a bit more and re-check
                logger.info("Waiting 5 more seconds and re-checking dialog status...")
                page.wait_for_timeout(5000)
                
                # Re-check if any dialog is still visible
                still_open_after_wait = False
                for selector in dialog_selectors:
                    try:
                        dialog = page.locator(selector).first
                        if dialog.is_visible(timeout=1000):
                            still_open_after_wait = True
                            break
                    except:
                        continue
                
                if still_open_after_wait:
                    logger.error("❌ Post dialog STILL open after extended wait - posting FAILED")
                    take_debug_screenshot(page, "ERROR_dialog_still_open_after_wait", "page_posting", "ERROR: Dialog still open after wait")
                    dialog_still_open = True
                else:
                    logger.info("✅ Post dialog closed after extended wait")
                    dialog_still_open = False
        except Exception as e:
            logger.info(f"✅ Post dialog check passed: {e}")
        
        # If dialog is still open, posting failed - return False
        if dialog_still_open:
            logger.error("❌ Cannot mark as posted - dialog verification failed")
            return False
        
        # Wait a bit more for the post to appear
        page.wait_for_timeout(3000)
        
        take_debug_screenshot(page, "09_final_verification", "page_posting", "Final verification")
        
        # Bugfix: CRITICAL - Actually verify the post appeared by checking for composer
        # If we can still see the post composer on the page, posting likely succeeded
        # If we can still click "What's on your mind", the posting flow is complete
        try:
            logger.info("Verifying post appeared on page...")
            page.wait_for_timeout(2000)
            
            # Check current URL
            current_url = page.url
            if 'facebook.com' not in current_url or 'TeamMiltonero' not in current_url:
                logger.error(f"❌ Unexpected URL after posting: {current_url}")
                return False
            
            # Bugfix: Check if composer is available again (indicates we're back to normal page view)
            # This is a strong indicator that the posting flow completed
            try:
                composer_indicators = [
                    'text=¿Qué estás pensando',
                    'text=What\'s on your mind',
                    'div[role="button"]:has-text("Crear publicación")',
                    'div[role="button"]:has-text("Create post")',
                ]
                
                composer_found = False
                for indicator in composer_indicators:
                    try:
                        elem = page.locator(indicator).first
                        if elem.is_visible(timeout=2000):
                            logger.info(f"✅ Composer visible again ({indicator}) - posting likely succeeded")
                            composer_found = True
                            break
                    except:
                        continue
                
                if not composer_found:
                    logger.error("❌ Composer not found after posting - post might have failed")
                    logger.error("Aborting - will retry in next attempt")
                    return False
                    
            except Exception as e:
                logger.warning(f"Could not verify composer visibility: {e}")
                # Don't fail here, just warn
            
            logger.info("✅ All verification checks passed")
            
        except Exception as e:
            logger.error(f"❌ Post verification failed: {e}")
            return False
        
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
        
        # Feature: Check operating hours (only for scheduled runs, not manual runs)
        if not is_manual_run:
            logger.info("Checking operating hours...")
            within_hours = is_within_operating_hours(
                settings.get('posting_start_hour', 7),
                settings.get('posting_start_period', 'AM'),
                settings.get('posting_stop_hour', 1),
                settings.get('posting_stop_period', 'AM')
            )
            if not within_hours:
                logger.info("⏸️  Posting paused due to operating hours restrictions")
                return 0
        else:
            logger.info("Manual run - bypassing operating hours check")
        
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
                
                # Navigate to home first with retry logic for timeout issues
                logger.info("Navigating to Facebook home...")
                
                # Bugfix: Add retry logic for navigation timeouts
                # Facebook can sometimes take >30s to respond, especially with proxies
                max_nav_retries = 3
                nav_success = False
                
                for nav_attempt in range(1, max_nav_retries + 1):
                    try:
                        logger.info(f"Navigation attempt {nav_attempt}/{max_nav_retries}...")
                        # Bugfix: Increase timeout from 30s (default) to 60s
                        page.goto('https://www.facebook.com/', wait_until='domcontentloaded', timeout=60000)
                        page.wait_for_timeout(4000)  # Give it time to load
                        nav_success = True
                        logger.info(f"✅ Navigation successful on attempt {nav_attempt}")
                        break
                    except PlaywrightTimeoutError as timeout_error:
                        logger.warning(f"❌ Navigation attempt {nav_attempt} timed out: {timeout_error}")
                        if nav_attempt < max_nav_retries:
                            wait_time = nav_attempt * 3  # 3s, 6s, 9s
                            logger.info(f"Retrying in {wait_time} seconds...")
                            page.wait_for_timeout(wait_time * 1000)
                        else:
                            logger.error(f"Navigation failed after {max_nav_retries} attempts")
                            raise
                    except Exception as nav_error:
                        logger.error(f"Navigation attempt {nav_attempt} failed with unexpected error: {nav_error}")
                        if nav_attempt < max_nav_retries:
                            wait_time = nav_attempt * 3
                            logger.info(f"Retrying in {wait_time} seconds...")
                            page.wait_for_timeout(wait_time * 1000)
                        else:
                            raise
                
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
                        
                        # Bugfix: Use ensure_page_mode instead of switch_to_page_mode
                        # ensure_page_mode tries direct navigation first (faster & more reliable)
                        from facebook.facebook_page_manager import ensure_page_mode
                        
                        # Bugfix: Use ensure_page_mode with page_url for direct navigation (Method 1)
                        if not ensure_page_mode(page, page_name, page_url):
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
                # Bugfix: Add retry logic for posting failures
                max_post_attempts = 3
                post_success = False
                
                for post_attempt in range(1, max_post_attempts + 1):
                    logger.info("="*70)
                    logger.info(f"POSTING ATTEMPT {post_attempt}/{max_post_attempts}")
                    logger.info("="*70)
                    
                    success = post_image_to_page(page, image_data['image_path'], page_name)
                    
                    if success:
                        # Mark as posted
                        mark_as_posted(image_data['id'])
                        logger.info("="*70)
                        logger.info("✅ POST SUCCESSFUL")
                        logger.info("="*70)
                        post_success = True
                        break
                    else:
                        logger.error(f"❌ Posting attempt {post_attempt}/{max_post_attempts} failed")
                        
                        if post_attempt < max_post_attempts:
                            wait_time = 5
                            # Bugfix: Check if page is still alive before waiting
                            if is_page_alive(page):
                                logger.info(f"⏳ Waiting {wait_time} seconds before retry...")
                                try:
                                    page.wait_for_timeout(wait_time * 1000)
                                    
                                    # Refresh the page before retrying
                                    logger.info("🔄 Refreshing page before retry...")
                                    page.reload()
                                    page.wait_for_timeout(3000)
                                except PlaywrightError as timeout_error:
                                    logger.error(f"❌ Bugfix: Cannot wait or reload - page closed during timeout: {str(timeout_error)[:100]}")
                                    logger.error(f"❌ Bugfix: Posting failed due to browser/page closure - cannot retry")
                                    return 1
                            else:
                                logger.error(f"❌ Bugfix: Page is closed - cannot wait or retry posting")
                                logger.error(f"❌ Bugfix: Posting failed due to browser/page closure on attempt {post_attempt}")
                                return 1
                        else:
                            logger.error("❌ All posting attempts exhausted")
                
                if post_success:
                    return 0
                else:
                    logger.error("Failed to post image after all retry attempts")
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


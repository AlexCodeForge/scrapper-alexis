"""
Facebook message content extraction.

This module handles navigation to Facebook messages and
text content extraction with validation.
"""

import logging
from playwright.sync_api import Page, Locator, TimeoutError as PlaywrightTimeoutError
from typing import Optional, Tuple
from pathlib import Path

import config
from core.exceptions import NavigationError, ExtractionError
from utils.selector_strategies import MESSAGE_SELECTORS, try_selectors
from core.debug_helper import take_debug_screenshot, log_page_state, log_debug_info
from core.database import get_database
from core.message_deduplicator import get_message_deduplicator, MessageQualityFilter

logger = logging.getLogger(__name__)


def navigate_to_message(page: Page, url: str, max_retries: int = 3) -> bool:
    """
    Navigate to Facebook message URL with retry logic.
    
    Args:
        page: Playwright Page instance
        url: Target Facebook message URL
        max_retries: Maximum number of retry attempts
        
    Returns:
        True if successful
        
    Raises:
        NavigationError: If navigation fails after all retries
    """
    logger.info(f"=== Navigating to Facebook Message ===")
    logger.info(f"Target URL: {url}")
    logger.info(f"Current URL: {page.url}")
    
    for attempt in range(max_retries):
        try:
            logger.info(f"Navigation attempt {attempt + 1}/{max_retries}...")
            logger.debug(f"Navigation timeout: {config.NAVIGATION_TIMEOUT}ms")
            
            page.goto(
                url,
                wait_until='domcontentloaded',
                timeout=config.NAVIGATION_TIMEOUT
            )
            
            logger.info(f"[OK] Navigation successful (attempt {attempt + 1})")
            logger.info(f"Final URL: {page.url}")
            
            # DEBUG: Screenshot after navigation
            take_debug_screenshot(page, "07_after_navigation", "navigation", f"Navigated to target")
            log_page_state(page, "After navigation to target URL", "navigation")
            
            return True
            
        except PlaywrightTimeoutError as e:
            if attempt < max_retries - 1:
                wait_time = 2000 * (attempt + 1)  # Incremental backoff
                logger.warning(
                    f"Navigation timeout on attempt {attempt + 1}, "
                    f"retrying in {wait_time}ms..."
                )
                page.wait_for_timeout(wait_time)
            else:
                logger.error(f"Navigation failed after {max_retries} attempts")
                raise NavigationError(
                    f"Failed to navigate to Facebook message after {max_retries} attempts: {e}"
                )
        
        except Exception as e:
            logger.error(f"Unexpected navigation error on attempt {attempt + 1}: {e}")
            raise NavigationError(f"Navigation error: {e}")
    
    return False


def _smart_scroll_and_extract(page: Page, selector: str, target_messages: int, max_scrolls: int = 20, scroll_strategy: str = "default") -> list:
    """
    Smart scrolling that extracts messages as we scroll (handles Facebook's virtual scrolling).
    
    Facebook removes old DOM elements as new ones load, so we must extract text immediately.
    Includes fail-safe: if page doesn't scroll, force scroll to bottom.
    Uses SLOW scrolling with crash protection for VPS stability.
    
    Args:
        page: Playwright Page instance
        selector: CSS selector to find message elements
        target_messages: Target number of unique messages to extract
        max_scrolls: Maximum number of scroll attempts (default: 20 for safety)
        scroll_strategy: Scroll method to use ("default", "mouse_wheel", "page_down")
        
    Returns:
        List of extracted message texts (deduplicated)
    """
    logger.info(f"=== Smart Scroll & Extract (Strategy: {scroll_strategy}) ===")
    logger.info(f"Max scrolls: {max_scrolls}, Target messages: {target_messages}")
    scroll_count = 0
    no_new_messages_count = 0
    max_no_new_messages = 8  # Stop after 8 scrolls with no new messages (be patient!)
    stuck_scroll_count = 0  # Track consecutive scrolls with no position change (but don't stop on this!)
    
    extracted_messages = set()  # Deduplicate as we go
    previous_message_count = 0
    previous_scroll_position = 0
    
    # Import lxml for HTML parsing (workspace rules recommendation)
    try:
        from lxml import html as lxml_html
    except ImportError:
        logger.error("lxml not installed - falling back to Playwright (may crash)")
        lxml_html = None
    
    while scroll_count < max_scrolls and len(extracted_messages) < target_messages:
        # NEW APPROACH: Extract text directly via JavaScript (avoids HTML transfer overhead)
        try:
            # Check if page is still alive before querying
            if page.is_closed():
                logger.error("Page was closed unexpectedly")
                break
            
            # Extract text directly using JavaScript evaluation (lighter than page.content())
            logger.debug(f"Extracting text via JavaScript...")
            
            messages_on_page = page.evaluate('''
                () => {
                    const elements = document.querySelectorAll('div[dir="auto"]');
                    const texts = [];
                    for (let i = 0; i < Math.min(elements.length, 50); i++) {
                        const element = elements[i];
                        // Get text with proper Unicode handling
                        let text = element.innerText || element.textContent || '';
                        
                        // Ensure proper UTF-8 encoding by normalizing Unicode
                        text = text.normalize('NFC');
                        
                        const cleaned = text.trim();
                        // Filter out short texts and UI elements
                        if (cleaned.length >= 5 && 
                            !cleaned.match(/^(Compartir|Comentar|Me gusta|Reaccionar|Share|Comment|Like)$/)) {
                            texts.push(cleaned);
                        }
                    }
                    return texts;
                }
            ''')
            
            logger.debug(f"Found {len(messages_on_page)} potential messages via JS")
            
            # Add to our set (auto-deduplicates)
            for text in messages_on_page:
                extracted_messages.add(text)
        
        except Exception as e:
            # Handle page crashes gracefully
            error_msg = str(e)
            if "Target crashed" in error_msg or "Page closed" in error_msg:
                logger.error(f"Page crashed during extraction: {e}")
                logger.info(f"Returning {len(extracted_messages)} messages extracted before crash")
                break
            else:
                # Re-raise other unexpected errors
                logger.error(f"Unexpected error getting elements: {e}")
                raise
        
        current_message_count = len(extracted_messages)
        new_messages = current_message_count - previous_message_count
        
        logger.info(f"Scroll {scroll_count + 1}: Extracted {current_message_count}/{target_messages} unique messages (+{new_messages} new)")
        
        # Check if we reached target
        if current_message_count >= target_messages:
            logger.info(f"[SUCCESS] Reached target of {target_messages} messages!")
            break
        
        # Check if we got new messages this scroll
        if new_messages > 0:
            no_new_messages_count = 0  # Reset counter
        else:
            no_new_messages_count += 1
            logger.warning(f"  → No new messages this scroll (attempt {no_new_messages_count}/{max_no_new_messages})")
            
            # Stop if no new messages for several attempts
            if no_new_messages_count >= max_no_new_messages:
                logger.info("No new messages loading, reached end of available content")
                break
        
        previous_message_count = current_message_count
        
        # Get current scroll position BEFORE scrolling (with crash protection)
        try:
            current_scroll_position = page.evaluate("window.pageYOffset")
        except Exception as e:
            logger.warning(f"  ⚠️  Page became unstable while reading scroll position: {e}")
            logger.info(f"✅ Successfully extracted {current_message_count} messages before instability")
            break  # Exit gracefully with what we have
        
        # FAIL-SAFE: Detect if we're stuck (scroll position hasn't changed)
        # BUT DON'T STOP - Facebook's lazy loading is slow, content is still there!
        if current_scroll_position == previous_scroll_position:
            stuck_scroll_count += 1
            logger.warning(f"  ⚠️  Scroll position hasn't changed! (stuck count: {stuck_scroll_count})")
            
            # Take screenshot every 3rd stuck attempt for debugging
            if stuck_scroll_count % 3 == 0:
                logger.info(f"  📸 Taking screenshot for debugging (stuck count: {stuck_scroll_count})...")
                try:
                    take_debug_screenshot(page, f"stuck_scroll_{scroll_count}", "stuck", 
                                        f"Stuck after {scroll_count} scrolls, {current_message_count} messages")
                except Exception as e:
                    logger.warning(f"Could not take stuck screenshot: {e}")
            
            # Wait LONGER for Facebook's lazy loading - messages might still be loading
            logger.info(f"  ⏳ Waiting 18 seconds for messages to load (stuck attempt {stuck_scroll_count})...")
            page.wait_for_timeout(18000)  # Wait even longer - Facebook is slow!
            
            # Try AGGRESSIVE scroll to force lazy loading
            logger.debug(f"Attempting aggressive scroll (3000px) with {scroll_strategy} method...")
            try:
                if scroll_strategy == "mouse_wheel":
                    page.mouse.wheel(0, 3000)
                elif scroll_strategy == "page_down":
                    for _ in range(6):  # 6 Page Downs ≈ 3000px
                        page.keyboard.press("PageDown")
                        page.wait_for_timeout(100)
                else:  # default
                    page.evaluate("window.scrollBy(0, 3000)")
            except Exception as e:
                logger.warning(f"  ⚠️  Page crashed during scroll: {e}")
                logger.info(f"✅ Successfully extracted {current_message_count} messages before crash")
                break
            
            # DON'T BREAK - let "no new messages" counter handle stopping
        else:
            # Normal incremental scroll (with crash protection)
            stuck_scroll_count = 0  # Reset stuck counter - we're making progress!
            
            # Use different scroll methods based on strategy
            try:
                if scroll_strategy == "mouse_wheel":
                    logger.debug(f"Scrolling with mouse wheel (1500px)...")
                    page.mouse.wheel(0, 1500)
                elif scroll_strategy == "page_down":
                    logger.debug(f"Scrolling with Page Down key...")
                    for _ in range(3):  # 3 Page Downs ≈ 1500px
                        page.keyboard.press("PageDown")
                        page.wait_for_timeout(100)
                else:  # default
                    logger.debug(f"Scrolling down (1500px)...")
                    page.evaluate("window.scrollBy(0, 1500)")
            except Exception as e:
                logger.warning(f"  ⚠️  Page crashed during scroll: {e}")
                logger.info(f"✅ Successfully extracted {current_message_count} messages before crash")
                break
        
        # Get new scroll position (with crash protection)
        try:
            previous_scroll_position = page.evaluate("window.pageYOffset")
        except Exception as e:
            logger.warning(f"  ⚠️  Cannot read scroll position after scroll: {e}")
            logger.info(f"✅ Successfully extracted {current_message_count} messages")
            break
        
        scroll_count += 1
        
        # CRITICAL: Wait for Facebook's lazy loading to trigger
        # Messages can take time to load - be patient!
        logger.debug(f"⏳ Waiting 12 seconds for Facebook to lazy-load new content...")
        page.wait_for_timeout(12000)  # Give Facebook time to load messages
        
        # Don't wait for networkidle - Facebook always has background activity
        # This was causing 60+ second hangs!
    
    logger.info(f"=== Scroll & Extract Summary ===")
    logger.info(f"Total scrolls: {scroll_count}")
    logger.info(f"Unique messages extracted: {len(extracted_messages)}")
    
    return list(extracted_messages)


def check_and_close_login_popup(page: Page) -> bool:
    """
    Check if Facebook is showing a login popup and try to close it.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        True if popup was found and closed, False otherwise
    """
    try:
        # Check for login popup/modal with close button
        # Use role-based selector first (most reliable)
        try:
            # Look for any dialog on the page
            popup = page.locator('div[role="dialog"]').first
            if popup.is_visible(timeout=2000):
                logger.warning("Facebook login popup/dialog detected - attempting to close...")
                
                # Try multiple methods to close the popup
                close_methods = [
                    # Method 1: Role-based button with "Close" text (most reliable - matches MCP success)
                    lambda: page.get_by_role("button", name="Close").click(timeout=3000),
                    # Method 2: Role-based button with "Cerrar" text (Spanish)
                    lambda: page.get_by_role("button", name="Cerrar").click(timeout=3000),
                    # Method 3: Button element directly containing text
                    lambda: page.locator('button:has-text("Close")').first.click(timeout=2000),
                    lambda: page.locator('button:has-text("Cerrar")').first.click(timeout=2000),
                    # Method 4: Aria-label based selectors
                    lambda: page.locator('button[aria-label="Close"]').first.click(timeout=2000),
                    lambda: page.locator('button[aria-label="Cerrar"]').first.click(timeout=2000),
                    # Method 5: Div with role button
                    lambda: page.locator('div[role="button"][aria-label="Close"]').first.click(timeout=2000),
                    lambda: page.locator('div[role="button"][aria-label="Cerrar"]').first.click(timeout=2000),
                ]
                
                for i, close_method in enumerate(close_methods, 1):
                    try:
                        logger.debug(f"Attempting close method {i}...")
                        close_method()
                        page.wait_for_timeout(1000)  # Wait for popup to close
                        logger.info(f"[OK] Closed Facebook login popup using method {i}")
                        return True
                    except Exception as method_error:
                        logger.debug(f"Method {i} failed: {str(method_error)[:100]}")
                        continue
                
                # Final fallback: Try pressing ESC key to close popup
                try:
                    logger.debug("Trying ESC key as fallback...")
                    page.keyboard.press("Escape")
                    page.wait_for_timeout(1000)
                    logger.info("[OK] Closed popup with ESC key")
                    return True
                except Exception as esc_error:
                    logger.debug(f"ESC key fallback failed: {str(esc_error)[:100]}")
                
                logger.warning("Could not find close button for login popup after trying all methods")
                logger.debug("The popup may still be blocking content extraction")
                return False
        except:
            pass
        
        # Check URL for login redirect (different from popup)
        current_url = page.url
        if 'login' in current_url.lower() or 'checkpoint' in current_url.lower():
            logger.warning(f"[WARN] Redirected to login page: {current_url}")
            return False  # This requires re-login, not just closing popup
        
        return False
        
    except Exception as e:
        logger.debug(f"Error checking/closing login popup: {e}")
        return False


def extract_message_text_with_database(page: Page, profile_id: int, max_messages: int = 10, 
                                     max_retries: int = 3) -> Tuple[list, dict]:
    """
    Extract multiple message texts with database integration and duplicate checking.
    
    This version integrates with the database to:
    1. Check for duplicates and stop when found
    2. Store new messages directly to database
    3. Track scraping session
    
    Args:
        page: Playwright Page instance
        profile_id: Database ID of the profile being scraped
        max_messages: Maximum number of unique messages to extract
        max_retries: Maximum number of extraction attempts if stuck early
        
    Returns:
        Tuple of (messages_list, extraction_stats)
        
    Raises:
        ExtractionError: If extraction fails or no valid content found after all retries
    """
    db = get_database()
    deduplicator = get_message_deduplicator()
    
    # Start a scraping session
    session_id = db.start_scraping_session(profile_id)
    
    extraction_stats = {
        'session_id': session_id,
        'total_scraped': 0,
        'new_messages': 0,
        'duplicates_found': 0,
        'first_duplicate_index': None,
        'stopped_due_to_duplicate': False,
        'quality_filtered': 0
    }
    
    min_acceptable_messages = min(50, max_messages // 2)  # At least 50 or half target
    
    for attempt in range(max_retries):
        try:
            logger.info(f"=== Extracting Message Content with Database Integration (Attempt {attempt + 1}/{max_retries}) ===")
            logger.info(f"Profile ID: {profile_id}, Session ID: {session_id}")
            logger.info(f"Target: Extract up to {max_messages} unique messages")
            logger.info(f"Current URL: {page.url}")
            
            # Wait for initial page load
            logger.info("Waiting for initial page load (2s)...")
            page.wait_for_timeout(2000)
            
            # Try to close any login popup if present
            try:
                logger.info("Checking for login popups...")
                popup_closed = check_and_close_login_popup(page)
                if popup_closed:
                    logger.info("Popup was closed, waiting for page to stabilize (1s)...")
                page.wait_for_timeout(1000)
            except Exception as e:
                logger.debug(f"Popup check failed (not critical): {e}")
            
            # Use the best performing selector
            selector = MESSAGE_SELECTORS[0]  # 'div[dir="auto"]'
            logger.info(f"Using validated selector: {selector}")
            
            # Smart scrolling with database integration
            logger.info(f"Starting smart scroll & extract with duplicate checking...")
            
            # Try different scroll strategies on retries
            scroll_strategy = "default" if attempt == 0 else ("mouse_wheel" if attempt == 1 else "page_down")
            messages, scroll_stats = _smart_scroll_and_extract_with_db(
                page, selector, profile_id, max_messages, scroll_strategy=scroll_strategy
            )
            
            # Update extraction stats
            extraction_stats.update(scroll_stats)
            
            # Quality filter the messages
            quality_messages = MessageQualityFilter.filter_quality_messages(messages)
            extraction_stats['quality_filtered'] = len(messages) - len(quality_messages)
            
            logger.info(f"[OK] Extraction completed - {len(quality_messages)} quality messages extracted")
            
            # Check if we got enough messages or should retry
            if len(quality_messages) == 0:
                raise ExtractionError(f"No quality messages extracted on attempt {attempt + 1}")
            
            if len(quality_messages) < min_acceptable_messages and attempt < max_retries - 1:
                logger.warning(f"⚠️ Only extracted {len(quality_messages)} messages (expected at least {min_acceptable_messages})")
                logger.warning(f"🔄 Retrying extraction with different scroll strategy...")
                logger.info(f"⏳ Waiting 20 seconds to let more messages load...")
                page.wait_for_timeout(20000)
                continue
            
            # Success!
            logger.info(f"✅ Successfully extracted {len(quality_messages)} messages")
            break
            
        except ExtractionError as e:
            if attempt < max_retries - 1:
                logger.warning(f"Extraction attempt {attempt + 1} failed: {e}")
                logger.info(f"⏳ Waiting 20 seconds before retry...")
                page.wait_for_timeout(20000)
                continue
            else:
                logger.error(f"All {max_retries} extraction attempts failed")
                # Complete session with error
                db.complete_scraping_session(
                    session_id, 
                    extraction_stats['total_scraped'], 
                    extraction_stats['new_messages'], 
                    "extraction_failed"
                )
                raise
        except Exception as e:
            if attempt < max_retries - 1:
                logger.error(f"Unexpected error on attempt {attempt + 1}: {e}")
                logger.info(f"⏳ Waiting 20 seconds before retry...")
                page.wait_for_timeout(20000)
                continue
            else:
                logger.error(f"Unexpected extraction error after {max_retries} attempts: {e}")
                # Complete session with error
                db.complete_scraping_session(
                    session_id, 
                    extraction_stats['total_scraped'], 
                    extraction_stats['new_messages'], 
                    "unexpected_error"
                )
                raise ExtractionError(f"Failed to extract messages: {e}")
    
    # Complete the scraping session
    stop_reason = "duplicate_found" if extraction_stats['stopped_due_to_duplicate'] else "completed"
    db.complete_scraping_session(
        session_id, 
        extraction_stats['total_scraped'], 
        extraction_stats['new_messages'], 
        stop_reason
    )
    
    # Update profile's last scraped time
    db.update_profile_scraped_time(profile_id)
    
    logger.info("\n=== Extraction Summary ===")
    logger.info(f"Session ID: {session_id}")
    logger.info(f"Total messages scraped: {extraction_stats['total_scraped']}")
    logger.info(f"New messages stored: {extraction_stats['new_messages']}")
    logger.info(f"Duplicates encountered: {extraction_stats['duplicates_found']}")
    logger.info(f"Quality filtered out: {extraction_stats['quality_filtered']}")
    if extraction_stats['stopped_due_to_duplicate']:
        logger.info(f"Stopped at duplicate (index {extraction_stats['first_duplicate_index']})")
    
    # Export messages to JSON file in extraction folder for backup
    try:
        _export_messages_to_json(quality_messages, profile_id, session_id, extraction_stats)
    except Exception as e:
        logger.warning(f"Could not export messages to JSON: {e}")
    
    return quality_messages, extraction_stats


def _smart_scroll_and_extract_with_db(page: Page, selector: str, profile_id: int, 
                                     target_messages: int, max_scrolls: int = 20, 
                                     scroll_strategy: str = "default") -> Tuple[list, dict]:
    """
    Smart scrolling that extracts messages and checks for duplicates in real-time.
    
    This version integrates with the database to stop when duplicates are found.
    
    Args:
        page: Playwright Page instance
        selector: CSS selector to find message elements
        profile_id: Database profile ID
        target_messages: Target number of unique messages to extract
        max_scrolls: Maximum number of scroll attempts
        scroll_strategy: Scroll method to use ("default", "mouse_wheel", "page_down")
        
    Returns:
        Tuple of (messages_list, extraction_stats)
    """
    db = get_database()
    deduplicator = get_message_deduplicator()
    
    logger.info(f"=== Smart Scroll & Extract with Database (Strategy: {scroll_strategy}) ===")
    logger.info(f"Max scrolls: {max_scrolls}, Target messages: {target_messages}")
    
    scroll_count = 0
    no_new_messages_count = 0
    max_no_new_messages = 8
    stuck_scroll_count = 0
    consecutive_duplicate_only_scrolls = 0  # NEW: Track consecutive scrolls with ONLY duplicates
    
    extracted_messages = []  # Messages we've extracted this session
    previous_message_count = 0
    previous_scroll_position = 0
    
    stats = {
        'total_scraped': 0,
        'new_messages': 0,
        'duplicates_found': 0,
        'first_duplicate_index': None,
        'stopped_due_to_duplicate': False
    }
    
    while scroll_count < max_scrolls and len(extracted_messages) < target_messages:
        try:
            # Check if page is still alive
            if page.is_closed():
                logger.error("Page was closed unexpectedly")
                break
            
            # Extract text directly using JavaScript evaluation
            logger.debug(f"Extracting text via JavaScript...")
            
            messages_on_page = page.evaluate('''
                () => {
                    const elements = document.querySelectorAll('div[dir="auto"]');
                    const texts = [];
                    for (let i = 0; i < Math.min(elements.length, 50); i++) {
                        const element = elements[i];
                        // Get text with proper Unicode handling
                        let text = element.innerText || element.textContent || '';
                        
                        // Ensure proper UTF-8 encoding by normalizing Unicode
                        text = text.normalize('NFC');
                        
                        const cleaned = text.trim();
                        // Filter out short texts and UI elements
                        if (cleaned.length >= 5 && 
                            !cleaned.match(/^(Compartir|Comentar|Me gusta|Reaccionar|Share|Comment|Like)$/)) {
                            texts.push(cleaned);
                        }
                    }
                    return texts;
                }
            ''')
            
            logger.debug(f"Found {len(messages_on_page)} potential messages via JS")
            stats['total_scraped'] += len(messages_on_page)
            
            # Check each message for duplicates and add new ones to database
            new_messages_this_scroll = 0
            duplicates_this_scroll = 0
            
            for i, message_text in enumerate(messages_on_page):
                # Check if this message is a duplicate
                if deduplicator.is_duplicate(message_text, profile_id):
                    stats['duplicates_found'] += 1
                    duplicates_this_scroll += 1
                    
                    # Mark first duplicate if not already marked
                    if stats['first_duplicate_index'] is None:
                        stats['first_duplicate_index'] = len(extracted_messages) + i
                        logger.info(f"🔍 First duplicate encountered at index {stats['first_duplicate_index']}")
                        logger.info(f"Duplicate message: {message_text[:100]}...")
                else:
                    # This is a new message - add to database and our list
                    message_id = db.add_message(profile_id, message_text)
                    if message_id:
                        extracted_messages.append(message_text)
                        stats['new_messages'] += 1
                        new_messages_this_scroll += 1
                        logger.debug(f"Added new message {message_id}: {message_text[:50]}...")
            
            # Check if we should stop due to too many duplicates in a row
            if duplicates_this_scroll > 0 and new_messages_this_scroll == 0:
                # Only duplicates found this scroll
                consecutive_duplicate_only_scrolls += 1
                
                # SMART BAILOUT: If we see 2+ consecutive scrolls with ONLY duplicates, bail out
                # This means all visible content is already in DB - no point waiting 18s each scroll!
                if consecutive_duplicate_only_scrolls >= 2:
                    logger.info(f"🛑 SMART BAILOUT: {consecutive_duplicate_only_scrolls} consecutive scrolls with only duplicates")
                    logger.info(f"   All visible content already in database - no new content available")
                    stats['stopped_due_to_duplicate'] = True
                    break
                
                if len(extracted_messages) >= 5:  # Only stop if we have some messages already
                    logger.info(f"🛑 Only duplicates found this scroll - likely reached existing content")
                    stats['stopped_due_to_duplicate'] = True
                    break
                else:
                    logger.info(f"⚠️ Only duplicates this scroll, but continuing to find more content...")
            elif new_messages_this_scroll > 0:
                # Reset counter when we find new messages
                consecutive_duplicate_only_scrolls = 0
            
            if duplicates_this_scroll > 0 and new_messages_this_scroll > 0:
                logger.info(f"📊 Mixed results: {new_messages_this_scroll} new, {duplicates_this_scroll} duplicates - continuing to scroll")
            
            current_message_count = len(extracted_messages)
            
            logger.info(f"Scroll {scroll_count + 1}: Extracted {current_message_count}/{target_messages} "
                       f"unique messages (+{new_messages_this_scroll} new)")
            
            # Check if we reached target
            if current_message_count >= target_messages:
                logger.info(f"[SUCCESS] Reached target of {target_messages} messages!")
                break
            
            # Check if we got new messages this scroll
            if new_messages_this_scroll > 0:
                no_new_messages_count = 0  # Reset counter
            else:
                no_new_messages_count += 1
                logger.warning(f"  → No new messages this scroll (attempt {no_new_messages_count}/{max_no_new_messages})")
                
                # Stop if no new messages for several attempts
                if no_new_messages_count >= max_no_new_messages:
                    logger.info("No new messages loading, reached end of available content")
                    break
            
            previous_message_count = current_message_count
            
            # Get current scroll position BEFORE scrolling (with crash protection)
            try:
                current_scroll_position = page.evaluate("window.pageYOffset")
            except Exception as e:
                logger.warning(f"  ⚠️  Page became unstable while reading scroll position: {e}")
                logger.info(f"✅ Successfully extracted {current_message_count} messages before instability")
                break
            
            # FAIL-SAFE: Detect if we're stuck (scroll position hasn't changed)
            if current_scroll_position == previous_scroll_position:
                stuck_scroll_count += 1
                logger.warning(f"  ⚠️  Scroll position hasn't changed! (stuck count: {stuck_scroll_count})")
                
                # Take screenshot every 3rd stuck attempt for debugging
                if stuck_scroll_count % 3 == 0:
                    logger.info(f"  📸 Taking screenshot for debugging (stuck count: {stuck_scroll_count})...")
                    try:
                        take_debug_screenshot(page, f"stuck_scroll_{scroll_count}", "stuck", 
                                            f"Stuck after {scroll_count} scrolls, {current_message_count} messages")
                    except Exception as e:
                        logger.warning(f"Could not take stuck screenshot: {e}")
                
                # Wait for Facebook's lazy loading
                logger.info(f"  ⏳ Waiting 18 seconds for messages to load (stuck attempt {stuck_scroll_count})...")
                page.wait_for_timeout(18000)
                
                # Try AGGRESSIVE scroll to force lazy loading
                logger.debug(f"Attempting aggressive scroll (3000px) with {scroll_strategy} method...")
                try:
                    if scroll_strategy == "mouse_wheel":
                        page.mouse.wheel(0, 3000)
                    elif scroll_strategy == "page_down":
                        for _ in range(6):  # 6 Page Downs ≈ 3000px
                            page.keyboard.press("PageDown")
                            page.wait_for_timeout(100)
                    else:  # default
                        page.evaluate("window.scrollBy(0, 3000)")
                except Exception as e:
                    logger.warning(f"  ⚠️  Page crashed during scroll: {e}")
                    logger.info(f"✅ Successfully extracted {current_message_count} messages before crash")
                    break
            else:
                # Normal incremental scroll (with crash protection)
                stuck_scroll_count = 0  # Reset stuck counter
                
                try:
                    if scroll_strategy == "mouse_wheel":
                        logger.debug(f"Scrolling with mouse wheel (1500px)...")
                        page.mouse.wheel(0, 1500)
                    elif scroll_strategy == "page_down":
                        logger.debug(f"Scrolling with Page Down key...")
                        for _ in range(3):  # 3 Page Downs ≈ 1500px
                            page.keyboard.press("PageDown")
                            page.wait_for_timeout(100)
                    else:  # default
                        logger.debug(f"Scrolling down (1500px)...")
                        page.evaluate("window.scrollBy(0, 1500)")
                except Exception as e:
                    logger.warning(f"  ⚠️  Page crashed during scroll: {e}")
                    logger.info(f"✅ Successfully extracted {current_message_count} messages before crash")
                    break
            
            # Get new scroll position (with crash protection)
            try:
                previous_scroll_position = page.evaluate("window.pageYOffset")
            except Exception as e:
                logger.warning(f"  ⚠️  Cannot read scroll position after scroll: {e}")
                logger.info(f"✅ Successfully extracted {current_message_count} messages")
                break
            
            scroll_count += 1
            
            # Wait for Facebook's lazy loading
            logger.debug(f"⏳ Waiting 12 seconds for Facebook to lazy-load new content...")
            page.wait_for_timeout(12000)
        
        except Exception as e:
            # Handle page crashes gracefully
            error_msg = str(e)
            if "Target crashed" in error_msg or "Page closed" in error_msg:
                logger.error(f"Page crashed during extraction: {e}")
                logger.info(f"Returning {len(extracted_messages)} messages extracted before crash")
                break
            else:
                logger.error(f"Unexpected error during scroll: {e}")
                break
    
    logger.info(f"=== Scroll & Extract Summary ===")
    logger.info(f"Total scrolls: {scroll_count}")
    logger.info(f"Unique messages extracted: {len(extracted_messages)}")
    logger.info(f"New messages stored to DB: {stats['new_messages']}")
    logger.info(f"Duplicates found: {stats['duplicates_found']}")
    
    return extracted_messages, stats


# Keep the original function for backward compatibility
def extract_message_text(page: Page, max_messages: int = 10, max_retries: int = 3) -> list:
    """
    Extract multiple message texts with deduplication and retry logic.
    
    Based on MCP validation Oct 9, 2025: div[dir="auto"] selector
    successfully extracts 10+ messages from Facebook profiles/posts.
    
    Implements retry mechanism for Facebook's inconsistent lazy-loading behavior.
    
    Args:
        page: Playwright Page instance
        max_messages: Maximum number of unique messages to extract
        max_retries: Maximum number of extraction attempts if stuck early
        
    Returns:
        List of extracted and cleaned message texts (deduplicated)
        
    Raises:
        ExtractionError: If extraction fails or no valid content found after all retries
    """
    min_acceptable_messages = min(50, max_messages // 2)  # At least 50 or half target
    
    for attempt in range(max_retries):
        try:
            logger.info(f"=== Extracting Message Content (Attempt {attempt + 1}/{max_retries}) ===")
            logger.info(f"Target: Extract up to {max_messages} unique messages")
            logger.info(f"Current URL: {page.url}")
            
            # Wait for initial page load - reduced time to minimize crash risk
            logger.info("Waiting for initial page load (2s)...")
            page.wait_for_timeout(2000)
            
            # SKIP SCREENSHOTS DURING EXTRACTION - they cause page crashes on heavy Facebook pages
            logger.info("Skipping debug screenshots during extraction to prevent memory crashes")
            
            # Try to close any login popup if present (some pages show these even when logged in)
            try:
                logger.info("Checking for login popups...")
                popup_closed = check_and_close_login_popup(page)
                if popup_closed:
                    logger.info("Popup was closed, waiting for page to stabilize (1s)...")
                page.wait_for_timeout(1000)
            except Exception as e:
                logger.debug(f"Popup check failed (not critical): {e}")
            
            # Use the best performing selector (MCP validated)
            selector = MESSAGE_SELECTORS[0]  # 'div[dir="auto"]'
            logger.info(f"Using validated selector: {selector}")
            
            # Smart scrolling with real-time extraction (handles Facebook's virtual DOM)
            logger.info(f"Starting smart scroll & extract for {max_messages} messages...")
            log_debug_info(f"Using selector: {selector}")
            log_debug_info(f"Target messages: {max_messages}")
            
            # Try different scroll strategies on retries
            scroll_strategy = "default" if attempt == 0 else ("mouse_wheel" if attempt == 1 else "page_down")
            messages = _smart_scroll_and_extract(page, selector, max_messages, scroll_strategy=scroll_strategy)
            
            # Log completion without screenshot (to avoid memory crashes)
            logger.info(f"[OK] Extraction completed - {len(messages)} messages extracted")
            log_debug_info(f"Extraction complete: {len(messages)} messages extracted", category="extraction")
            
            # Check if we got enough messages or should retry
            if len(messages) == 0:
                raise ExtractionError(f"No messages extracted on attempt {attempt + 1}")
            
            if len(messages) < min_acceptable_messages and attempt < max_retries - 1:
                logger.warning(f"⚠️ Only extracted {len(messages)} messages (expected at least {min_acceptable_messages})")
                logger.warning(f"🔄 Retrying extraction with different scroll strategy...")
                logger.info(f"⏳ Waiting 20 seconds to let more messages load...")
                page.wait_for_timeout(20000)  # Wait for messages to load, DON'T reload page
                continue  # Try again with next strategy (keeping existing messages in DOM)
            
            # Success! We got enough messages
            logger.info(f"✅ Successfully extracted {len(messages)} messages (target: {max_messages})")
            break  # Exit retry loop
            
        except ExtractionError as e:
            if attempt < max_retries - 1:
                logger.warning(f"Extraction attempt {attempt + 1} failed: {e}")
                logger.info(f"⏳ Waiting 20 seconds before retry...")
                page.wait_for_timeout(20000)
                continue
            else:
                logger.error(f"All {max_retries} extraction attempts failed")
                raise
        except Exception as e:
            if attempt < max_retries - 1:
                logger.error(f"Unexpected error on attempt {attempt + 1}: {e}")
                logger.info(f"⏳ Waiting 20 seconds before retry...")
                page.wait_for_timeout(20000)
                continue
            else:
                logger.error(f"Unexpected extraction error after {max_retries} attempts: {e}")
                raise ExtractionError(f"Failed to extract messages: {e}")
    
    # Log results with truncation for readability
    logger.info("\n=== Extracted Messages ===")
    for i, msg in enumerate(messages[:20], 1):  # Show first 20
        preview = msg[:80] + '...' if len(msg) > 80 else msg
        logger.info(f"  [OK] [{i}] {preview}")
    
    if len(messages) > 20:
        logger.info(f"  ... and {len(messages) - 20} more messages")
    
    # Save extracted messages to file
    try:
        from debug_helper import get_current_session_dir
        import json
        from datetime import datetime
        
        session_dir = get_current_session_dir()
        if session_dir:
            extraction_dir = session_dir / 'extraction'
            extraction_dir.mkdir(exist_ok=True)
            
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            
            # Save as JSON
            extraction_file = extraction_dir / f"{timestamp}_extracted_{len(messages)}_messages.json"
            with open(extraction_file, 'w', encoding='utf-8') as f:
                json.dump({
                    "extraction_time": datetime.now().isoformat(),
                    "total_messages": len(messages),
                    "url": page.url,
                    "messages": messages
                }, f, ensure_ascii=False, indent=2)
            
            # Save as readable text file
            text_file = extraction_dir / f"{timestamp}_extracted_{len(messages)}_messages.txt"
            with open(text_file, 'w', encoding='utf-8') as f:
                f.write(f"Facebook Message Extraction\n")
                f.write(f"{'='*70}\n")
                f.write(f"Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
                f.write(f"URL: {page.url}\n")
                f.write(f"Total Messages: {len(messages)}\n")
                f.write(f"{'='*70}\n\n")
                
                for i, msg in enumerate(messages, 1):
                    f.write(f"[{i}] {msg}\n\n")
            
            logger.info(f"💾 Saved extraction results to:")
            logger.info(f"   - JSON: {extraction_file.name}")
            logger.info(f"   - TXT: {text_file.name}")
    except Exception as e:
        logger.warning(f"Could not save extraction files: {e}")
    
    logger.info(f"\n[OK] Successfully extracted {len(messages)} unique messages")
    return messages


def get_message_locator(page: Page) -> Locator:
    """
    Get message content locator for screenshot capture.
    
    This function is used by the screenshot module to capture
    the exact element that was extracted.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        Locator for message content
        
    Raises:
        ExtractionError: If locator cannot be found
    """
    logger.debug("Getting message locator for screenshot...")
    
    message_locator = try_selectors(page, MESSAGE_SELECTORS, timeout=10000)
    
    if not message_locator:
        raise ExtractionError("Could not locate message for screenshot")
    
    return message_locator


def _export_messages_to_json(messages: list, profile_id: int, session_id: int, extraction_stats: dict):
    """
    Export extracted messages to JSON file in extraction folder for backup.
    
    Args:
        messages: List of extracted messages
        profile_id: Database profile ID
        session_id: Scraping session ID
        extraction_stats: Extraction statistics
    """
    import json
    import os
    from datetime import datetime
    from core.debug_helper import get_current_session_dir
    
    try:
        # Get current debug session directory
        session_dir = get_current_session_dir()
        if session_dir:
            extraction_dir = session_dir / "extraction"
            extraction_dir.mkdir(exist_ok=True)
        else:
            # Fallback to creating extraction folder in debug_output
            extraction_dir = Path("debug_output/extraction")
            extraction_dir.mkdir(parents=True, exist_ok=True)
        
        # Create filename with timestamp and profile info
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"profile_{profile_id}_session_{session_id}_{timestamp}_messages.json"
        filepath = extraction_dir / filename
        
        # Prepare export data
        export_data = {
            "extraction_info": {
                "profile_id": profile_id,
                "session_id": session_id,
                "timestamp": timestamp,
                "extraction_date": datetime.now().isoformat(),
                "total_messages": len(messages),
                "stats": extraction_stats
            },
            "messages": [
                {
                    "index": i + 1,
                    "text": msg,
                    "length": len(msg),
                    "extracted_at": datetime.now().isoformat()
                } for i, msg in enumerate(messages)
            ]
        }
        
        # Save to JSON file
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(export_data, f, indent=2, ensure_ascii=False)
        
        logger.info(f"📄 Exported {len(messages)} messages to: {filename}")
        
    except Exception as e:
        logger.error(f"Failed to export messages to JSON: {e}")
        raise

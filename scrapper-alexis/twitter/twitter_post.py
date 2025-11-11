#!/usr/bin/env python3
"""
Twitter Post Script with Database Integration
Posts messages from database and updates their posted status.
Works with debug_helper.py for comprehensive logging.

=============================================================================
⚠️  DEPRECATED - NO LONGER USED ⚠️
=============================================================================
This script is no longer used. The app now focuses on IMAGE GENERATION ONLY.
Twitter posting functionality has been removed to simplify the application.

The image generator (generate_message_images.py) now uses user-provided
profile information (display name, username, avatar) configured in the
web interface settings.

This file is kept for reference only.
=============================================================================
"""

import sys
from pathlib import Path

# Add parent directory to path for imports (needed when running as standalone script)
if __name__ == "__main__":
    sys.path.insert(0, str(Path(__file__).parent.parent))

import json
import time
import sqlite3
from datetime import datetime
from typing import Dict, Any, Optional, List
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError

from core.debug_helper import take_debug_screenshot, log_debug_info, log_error, log_success, is_debug_enabled
from core.database import get_database
from core.message_deduplicator import MessageQualityFilter

# Character limit for Twitter/X
X_CHAR_LIMIT = 280

# JSON storage file
POSTS_JSON_FILE = Path('twitter_posts.json')

# Import proxy config from main config
import sys
sys.path.insert(0, str(Path(__file__).parent.parent))
import config

# Proxy configuration for Twitter access (CRITICAL!)
PROXY_CONFIG = config.PROXY_CONFIG
if not PROXY_CONFIG:
    print("⚠️  ERROR: No proxy configured! Twitter REQUIRES proxy!")
    print("   Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to copy.env")
    sys.exit(1)

def truncate_for_x(text: str, limit: int = X_CHAR_LIMIT) -> str:
    """Truncate text to fit X character limit with ellipsis."""
    if len(text) <= limit:
        return text
    
    truncated = text[:limit - 3].rstrip()
    result = f"{truncated}..."
    log_debug_info(f"Truncated text from {len(text)} to {len(result)} characters", category="posting")
    return result

def get_user_avatar_url(page: Page) -> Optional[str]:
    """Extract the current user's avatar URL."""
    try:
        # Multiple approaches to find the avatar
        avatar_selectors = [
            # Account switcher button area
            '[data-testid="SideNav_AccountSwitcher_Button"] img',
            # Profile image in top navigation
            'img[alt*="profile"]',
            'img[src*="profile_images"]',
            # Any image in account menu area
            'button[aria-label*="Account menu"] img',
            # Profile image in tweet compose area
            '[data-testid="tweetTextarea_0"] ~ * img',
            # Generic profile image selectors
            'img[alt*="Profile"]', 
            'img[alt*="Avatar"]'
        ]
        
        for selector in avatar_selectors:
            try:
                avatar_elements = page.locator(selector).all()
                for avatar_elem in avatar_elements:
                    if avatar_elem.is_visible(timeout=1000):
                        src = avatar_elem.get_attribute('src')
                        if src and ('profile_images' in src or 'pbs.twimg.com' in src):
                            log_debug_info(f"Found avatar URL with selector {selector}: {src}", category="extraction")
                            return src
            except:
                continue
        
        # Alternative approach: look for any Twitter profile image URLs in the page
        try:
            page.wait_for_timeout(1000)
            all_images = page.locator('img').all()
            for img in all_images:
                try:
                    src = img.get_attribute('src')
                    if src and 'profile_images' in src and img.is_visible():
                        log_debug_info(f"Found avatar URL from all images scan: {src}", category="extraction")
                        return src
                except:
                    continue
        except:
            pass
                
        log_debug_info("Could not find user avatar URL", level="WARNING", category="extraction")
        return None
        
    except Exception as e:
        log_debug_info(f"Error extracting avatar URL: {e}", level="ERROR", category="extraction")
        return None

def extract_post_url(page: Page, posted_text: str) -> Optional[str]:
    """Extract the URL of the posted tweet."""
    try:
        # Wait a bit for the post to appear in timeline
        page.wait_for_timeout(3000)
        
        # Look for the posted tweet in the timeline
        # Try to find a link that contains our posted text
        tweet_selectors = [
            f'article:has-text("{posted_text[:20]}") a[href*="/status/"]',
            f'[data-testid="tweet"]:has-text("{posted_text[:20]}") a[href*="/status/"]',
            'a[href*="/status/"]:near(article)'
        ]
        
        for selector in tweet_selectors:
            try:
                tweet_link = page.locator(selector).first
                if tweet_link.is_visible(timeout=3000):
                    href = tweet_link.get_attribute('href')
                    if href and '/status/' in href:
                        # Convert relative URL to absolute
                        if href.startswith('/'):
                            full_url = f"https://x.com{href}"
                        else:
                            full_url = href
                        log_debug_info(f"Found post URL: {full_url}", category="extraction")
                        return full_url
            except:
                continue
        
        # Alternative: look for success alert that might contain a link
        try:
            success_alert = page.locator('text=Tu post se envió').first
            if success_alert.is_visible():
                # Look for link near the success message
                parent = success_alert.locator('xpath=..').first
                link = parent.locator('a[href*="/status/"]').first
                if link.is_visible():
                    href = link.get_attribute('href')
                    if href:
                        full_url = f"https://x.com{href}" if href.startswith('/') else href
                        log_debug_info(f"Found post URL from success alert: {full_url}", category="extraction")
                        return full_url
        except:
            pass
            
        log_debug_info("Could not extract post URL", level="WARNING", category="extraction")
        return None
        
    except Exception as e:
        log_error(page, f"Error extracting post URL: {e}")
        return None

def save_post_data(post_data: Dict[str, Any]):
    """Save post data to JSON file with proper UTF-8 encoding."""
    try:
        # Ensure all text fields are properly encoded
        if 'posted_text' in post_data and post_data['posted_text']:
            # Fix any encoding issues in the posted text
            text = post_data['posted_text']
            if isinstance(text, str):
                # Ensure proper UTF-8 encoding
                post_data['posted_text'] = text.encode('utf-8').decode('utf-8')
        
        # Load existing data or create new
        if POSTS_JSON_FILE.exists():
            with open(POSTS_JSON_FILE, 'r', encoding='utf-8') as f:
                all_posts = json.load(f)
        else:
            all_posts = []
        
        # Add new post data
        all_posts.append(post_data)
        
        # Save back to file with proper UTF-8 encoding
        with open(POSTS_JSON_FILE, 'w', encoding='utf-8') as f:
            json.dump(all_posts, f, indent=2, ensure_ascii=False)
            
        log_success(f"Post data saved to {POSTS_JSON_FILE}", category="storage")
        
    except Exception as e:
        log_debug_info(f"Error saving post data: {e}", level="ERROR", category="storage")

def post_tweet(page: Page, message: str) -> Dict[str, Any]:
    """
    Post a tweet and extract post URL and avatar URL.
    
    Args:
        page: Playwright page object (should be on https://x.com/home)
        message: Message text to post
        
    Returns:
        Dictionary with posting results, post URL, and avatar URL
    """
    start_time = time.time()
    result = {
        'success': False,
        'posted_text': None,
        'char_count': 0,
        'was_truncated': False,
        'error': None,
        'elapsed_time': 0,
        'duplicate_detected': False,
        'post_url': None,
        'avatar_url': None,
        'timestamp': time.time(),
        'date_posted': time.strftime('%Y-%m-%d %H:%M:%S')
    }
    
    try:
        # Ensure we're on the home page and DOM is fully loaded
        if 'x.com/home' not in page.url:
            log_debug_info("Navigating to Twitter home page...", category="navigation")
            page.goto('https://x.com/home', wait_until='domcontentloaded', timeout=60000)
            log_debug_info("Page navigation complete, waiting for page to stabilize...", category="navigation")
        
        # Wait for page to fully load and stabilize
        log_debug_info("Waiting for Twitter home page to fully load...", category="navigation")
        page.wait_for_timeout(5000)  # Give page time to fully render
        
        # Wait for key elements to be present (ensures page is loaded)
        try:
            log_debug_info("Checking for compose textbox...", category="navigation")
            page.wait_for_selector('[data-testid="tweetTextarea_0"]', state='attached', timeout=30000)
            log_debug_info("Compose textbox found - page is ready!", category="navigation")
        except Exception as e:
            log_debug_info(f"Compose textbox not found, page may not be fully loaded: {e}", level="WARNING", category="navigation")
            page.wait_for_timeout(5000)  # Extra wait
        
        take_debug_screenshot(page, "01_before_posting", category="posting")
        
        # Get user avatar URL before posting
        avatar_url = get_user_avatar_url(page)
        result['avatar_url'] = avatar_url
        
        # Truncate message if needed
        original_length = len(message)
        post_text = truncate_for_x(message)
        result['posted_text'] = post_text
        result['char_count'] = len(post_text)
        result['was_truncated'] = original_length > len(post_text)
        
        # Step 1: Wait for and click compose textbox
        log_debug_info("Waiting for compose textbox to be ready...", category="posting")
        
        # Wait for element to be in DOM, visible, and stable
        compose_textbox = page.locator('[data-testid="tweetTextarea_0"]').first
        compose_textbox.wait_for(state='attached', timeout=30000)  # Wait for element in DOM
        log_debug_info("Compose textbox attached to DOM", category="posting")
        
        compose_textbox.wait_for(state='visible', timeout=30000)  # Wait for visible
        log_debug_info("Compose textbox visible", category="posting")
        
        page.wait_for_timeout(2000)  # Extra wait for animations/rendering
        
        log_debug_info("Clicking compose textbox...", category="posting")
        compose_textbox.click()
        page.wait_for_timeout(1000)  # Wait for focus
        
        # Verify the textarea is focused by checking for placeholder disappearance
        log_debug_info("Verifying textarea is focused...", category="posting")
        is_focused = page.evaluate('''() => {
            const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
            return textarea && document.activeElement === textarea;
        }''')
        log_debug_info(f"Textarea focused: {is_focused}", category="posting")
        
        if not is_focused:
            log_debug_info("Textarea not focused, clicking again...", category="posting")
            compose_textbox.click()
            page.wait_for_timeout(1000)
        
        take_debug_screenshot(page, "02_compose_clicked", category="posting")
        
        # Step 2: Type the message using locator.type() (handles Unicode properly!)
        log_debug_info(f"Typing message with Unicode support: '{post_text}'", category="posting")
        
        # Clear any existing text first using locator
        compose_textbox.fill('')  # Clear the field
        page.wait_for_timeout(500)
        
        # Type the message using locator.type() (handles accents, emojis, Unicode!)
        # Note: locator.type() handles special characters properly, unlike page.keyboard.type()
        compose_textbox.type(post_text, delay=50)  # 50ms delay between characters
        page.wait_for_timeout(1000)  # Wait for typing to complete
        
        # Verify text was actually entered
        entered_text = page.evaluate('''() => {
            const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
            return textarea ? textarea.value : null;
        }''')
        
        # Handle None case
        if entered_text is None:
            log_debug_info("ERROR: Could not read textarea value!", level="ERROR", category="posting")
            entered_text = ""
        
        log_debug_info(f"Text entered in textarea: '{entered_text[:min(50, len(entered_text))] if entered_text else '(empty)'}' (length: {len(entered_text) if entered_text else 0})", category="posting")
        
        # Validate the text matches what we wanted to type
        if not entered_text or len(entered_text) == 0:
            log_debug_info("ERROR: Text was not entered! Trying alternative method...", level="ERROR", category="posting")
            # Fallback: try using fill() with force
            compose_textbox.fill(post_text, force=True)
            page.wait_for_timeout(1000)
            
            # Verify again
            entered_text = page.evaluate('''() => {
                const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
                return textarea ? textarea.value : '';
            }''')
            log_debug_info(f"After fill: Text in textarea: '{entered_text[:min(50, len(entered_text))] if entered_text else '(empty)'}'", category="posting")
        
        # Validate message content matches expected (character by character)
        if entered_text and entered_text.strip() != post_text.strip():
            log_debug_info(f"WARNING: Entered text doesn't match expected!", level="WARNING", category="posting")
            log_debug_info(f"Expected: '{post_text}'", category="posting")
            log_debug_info(f"Got: '{entered_text}'", category="posting")
            
            # Character-by-character comparison to find differences
            for i, (expected_char, got_char) in enumerate(zip(post_text, entered_text)):
                if expected_char != got_char:
                    log_debug_info(f"First difference at position {i}: expected '{expected_char}' ({ord(expected_char)}), got '{got_char}' ({ord(got_char)})", level="WARNING", category="posting")
                    break
            
            # Try one more time with correct text using locator.type()
            log_debug_info("Clearing and retyping correct message with locator.type()...", category="posting")
            compose_textbox.fill('')  # Clear
            page.wait_for_timeout(500)
            compose_textbox.type(post_text, delay=50)  # Use locator.type() for Unicode support
            page.wait_for_timeout(1000)
            
            # Verify again
            entered_text = page.evaluate('''() => {
                const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
                return textarea ? textarea.value : '';
            }''')
            log_debug_info(f"After retry: '{entered_text[:min(50, len(entered_text))] if entered_text else '(empty)'}'", category="posting")
        
        # Trigger input events to make Twitter enable the button
        log_debug_info("Triggering input events to enable post button...", category="posting")
        page.evaluate('''() => {
            const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
            if (textarea) {
                // Fire input and change events
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
                // Trigger focus/blur to ensure Twitter processes the text
                textarea.blur();
                setTimeout(() => textarea.focus(), 100);
            }
        }''')
        
        page.wait_for_timeout(3000)  # Increased to 3s - give time for Twitter to process and enable button
        
        take_debug_screenshot(page, "03_message_typed", category="posting")
        
        # Step 3: Click post button (handle both inline and modal versions)
        log_debug_info("Looking for post button...", category="posting")
        
        # Twitter might show inline composer OR modal composer
        # Try inline version first, then modal version
        post_button = None
        button_selector = None
        
        try:
            # Try inline version first (on home page)
            inline_button = page.locator('[data-testid="tweetButtonInline"]').first
            if inline_button.is_visible(timeout=2000):
                post_button = inline_button
                button_selector = '[data-testid="tweetButtonInline"]'
                log_debug_info("Found inline post button", category="posting")
        except:
            pass
        
        if not post_button:
            # Try modal version (compose dialog)
            try:
                modal_button = page.locator('[data-testid="tweetButton"]').first
                if modal_button.is_visible(timeout=2000):
                    post_button = modal_button
                    button_selector = '[data-testid="tweetButton"]'
                    log_debug_info("Found modal post button", category="posting")
            except:
                pass
        
        if not post_button:
            raise Exception("Could not find post button (tried inline and modal versions)")
        
        log_debug_info(f"Using button selector: {button_selector}", category="posting")
        post_button.wait_for(state='visible', timeout=30000)
        
        # Wait for button to be enabled (works for both inline and modal)
        log_debug_info("Waiting for post button to be enabled...", category="posting")
        
        # Check if button is already enabled
        is_enabled = page.evaluate(f'() => {{const btn = document.querySelector(\'{button_selector}\'); return btn && !btn.disabled;}}')
        log_debug_info(f"Button enabled status: {is_enabled}", category="posting")
        
        if not is_enabled:
            log_debug_info("Button not enabled yet, waiting up to 30 seconds...", category="posting")
            try:
                page.wait_for_function(
                    f'() => {{const btn = document.querySelector(\'{button_selector}\'); return btn && !btn.disabled;}}',
                    timeout=30000
                )
                log_debug_info("Post button is now enabled!", category="posting")
            except PlaywrightTimeoutError:
                # Button still not enabled - try fallback: click anyway or use compose URL
                log_debug_info("WARNING: Button still disabled after 30s. Trying to click anyway...", level="WARNING", category="posting")
                take_debug_screenshot(page, "03b_button_still_disabled", category="posting")
                
                # Last attempt: try the compose/post URL as fallback
                log_debug_info("Attempting fallback: navigating to compose URL...", category="posting")
                page.goto('https://x.com/compose/post', wait_until='domcontentloaded', timeout=30000)
                page.wait_for_timeout(2000)
                
                # Try typing in the compose modal
                try:
                    modal_textarea = page.locator('[data-testid="tweetTextarea_0"]').first
                    modal_textarea.wait_for(state='visible', timeout=10000)
                    modal_textarea.click()
                    modal_textarea.fill(post_text)
                    
                    # Trigger events again
                    page.evaluate('''() => {
                        const textarea = document.querySelector('[data-testid="tweetTextarea_0"]');
                        if (textarea) {
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }''')
                    page.wait_for_timeout(2000)
                    
                    # Find post button in modal
                    modal_post_button = page.locator('[data-testid="tweetButton"]').first
                    modal_post_button.wait_for(state='visible', timeout=10000)
                    page.wait_for_function(
                        '() => {const btn = document.querySelector(\'[data-testid="tweetButton"]\'); return btn && !btn.disabled;}',
                        timeout=15000
                    )
                    post_button = modal_post_button
                    log_debug_info("Fallback compose URL successful!", category="posting")
                except Exception as fallback_error:
                    log_debug_info(f"Fallback failed: {fallback_error}", level="ERROR", category="posting")
                    raise Exception(f"Could not enable post button even with fallback: {fallback_error}")
        
        take_debug_screenshot(page, "03c_before_clicking_post", category="posting")
        post_button.click()
        log_debug_info("Post button clicked!", category="posting")
        page.wait_for_timeout(5000)  # Give time for tweet to submit
        
        take_debug_screenshot(page, "04_after_post_click", category="posting")
        
        # Step 4: Check for duplicate error
        try:
            duplicate_alert = page.locator('text=¡Ups! Eso ya lo dijiste.').first
            if duplicate_alert.is_visible():
                log_debug_info("Duplicate post detected!", level="WARNING", category="posting")
                result['duplicate_detected'] = True
                result['error'] = "Duplicate post detected"
                take_debug_screenshot(page, "05_duplicate_error", category="errors")
                return result
        except:
            pass
        
        # Step 5: Extract post URL
        post_url = extract_post_url(page, post_text)
        result['post_url'] = post_url
        
        # Step 6: Look for success indicators
        success_found = False
        success_indicators = [
            'text=Tu post se envió',
            'text=Your post was sent'
        ]
        
        for indicator in success_indicators:
            try:
                element = page.locator(indicator).first
                if element.is_visible(timeout=3000):
                    log_success(f"Success indicator found: {indicator}", category="posting")
                    success_found = True
                    break
            except:
                continue
        
        # If no explicit success indicator, but we have a post URL, consider it successful
        if not success_found and post_url:
            success_found = True
            log_success("Post URL extracted successfully, assuming post succeeded", category="posting")
        
        # If still no success indicator, assume success if no error occurred
        if not success_found:
            success_found = True
            log_debug_info("No explicit success indicator, but no errors detected", category="posting")
        
        result['success'] = success_found
        
        take_debug_screenshot(page, "06_final_result", category="posting")
        
        # Save post data to JSON if successful
        if result['success']:
            save_post_data(result.copy())
            
    except PlaywrightTimeoutError as e:
        result['error'] = f"Timeout during posting: {e}"
        log_error(page, f"Posting timeout: {e}")
        take_debug_screenshot(page, "error_timeout", category="errors")
    except Exception as e:
        result['error'] = f"Error during posting: {e}"
        log_error(page, f"Posting error: {e}")
        take_debug_screenshot(page, "error_general", category="errors")
    finally:
        result['elapsed_time'] = time.time() - start_time
        
    return result


# Database Integration Functions

def post_tweet_from_database(page: Page, message_id: int = None, text: str = None) -> Dict[str, Any]:
    """
    Post a tweet using message from database or provided text.
    Updates database with posted status.
    
    Args:
        page: Playwright Page instance
        message_id: Database message ID to post (if None, uses text parameter)
        text: Text to post (if message_id is None)
        
    Returns:
        Dictionary with posting results and metadata
    """
    db = get_database()
    
    # Get message from database or use provided text
    if message_id:
        # Get message from database
        messages = db.get_unposted_messages(limit=1000)  # Get a good sample
        message_record = next((m for m in messages if m['id'] == message_id), None)
        if not message_record:
            return {
                'success': False,
                'error': f'Message with ID {message_id} not found or already posted',
                'message_id': message_id,
                'elapsed_time': 0
            }
        
        text_to_post = message_record['message_text']
        profile_username = message_record['profile_username']
    else:
        # Use provided text
        if not text:
            return {
                'success': False,
                'error': 'No message text provided',
                'message_id': None,
                'elapsed_time': 0
            }
        text_to_post = text
        profile_username = "manual"
        message_record = None
    
    log_debug_info(f"Posting tweet from {profile_username}: {text_to_post[:50]}...", category="posting")
    start_time = time.time()
    
    # Validate and prepare text
    if not MessageQualityFilter.is_valid_message(text_to_post):
        error_msg = "Message failed quality validation"
        log_error(page, error_msg)
        return {
            'success': False,
            'error': error_msg,
            'message_id': message_id,
            'posted_text': text_to_post,
            'elapsed_time': time.time() - start_time
        }
    
    # Truncate if necessary
    original_text = text_to_post
    text_to_post = truncate_for_x(text_to_post)
    was_truncated = len(text_to_post) != len(original_text)
    
    result = {
        'success': False,
        'message_id': message_id,
        'posted_text': text_to_post,
        'original_text': original_text,
        'char_count': len(text_to_post),
        'was_truncated': was_truncated,
        'profile_source': profile_username,
        'error': None,
        'elapsed_time': 0,
        'duplicate_detected': False,
        'post_url': None,
        'avatar_url': None,
        'timestamp': time.time(),
        'date_posted': time.strftime('%Y-%m-%d %H:%M:%S')
    }
    
    try:
        # Post the tweet using the original function
        post_result = post_tweet(page, text_to_post)
        
        # Update result with post_tweet response
        result.update(post_result)
        
        # If posting was successful OR duplicate detected, update database
        # (Duplicate means it's already on Twitter, so we should skip it)
        if message_id and (result['success'] or result.get('duplicate_detected', False)):
            # For duplicates, mark with special URL to indicate it was skipped
            post_url_to_save = result.get('post_url') if result['success'] else 'DUPLICATE_SKIPPED'
            
            db.mark_message_posted(
                message_id, 
                post_url=post_url_to_save, 
                avatar_url=result.get('avatar_url')
            )
            
            if result['success']:
                log_success(f"Updated database: message {message_id} marked as posted (URL: {result.get('post_url')}, Avatar: {result.get('avatar_url')})", category="database")
            else:
                log_debug_info(f"⚠️  Duplicate detected: message {message_id} marked as DUPLICATE_SKIPPED to move to next message", level="WARNING", category="database")
        
        # Save to JSON file for backup/compatibility
        save_to_json_backup(result)
        
        result['elapsed_time'] = time.time() - start_time
        
        if result['success']:
            log_success(f"Tweet posted successfully from {profile_username}: {text_to_post[:50]}...", category="posting")
        else:
            log_error(page, f"Tweet posting failed: {result.get('error')}")
        
        return result
        
    except Exception as e:
        error_msg = f"Unexpected error during posting: {e}"
        log_error(page, error_msg)
        result.update({
            'success': False,
            'error': error_msg,
            'elapsed_time': time.time() - start_time
        })
        return result


def get_next_message_to_post(limit: int = 1, profile_filter: str = None) -> Optional[Dict]:
    """
    Get the next message(s) to post from database.
    
    Args:
        limit: Number of messages to retrieve
        profile_filter: Optional profile username to filter by
        
    Returns:
        Message record(s) ready for posting
    """
    db = get_database()
    
    # Fetch more messages than needed to account for quality filtering
    # This ensures we can skip bad messages and find good ones
    fetch_limit = limit * 20  # Fetch 20x more to filter through
    messages = db.get_unposted_messages(limit=fetch_limit)
    
    if profile_filter:
        messages = [m for m in messages if m['profile_username'] == profile_filter]
    
    # Quality filter the messages and mark failed ones
    quality_messages = []
    failed_messages = []
    
    for msg in messages:
        if MessageQualityFilter.is_valid_message(msg['message_text']):
            quality_messages.append(msg)
        else:
            failed_messages.append(msg)
            # Mark this message as failed quality check in database
            log_debug_info(f"⚠️  Message ID {msg['id']} failed quality: '{msg['message_text'][:50]}'", category="database")
    
    # Mark failed messages so they don't get selected again
    if failed_messages:
        conn = sqlite3.connect(db.db_path)
        cursor = conn.cursor()
        for msg in failed_messages:
            # Update message to mark it as "posted" with a special note
            cursor.execute("""
                UPDATE messages 
                SET posted_to_twitter = 1, 
                    posted_at = ?,
                    post_url = 'SKIPPED_QUALITY_FILTER'
                WHERE id = ?
            """, (datetime.now().isoformat(), msg['id']))
        conn.commit()
        conn.close()
        log_debug_info(f"Marked {len(failed_messages)} messages as skipped (quality filter)", category="database")
    
    log_debug_info(f"Found {len(quality_messages)} quality messages ready to post (skipped {len(failed_messages)} low-quality)", category="database")
    
    if limit == 1:
        return quality_messages[0] if quality_messages else None
    else:
        return quality_messages[:limit]  # Return only requested amount


def batch_post_from_database(page: Page, count: int = 5, delay_seconds: int = 300) -> Dict[str, Any]:
    """
    Post multiple tweets from database with delays.
    
    Args:
        page: Playwright Page instance
        count: Number of tweets to post
        delay_seconds: Delay between posts (default 5 minutes)
        
    Returns:
        Batch posting results
    """
    log_debug_info(f"Starting batch posting: {count} tweets with {delay_seconds}s delays", category="posting")
    
    results = {
        'total_attempted': 0,
        'successful_posts': 0,
        'failed_posts': 0,
        'posts': [],
        'total_time': 0
    }
    
    start_time = time.time()
    
    for i in range(count):
        log_debug_info(f"Batch post {i+1}/{count}", category="posting")
        
        # Get next message
        message = get_next_message_to_post()
        if not message:
            log_debug_info("No more messages available for posting", category="posting")
            break
        
        # Post the message
        post_result = post_tweet_from_database(page, message_id=message['id'])
        
        results['posts'].append(post_result)
        results['total_attempted'] += 1
        
        if post_result['success']:
            results['successful_posts'] += 1
            log_success(f"Batch post {i+1} successful: {message['message_text'][:50]}...", category="posting")
        else:
            results['failed_posts'] += 1
            log_error(page, f"Batch post {i+1} failed: {post_result.get('error')}")
        
        # Wait before next post (except for the last one)
        if i < count - 1:
            log_debug_info(f"Waiting {delay_seconds} seconds before next post...", category="posting")
            page.wait_for_timeout(delay_seconds * 1000)
    
    results['total_time'] = time.time() - start_time
    
    log_debug_info(f"Batch posting complete: {results['successful_posts']}/{results['total_attempted']} successful", 
                   category="posting")
    
    return results


def save_to_json_backup(post_result: Dict[str, Any]):
    """Save posting result to JSON file for backup/compatibility."""
    try:
        # Load existing posts
        posts = []
        if POSTS_JSON_FILE.exists():
            with open(POSTS_JSON_FILE, 'r', encoding='utf-8') as f:
                posts = json.load(f)
        
        # Add new post
        posts.append(post_result)
        
        # Save back to file
        with open(POSTS_JSON_FILE, 'w', encoding='utf-8') as f:
            json.dump(posts, f, indent=2, ensure_ascii=False)
        
        log_debug_info(f"Saved post result to {POSTS_JSON_FILE}", category="storage")
        
    except Exception as e:
        # Note: We don't have page context here, so just use regular logging
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f"Failed to save to JSON backup: {e}")


def get_posting_statistics() -> Dict[str, Any]:
    """Get statistics about posting from database."""
    db = get_database()
    
    message_stats = db.get_message_stats()
    
    stats = {
        'total_messages': message_stats['total_messages'],
        'posted_messages': message_stats['posted'],
        'unposted_messages': message_stats['unposted'],
        'posting_percentage': 0 if message_stats['total_messages'] == 0 else 
                             (message_stats['posted'] / message_stats['total_messages']) * 100
    }
    
    # Get quality breakdown of unposted messages
    unposted_messages = db.get_unposted_messages(limit=1000)  # Get up to 1000 for analysis
    quality_unposted = MessageQualityFilter.filter_quality_messages([m['message_text'] for m in unposted_messages])
    
    stats.update({
        'quality_unposted_messages': len(quality_unposted),
        'low_quality_unposted': len(unposted_messages) - len(quality_unposted)
    })
    
    log_debug_info(f"Posting statistics: {stats}", category="statistics")
    return stats


def main():
    """Main function to post a message to Twitter using proxy and database."""
    import sys
    import os
    from pathlib import Path
    
    # Get the parent directory (project root)
    project_root = Path(__file__).parent.parent
    
    # Add parent directory to path for imports
    sys.path.insert(0, str(project_root))
    
    # Change to parent directory to ensure correct file paths
    original_cwd = os.getcwd()
    os.chdir(project_root)
    
    try:
        # Use Laravel database path from config
        import config as twitter_config
        os.environ['DATABASE_PATH'] = twitter_config.DATABASE_PATH
        
        # Reimport database with correct path
        import importlib
        import core.database
        importlib.reload(core.database)
        
        from playwright.sync_api import sync_playwright
        
        print("TWITTER POSTING WITH PROXY AND DATABASE")
        print("="*50)
        
        # Import database after changing directory
        from core.database import get_database, initialize_database
        # Initialize with Laravel database
        db = initialize_database(twitter_config.DATABASE_PATH)
        
        # Get next message to post (with quality filtering)
        message = get_next_message_to_post(limit=1)
        
        if not message:
            print("No quality messages available to post!")
            print("All unposted messages may have been filtered out as low-quality.")
            return False
        
        message_id = message['id']
        
        print(f"Selected message for posting:")
        print(f"  ID: {message_id}")
        print(f"  Profile: {message['profile_username']}")
        print(f"  Text: {message['message_text']}")
        print(f"  Length: {len(message['message_text'])} chars")
        print()
        
        # Check for Twitter auth
        auth_file = Path('auth/auth_x.json')
        if not auth_file.exists():
            print("ERROR: No Twitter authentication file found!")
            print("Please login to Twitter first.")
            return False
        
        print("Found Twitter authentication file")
        
        # Launch browser with proxy and post
        with sync_playwright() as p:
            try:
                print("Launching Firefox with proxy (VPS stable)...")
                
                # Use Firefox for VPS stability
                firefox_options = {
                    'headless': config.HEADLESS,
                    'slow_mo': 50
                }
                
                if PROXY_CONFIG:
                    firefox_options['proxy'] = PROXY_CONFIG
                    print(f"Using proxy: {PROXY_CONFIG['server']}")
                
                browser = p.firefox.launch(**firefox_options)
                print("Firefox launched successfully")
                
                # Load Twitter authentication
                context = browser.new_context(storage_state=str(auth_file))
                page = context.new_page()
                
                # Verify authentication with retries
                print("Verifying Twitter authentication...")
                max_retries = 3
                retry_count = 0
                auth_success = False
                
                while retry_count < max_retries and not auth_success:
                    try:
                        if retry_count > 0:
                            print(f"Retry {retry_count}/{max_retries}...")
                            page.wait_for_timeout(5000)  # Wait before retry
                        
                        # Take screenshot before navigation (only if debug enabled)
                        if is_debug_enabled():
                            try:
                                page.screenshot(path='debug_output/twitter_before_nav.png')
                                print("Screenshot saved: debug_output/twitter_before_nav.png")
                            except:
                                pass
                        
                        print("Navigating to Twitter home...")
                        page.goto('https://x.com/home', timeout=60000, wait_until='domcontentloaded')
                        print("Navigation complete, waiting for page to stabilize...")
                        page.wait_for_timeout(8000)  # Increased from 5s to 8s - give page time to fully load
                        
                        # Wait for key element to ensure page is ready
                        print("Waiting for page elements to load...")
                        try:
                            page.wait_for_selector('[data-testid="tweetTextarea_0"]', state='attached', timeout=20000)
                            print("Page elements loaded successfully")
                        except:
                            print("Warning: Some page elements may not be loaded yet")
                            page.wait_for_timeout(5000)  # Extra wait
                        
                        # Take screenshot after navigation (only if debug enabled)
                        if is_debug_enabled():
                            try:
                                page.screenshot(path='debug_output/twitter_after_nav.png')
                                print("Screenshot saved: debug_output/twitter_after_nav.png")
                            except:
                                pass
                        
                        if 'login' in page.url or 'i/flow' in page.url:
                            print(f"WARNING: Login page detected (retry {retry_count + 1})")
                            retry_count += 1
                            continue
                        
                        print("Successfully authenticated to Twitter!")
                        auth_success = True
                        
                        # Fetch REAL profile info from Twitter session
                        print("=" * 60)
                        print("🔍 FETCHING PROFILE INFORMATION FROM TWITTER...")
                        print("=" * 60)
                        try:
                            # Extract username from URL or page
                            username = None
                            display_name = None
                            avatar_url = None
                            
                            # METHOD 1: Try to extract from account menu
                            print("📋 Method 1: Extracting from account menu...")
                            try:
                                # Click account menu to reveal profile info
                                account_menu = page.locator('[data-testid="SideNav_AccountSwitcher_Button"]').first
                                if account_menu.is_visible(timeout=3000):
                                    print("   ✓ Account menu found")
                                    # Get username from account menu
                                    username_elem = page.locator('[data-testid="SideNav_AccountSwitcher_Button"] [dir="ltr"]').first
                                    if username_elem.is_visible(timeout=2000):
                                        username_text = username_elem.inner_text(timeout=2000)
                                        if username_text and username_text.startswith('@'):
                                            username = username_text.replace('@', '')
                                            print(f"   ✅ Found username from account menu: @{username}")
                                    
                                    # Get display name from account menu
                                    display_name_elem = page.locator('[data-testid="SideNav_AccountSwitcher_Button"] span[dir="auto"]').first
                                    if display_name_elem.is_visible(timeout=2000):
                                        display_name = display_name_elem.inner_text(timeout=2000)
                                        print(f"   ✅ Found display name: {display_name}")
                                else:
                                    print("   ✗ Account menu not visible")
                            except Exception as e:
                                print(f"   ✗ Could not extract from account menu: {e}")
                            
                            # METHOD 2: Extract from page URL (fallback)
                            if not username:
                                print("📋 Method 2: Extracting from page inspection...")
                                try:
                                    # Try to get username from profile link in sidebar
                                    profile_link = page.locator('a[data-testid="AppTabBar_Profile_Link"]').first
                                    if profile_link.is_visible(timeout=2000):
                                        href = profile_link.get_attribute('href')
                                        if href:
                                            username = href.replace('/', '')
                                            print(f"   ✅ Found username from profile link: @{username}")
                                except Exception as e:
                                    print(f"   ✗ Could not extract from profile link: {e}")
                            
                            # METHOD 3: Use JavaScript to extract from page data
                            if not username or not display_name:
                                print("📋 Method 3: Extracting via JavaScript...")
                                try:
                                    profile_data = page.evaluate('''() => {
                                        // Try to find username and display name from DOM
                                        const accountSwitcher = document.querySelector('[data-testid="SideNav_AccountSwitcher_Button"]');
                                        if (accountSwitcher) {
                                            const usernameEl = accountSwitcher.querySelector('[dir="ltr"]');
                                            const displayNameEl = accountSwitcher.querySelector('span[dir="auto"]');
                                            return {
                                                username: usernameEl ? usernameEl.textContent : null,
                                                displayName: displayNameEl ? displayNameEl.textContent : null
                                            };
                                        }
                                        return {username: null, displayName: null};
                                    }''')
                                    
                                    if not username and profile_data['username']:
                                        username = profile_data['username'].replace('@', '')
                                        print(f"   ✅ Found username via JavaScript: @{username}")
                                    if not display_name and profile_data['displayName']:
                                        display_name = profile_data['displayName']
                                        print(f"   ✅ Found display name via JavaScript: {display_name}")
                                except Exception as e:
                                    print(f"   ✗ JavaScript extraction failed: {e}")
                            
                            # Get avatar URL
                            print("📸 Fetching avatar URL...")
                            avatar_url = get_user_avatar_url(page)
                            if avatar_url:
                                print(f"   ✅ Found avatar URL: {avatar_url}")
                            else:
                                print("   ✗ Could not find avatar URL")
                            
                            # If we got profile info, save it
                            if username:
                                print("=" * 60)
                                print("💾 SAVING PROFILE INFORMATION TO .ENV FILES")
                                print("=" * 60)
                                print(f"📝 Username:     @{username}")
                                print(f"📝 Display Name: {display_name if display_name else '(not found)'}")
                                print(f"📝 Avatar URL:   {avatar_url if avatar_url else '(not found)'}")
                                print("")
                                
                                import re
                                updates = [
                                    ('X_USERNAME', f'@{username}'),
                                ]
                                if display_name:
                                    updates.append(('X_DISPLAY_NAME', display_name))
                                if avatar_url:
                                    updates.append(('X_AVATAR_URL', avatar_url))
                                
                                # Save to both .env files
                                saved_count = 0
                                for env_file in ['copy.env', '.env']:
                                    env_path = Path(env_file)
                                    if env_path.exists():
                                        try:
                                            with open(env_path, 'r') as f:
                                                env_content = f.read()
                                            
                                            for key, value in updates:
                                                pattern = f"^{key}=.*$"
                                                if re.search(pattern, env_content, re.MULTILINE):
                                                    env_content = re.sub(pattern, f"{key}={value}", env_content, flags=re.MULTILINE)
                                                else:
                                                    env_content += f"\n{key}={value}\n"
                                            
                                            with open(env_path, 'w') as f:
                                                f.write(env_content)
                                            print(f"   ✅ Saved to {env_file}")
                                            saved_count += 1
                                        except Exception as e:
                                            print(f"   ⚠️  Could not save to {env_file}: {e}")
                                
                                if saved_count > 0:
                                    print("")
                                    print("✅ PROFILE INFO SUCCESSFULLY SAVED!")
                                    print("   All future images will use this Twitter account's profile")
                                    print("=" * 60)
                                else:
                                    print("")
                                    print("❌ ERROR: Could not save to any .env file")
                                    print("=" * 60)
                            else:
                                print("=" * 60)
                                print("⚠️  WARNING: Could not extract username from Twitter session")
                                print("   Will use existing .env values for image generation")
                                print("=" * 60)
                        except Exception as e:
                            print(f"Warning: Failed to fetch profile info: {e}")
                            print("Continuing with posting...")
                        
                    except PlaywrightTimeoutError as e:
                        retry_count += 1
                        print(f"Timeout error (attempt {retry_count}/{max_retries}): {str(e)[:100]}")
                        if retry_count >= max_retries:
                            print("ERROR: Max retries reached for authentication!")
                            browser.close()
                            return False
                
                if not auth_success:
                    print("ERROR: Not logged in to Twitter after retries!")
                    browser.close()
                    return False
                
                # Post the message with retries
                print(f"Posting message ID {message_id}...")
                max_post_retries = 2
                post_retry = 0
                result = None
                
                while post_retry < max_post_retries:
                    try:
                        if post_retry > 0:
                            print(f"Retrying post (attempt {post_retry + 1}/{max_post_retries})...")
                            page.wait_for_timeout(3000)
                        
                        # Take screenshot before posting (only if debug enabled)
                        if is_debug_enabled():
                            try:
                                page.screenshot(path=f'debug_output/twitter_before_post_attempt{post_retry + 1}.png')
                                print(f"Screenshot saved: debug_output/twitter_before_post_attempt{post_retry + 1}.png")
                            except:
                                pass
                        
                        result = post_tweet_from_database(page, message_id=message_id)
                        
                        # Take screenshot after posting (only if debug enabled)
                        if is_debug_enabled():
                            try:
                                page.screenshot(path=f'debug_output/twitter_after_post_attempt{post_retry + 1}.png')
                                print(f"Screenshot saved: debug_output/twitter_after_post_attempt{post_retry + 1}.png")
                            except:
                                pass
                        
                        if result.get('success', False):
                            break  # Success, exit retry loop
                        else:
                            post_retry += 1
                            if post_retry >= max_post_retries:
                                print("Max posting retries reached")
                                break
                    
                    except Exception as e:
                        print(f"Error during posting attempt {post_retry + 1}: {str(e)[:100]}")
                        post_retry += 1
                        if post_retry >= max_post_retries:
                            result = {'success': False, 'error': str(e)}
                            break
                
                if not result:
                    result = {'success': False, 'error': 'No result from posting'}
                
                print("POSTING RESULT:")
                print(f"  Success: {result.get('success', False)}")
                print(f"  Error: {result.get('error', 'None')}")
                print(f"  Post URL: {result.get('post_url', 'None')}")
                print(f"  Avatar URL: {result.get('avatar_url', 'None')}")
                print(f"  Elapsed time: {result.get('elapsed_time', 0):.2f}s")
                
                if result.get('success', False):
                    print("\nSUCCESS: Message posted to Twitter!")
                    if result.get('post_url'):
                        print(f"LIVE POST: {result.get('post_url')}")
                else:
                    print(f"\nFAILED: {result.get('error', 'Unknown error')}")
                
                browser.close()
                return result.get('success', False)
                
            except Exception as e:
                print(f"ERROR: {e}")
                return False
    finally:
        # Restore original working directory
        os.chdir(original_cwd)


if __name__ == "__main__":
    success = main()
    if success:
        print("\nTwitter posting completed successfully!")
    else:
        print("\nTwitter posting failed!")

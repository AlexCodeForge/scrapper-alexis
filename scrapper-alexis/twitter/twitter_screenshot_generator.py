#!/usr/bin/env python3
"""
Twitter Screenshot Generator
Generates perfect screenshots for all posts in twitter_posts.json using the template approach.
"""

import json
import logging
import os
import re
import requests
from pathlib import Path
from typing import Dict, List, Any
from urllib.parse import quote
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

# Try to import debug helper, but make it optional
try:
    from debug_helper import DebugSession
    DEBUG_HELPER_AVAILABLE = True
except ImportError:
    DEBUG_HELPER_AVAILABLE = False
    DebugSession = None

# Import proxy config from main config
import sys
sys.path.insert(0, str(Path(__file__).parent.parent))
import config

# Configuration - use proxy from config (CRITICAL!)
PROXY_CONFIG = config.PROXY_CONFIG
if not PROXY_CONFIG:
    print("⚠️  ERROR: No proxy configured! Twitter REQUIRES proxy!")
    print("   Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to copy.env")
    sys.exit(1)

POSTS_JSON_FILE = Path('twitter_posts.json')
TEMPLATE_FILE = Path('tweet_template.html')
SCREENSHOTS_DIR = Path('screenshots')
SCREENSHOTS_DIR.mkdir(exist_ok=True)

# Avatar cache directory
AVATAR_CACHE_DIR = Path('avatar_cache')
AVATAR_CACHE_DIR.mkdir(exist_ok=True)

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


def create_browser_with_proxy(p, use_proxy: bool = True, headless: bool = False):
    """Create browser with proxy configuration."""
    launch_options = {
        'headless': headless,
        'slow_mo': 300,
        'args': [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage'
        ]
    }
    
    if use_proxy:
        launch_options['proxy'] = {
            "server": PROXY_CONFIG["server"],
            "username": PROXY_CONFIG["username"],
            "password": PROXY_CONFIG["password"]
        }
        logger.info(f"Using proxy: {PROXY_CONFIG['server']}")
    else:
        logger.info("Running without proxy")
    
    return p.chromium.launch(**launch_options)


def sanitize_filename(text: str, max_length: int = 50) -> str:
    """Create a safe filename from tweet text."""
    # Remove or replace problematic characters
    sanitized = re.sub(r'[<>:"/\\|?*]', '', text)
    sanitized = re.sub(r'\s+', '_', sanitized.strip())
    
    # Limit length
    if len(sanitized) > max_length:
        sanitized = sanitized[:max_length]
    
    return sanitized or "tweet"


def download_avatar_through_proxy(avatar_url: str, use_proxy: bool = True) -> str:
    """Download avatar image through proxy and return local path."""
    try:
        if not avatar_url:
            logger.warning("No avatar URL provided")
            return None
        
        # Get high-quality version
        hq_avatar_url = get_high_quality_avatar_url(avatar_url)
        logger.info(f"Downloading avatar: {hq_avatar_url}")
        
        # Create filename from URL
        import hashlib
        url_hash = hashlib.md5(hq_avatar_url.encode()).hexdigest()
        avatar_filename = f"avatar_{url_hash}.jpg"
        local_avatar_path = AVATAR_CACHE_DIR / avatar_filename
        
        # Check if already cached
        if local_avatar_path.exists():
            logger.info(f"Using cached avatar: {local_avatar_path}")
            return str(local_avatar_path)
        
        # Setup requests session with proxy
        session = requests.Session()
        if use_proxy:
            proxies = {
                'http': f"http://{PROXY_CONFIG['username']}:{PROXY_CONFIG['password']}@{PROXY_CONFIG['server'].replace('http://', '')}",
                'https': f"http://{PROXY_CONFIG['username']}:{PROXY_CONFIG['password']}@{PROXY_CONFIG['server'].replace('http://', '')}"
            }
            session.proxies.update(proxies)
            logger.info(f"🔄 Using proxy for avatar download: {PROXY_CONFIG['server']}")
        
        # Download avatar
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = session.get(hq_avatar_url, headers=headers, timeout=10)
        response.raise_for_status()
        
        # Save to local file
        with open(local_avatar_path, 'wb') as f:
            f.write(response.content)
        
        logger.info(f"✅ Avatar downloaded successfully: {local_avatar_path}")
        return str(local_avatar_path)
        
    except Exception as e:
        logger.error(f"❌ Failed to download avatar: {e}")
        return None


def get_high_quality_avatar_url(avatar_url: str) -> str:
    """Convert avatar URL to high-quality version as per the guide."""
    if not avatar_url:
        # Default high-quality avatar from the guide
        return "https://pbs.twimg.com/profile_images/1958793949692260352/PAlLs8Va_400x400.jpg"
    
    # Replace _normal.jpg with _400x400.jpg for high quality
    return avatar_url.replace('_normal.jpg', '_400x400.jpg')


def get_user_display_name(page) -> str:
    """Extract the current user's display name from Twitter page."""
    try:
        # Multiple approaches to find the display name
        display_name_selectors = [
            # Account switcher button area
            '[data-testid="SideNav_AccountSwitcher_Button"] [dir="ltr"] span',
            # Profile link in navigation (generic selector)
            'a[data-testid="AppTabBar_Profile_Link"] span[dir="ltr"]',
            # Any bold/semibold text near avatar in nav
            'nav [role="link"] span[dir="ltr"]',
        ]
        
        for selector in display_name_selectors:
            try:
                elements = page.locator(selector).all()
                for elem in elements:
                    if elem.is_visible(timeout=1000):
                        text = elem.inner_text().strip()
                        # Display names are usually non-empty and don't start with @
                        if text and not text.startswith('@') and len(text) > 1:
                            logger.info(f"Found display name: {text}")
                            return text
            except:
                continue
        
        logger.warning("Could not extract display name, using environment default")
        return os.getenv('X_DISPLAY_NAME', 'Twitter User')
        
    except Exception as e:
        logger.error(f"Error extracting display name: {e}")
        return os.getenv('X_DISPLAY_NAME', 'Twitter User')


def get_user_username(page) -> str:
    """Extract the current user's username/handle from Twitter page."""
    try:
        # Multiple approaches to find the username
        username_selectors = [
            # Account switcher button area - look for @username
            '[data-testid="SideNav_AccountSwitcher_Button"]',
            # Profile link in navigation (generic selector, not hardcoded)
            'a[data-testid="AppTabBar_Profile_Link"]',
            # Navigation area
            'nav [role="link"]',
        ]
        
        for selector in username_selectors:
            try:
                elements = page.locator(selector).all()
                for elem in elements:
                    if elem.is_visible(timeout=1000):
                        text = elem.inner_text().strip()
                        # Find @username pattern in the text
                        if '@' in text:
                            # Extract the @username part
                            for line in text.split('\n'):
                                if line.strip().startswith('@'):
                                    username = line.strip()
                                    logger.info(f"Found username: {username}")
                                    return username
            except:
                continue
        
        # Try extracting from URL
        try:
            current_url = page.url
            if 'x.com/' in current_url or 'twitter.com/' in current_url:
                # Try to get username from profile URL if we navigated there
                import re
                match = re.search(r'(?:x\.com|twitter\.com)/([a-zA-Z0-9_]+)', current_url)
                if match:
                    username = f"@{match.group(1)}"
                    logger.info(f"Extracted username from URL: {username}")
                    return username
        except:
            pass
                
        logger.warning("Could not extract username, using environment default")
        return os.getenv('X_USERNAME', '@username')
        
    except Exception as e:
        logger.error(f"Error extracting username: {e}")
        return os.getenv('X_USERNAME', '@username')


def update_template(tweet_text: str, local_avatar_path: str, display_name: str = None, username: str = None) -> str:
    """Update the HTML template with tweet content, avatar, display name, and username."""
    try:
        # Read the template
        with open(TEMPLATE_FILE, 'r', encoding='utf-8') as f:
            template_content = f.read()
        
        # Convert local path to file:// URL for HTML
        if local_avatar_path and os.path.exists(local_avatar_path):
            avatar_file_url = f"file:///{os.path.abspath(local_avatar_path).replace(os.sep, '/')}"
            logger.info(f"Using local avatar: {avatar_file_url}")
        else:
            # Fallback to default high-quality URL
            avatar_file_url = "https://pbs.twimg.com/profile_images/1958793949692260352/PAlLs8Va_400x400.jpg"
            logger.warning(f"Using fallback avatar URL: {avatar_file_url}")
        
        # Update template with local avatar
        updated_content = template_content.replace(
            'src="https://pbs.twimg.com/profile_images/1958793949692260352/PAlLs8Va_400x400.jpg"',
            f'src="{avatar_file_url}"'
        )
        
        # Update display name if provided
        if display_name:
            import re
            # Update display name in span
            display_name_pattern = r'(<span class="display-name">)(.*?)(</span>)'
            updated_content = re.sub(
                display_name_pattern,
                f'\\1{display_name}\\3',
                updated_content,
                flags=re.DOTALL
            )
            # Also update display name in image alt attribute
            alt_pattern = r'(alt=")([^"]*?)(")'
            updated_content = re.sub(
                alt_pattern,
                f'\\1{display_name}\\3',
                updated_content
            )
            logger.info(f"Updated display name to: {display_name}")
        
        # Update username if provided
        if username:
            username_pattern = r'(<span class="username">)(.*?)(</span>)'
            updated_content = re.sub(
                username_pattern,
                f'\\1{username}\\3',
                updated_content,
                flags=re.DOTALL
            )
            logger.info(f"Updated username to: {username}")
        
        # Update tweet text (find the <p class="tweet-text"> content)
        tweet_pattern = r'(<p class="tweet-text">)(.*?)(</p>)'
        updated_content = re.sub(
            tweet_pattern,
            f'\\1{tweet_text}\\3',
            updated_content,
            flags=re.DOTALL
        )
        
        # Create temporary template file
        temp_template = Path('temp_tweet_template.html')
        with open(temp_template, 'w', encoding='utf-8') as f:
            f.write(updated_content)
        
        return str(temp_template.absolute())
        
    except Exception as e:
        logger.error(f"Error updating template: {e}")
        return None


def generate_screenshot(page, post_data: Dict[str, Any], use_proxy: bool = True, debug_enabled: bool = False, 
                       display_name: str = None, username: str = None) -> str:
    """Generate screenshot for a single post."""
    try:
        tweet_text = post_data.get('posted_text', '')
        avatar_url = post_data.get('avatar_url', '')
        post_date = post_data.get('date_posted', '')
        
        logger.info(f"Generating screenshot for: '{tweet_text[:50]}...'")
        
        # Download avatar through proxy first
        logger.info("🔄 Downloading avatar through proxy...")
        local_avatar_path = download_avatar_through_proxy(avatar_url, use_proxy)
        
        if not local_avatar_path:
            logger.warning("❌ Avatar download failed, will use fallback")
        else:
            logger.info(f"✅ Avatar ready: {local_avatar_path}")
        
        # Update template with post content, local avatar, display name, and username
        temp_template_path = update_template(tweet_text, local_avatar_path, display_name, username)
        if not temp_template_path:
            logger.error("Failed to update template")
            return None
        
        # Navigate to the template
        file_url = f"file:///{temp_template_path.replace(os.sep, '/')}"
        logger.info(f"Navigating to: {file_url}")
        page.goto(file_url, wait_until='domcontentloaded')
        
        # Wait for assets to load (as per the guide)
        page.wait_for_timeout(2000)
        
        if debug_enabled:
            try:
                from debug_helper import take_debug_screenshot
                take_debug_screenshot(page, "template_loaded", category="screenshot")
            except:
                pass
        
        # Generate filename
        safe_text = sanitize_filename(tweet_text)
        timestamp = post_date.replace(':', '-').replace(' ', '_') if post_date else 'unknown'
        screenshot_filename = f"{timestamp}_{safe_text}.png"
        screenshot_path = SCREENSHOTS_DIR / screenshot_filename
        
        # Take screenshot of the screenshot wrapper (includes black space)
        logger.info("Taking screenshot of screenshot wrapper...")
        try:
            # Try to find the screenshot wrapper element (includes black space)
            screenshot_wrapper = page.locator('.screenshot-wrapper').first
            if screenshot_wrapper.is_visible():
                screenshot_wrapper.screenshot(
                    path=str(screenshot_path),
                    type='png'
                )
                logger.info(f"✅ Screenshot saved: {screenshot_path}")
            else:
                # Fallback: try tweet-container
                tweet_container = page.locator('.tweet-container').first
                if tweet_container.is_visible():
                    tweet_container.screenshot(
                        path=str(screenshot_path),
                        type='png'
                    )
                    logger.info(f"✅ Screenshot saved (fallback): {screenshot_path}")
                else:
                    # Last fallback: screenshot the entire page
                    page.screenshot(path=str(screenshot_path), full_page=True)
                    logger.info(f"✅ Full page screenshot saved: {screenshot_path}")
        except Exception as e:
            logger.warning(f"Wrapper screenshot failed, trying full page: {e}")
            page.screenshot(path=str(screenshot_path), full_page=True)
            logger.info(f"✅ Full page screenshot saved: {screenshot_path}")
        
        # Clean up temporary template
        try:
            Path(temp_template_path).unlink()
        except:
            pass
        
        return str(screenshot_path)
        
    except Exception as e:
        logger.error(f"Error generating screenshot: {e}")
        return None


def load_posts_data() -> List[Dict[str, Any]]:
    """Load posts from twitter_posts.json."""
    try:
        if not POSTS_JSON_FILE.exists():
            logger.error(f"Posts file not found: {POSTS_JSON_FILE}")
            return []
        
        with open(POSTS_JSON_FILE, 'r', encoding='utf-8') as f:
            posts = json.load(f)
        
        logger.info(f"Loaded {len(posts)} posts from {POSTS_JSON_FILE}")
        return posts
        
    except Exception as e:
        logger.error(f"Error loading posts data: {e}")
        return []


def main():
    """Main function to generate screenshots for all posts."""
    import argparse
    
    parser = argparse.ArgumentParser(description='Generate screenshots for Twitter posts')
    parser.add_argument('--no-proxy', action='store_true', help='Disable proxy')
    parser.add_argument('--debug', action='store_true', help='Enable debug logging')
    parser.add_argument('--headless', action='store_true', help='Run in headless mode')
    
    args = parser.parse_args()
    
    # Initialize debug session if requested
    debug_session = None
    if args.debug:
        if DEBUG_HELPER_AVAILABLE:
            # Debug is controlled via database settings at http://213.199.33.207:8006/settings
            debug_session = DebugSession("twitter_screenshots", script_type="twitter")
            logger = debug_session.logger
        else:
            logging.basicConfig(level=logging.DEBUG)
            logger = logging.getLogger(__name__)
    
    logger.info("=" * 70)
    logger.info("TWITTER SCREENSHOT GENERATOR")
    logger.info("=" * 70)
    logger.info(f"Using proxy: {not args.no_proxy}")
    logger.info(f"Debug mode: {args.debug}")
    logger.info(f"Screenshots directory: {SCREENSHOTS_DIR}")
    
    # Load posts data
    posts = load_posts_data()
    if not posts:
        logger.error("No posts found to process")
        return 1
    
    successful_screenshots = 0
    failed_screenshots = 0
    
    try:
        with sync_playwright() as p:
            # Create browser (headless if specified)
            browser = create_browser_with_proxy(p, use_proxy=not args.no_proxy, headless=args.headless)
            
            # Load Twitter auth if available to extract profile info
            auth_file = Path('auth/auth_x.json')
            if auth_file.exists():
                logger.info("Loading Twitter authentication...")
                context = browser.new_context(storage_state=str(auth_file))
            else:
                logger.warning("No Twitter authentication found, using default profile info")
                context = browser.new_context()
            
            page = context.new_page()
            
            # Extract display name and username from Twitter if authenticated
            display_name = None
            username = None
            
            if auth_file.exists():
                try:
                    logger.info("Navigating to Twitter to extract profile info...")
                    page.goto('https://x.com/home', wait_until='domcontentloaded', timeout=60000)
                    page.wait_for_timeout(5000)  # Wait for page to load
                    
                    display_name = get_user_display_name(page)
                    username = get_user_username(page)
                    
                    logger.info(f"✅ Extracted profile info - Display: {display_name}, Username: {username}")
                except Exception as e:
                    logger.warning(f"Failed to extract profile info from Twitter: {e}")
                    logger.info("Will use default values")
            
            logger.info(f"Processing {len(posts)} posts...")
            
            for i, post in enumerate(posts, 1):
                logger.info(f"\n--- Post {i}/{len(posts)} ---")
                
                if not post.get('success', False):
                    logger.warning(f"Skipping failed post: {post.get('error', 'Unknown error')}")
                    continue
                
                screenshot_path = generate_screenshot(page, post, not args.no_proxy, args.debug, 
                                                    display_name, username)
                
                if screenshot_path:
                    successful_screenshots += 1
                    logger.info(f"✅ Post {i} screenshot: {screenshot_path}")
                else:
                    failed_screenshots += 1
                    logger.error(f"❌ Post {i} screenshot failed")
                
                # Small delay between screenshots
                page.wait_for_timeout(1000)
            
            context.close()
            browser.close()
    
    except Exception as e:
        logger.error(f"Critical error: {e}")
        return 1
    
    finally:
        if debug_session:
            debug_session.close()
    
    # Final report
    logger.info("=" * 70)
    logger.info("SCREENSHOT GENERATION COMPLETE")
    logger.info("=" * 70)
    logger.info(f"Total posts processed: {len(posts)}")
    logger.info(f"Successful screenshots: {successful_screenshots}")
    logger.info(f"Failed screenshots: {failed_screenshots}")
    logger.info(f"Screenshots saved in: {SCREENSHOTS_DIR}")
    logger.info("=" * 70)
    
    return 0 if failed_screenshots == 0 else 1


if __name__ == "__main__":
    import sys
    print("Starting Twitter Screenshot Generator...")
    sys.exit(main())

#!/usr/bin/env python3
"""
Twitter Automation Script - Simplified Version
Uses only 3 scripts: twitter_auth.py, twitter_post.py, and debug_helper.py

Usage:
    python twitter.py "Your message here"
    python twitter.py "Your message here" --debug
"""

import sys
import argparse
import logging
from playwright.sync_api import sync_playwright

# Import our modules
from twitter_auth import ensure_authenticated
from twitter_post import post_tweet
from debug_helper import DebugSession

# Import proxy config from main config
sys.path.insert(0, str(Path(__file__).parent.parent))
import config

# Configuration - use proxy from config (CRITICAL!)
PROXY_CONFIG = config.PROXY_CONFIG
if not PROXY_CONFIG:
    print("⚠️  ERROR: No proxy configured! Twitter REQUIRES proxy!")
    print("   Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to copy.env")
    sys.exit(1)

# Credentials (from credenciales.txt)
X_USERNAME = "soyemizapata"
X_PASSWORD = "AleGar27$"

def validate_and_fix_encoding(text: str) -> str:
    """Validate and fix text encoding to prevent posting malformed characters."""
    try:
        # Check for common encoding problems - both as combined strings and individual unicode chars
        problematic_patterns = [
            'Ã±', 'Ã©', 'Ã¡', 'Ãº', 'Ã³', 'Ã­', 'Ã¢', 'Ã¤', 'Ã¶', 'Ã¼', 'Ã§',  # Combined
            '\u00C3\u00B1', '\u00C3\u00A9', '\u00C3\u00A1', '\u00C3\u00BA',  # Unicode combinations
            '\u00C3\u00B3', '\u00C3\u00AD', '\u00C3\u00A2', '\u00C3\u00A4',
            '\u00C3\u00B6', '\u00C3\u00BC', '\u00C3\u00A7'
        ]
        
        # Also check for specific malformed Unicode characters that indicate encoding issues
        malformed_unicode_chars = ['\u00C3', '\u0192', '\u00C2', '\u00B1', '\u00A9', '\u00A1']
        
        # Check for problematic patterns
        for pattern in problematic_patterns:
            if pattern in text:
                return None  # Return None to indicate encoding issue
        
        # Check for sequences of malformed Unicode characters
        for i, char in enumerate(text):
            if char in malformed_unicode_chars:
                # If we find a malformed character, check the next character too
                if i < len(text) - 1:
                    next_char = text[i + 1]
                    # Common malformed sequences
                    if (char == '\u00C3' and next_char in ['\u00B1', '\u00A9', '\u00A1', '\u00BA', '\u00B3', '\u00AD']) or \
                       (char == '\u00C2' and next_char in ['\u00B1', '\u00A0']):
                        return None
        
        # Ensure the text is properly UTF-8 encoded
        if isinstance(text, str):
            try:
                fixed_text = text.encode('utf-8').decode('utf-8')
                return fixed_text
            except UnicodeError:
                return None
        return text
    except Exception as e:
        return None

def create_browser_with_proxy(playwright_instance, use_proxy: bool = True):
    launch_options = {
        'headless': False,
        'slow_mo': 300,
        'args': [
            '--no-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security'
        ]
    }
    
    if use_proxy:
        launch_options['proxy'] = PROXY_CONFIG
        
    return playwright_instance.chromium.launch(**launch_options)

def main():
    parser = argparse.ArgumentParser(description='Twitter Automation - Simplified')
    parser.add_argument('message', help='Message to post')
    parser.add_argument('--no-proxy', action='store_true', help='Disable proxy')
    parser.add_argument('--debug', action='store_true', help='Enable debug logging with screenshots')
    
    args = parser.parse_args()
    
    # Initialize debug session if requested
    debug_session = None
    if args.debug:
        debug_session = DebugSession("twitter_simple")
        logger = debug_session.logger
    else:
        logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
        logger = logging.getLogger(__name__)
    
    logger.info("=" * 70)
    logger.info("SIMPLIFIED TWITTER AUTOMATION")
    logger.info("=" * 70)
    logger.info(f"Message to post: '{args.message}'")
    logger.info(f"Character count: {len(args.message)}")
    
    # Validate encoding before proceeding
    validated_message = validate_and_fix_encoding(args.message)
    if validated_message is None:
        logger.error("⚠️ ENCODING ISSUE DETECTED in message!")
        logger.error("❌ ABORTING to prevent account ban!")
        logger.error("The message contains malformed characters that could get the account banned.")
        return 1
    
    logger.info(f"✅ Message encoding validated successfully")
    logger.info(f"Using proxy: {not args.no_proxy}")
    logger.info(f"Debug mode: {args.debug}")
    
    try:
        with sync_playwright() as p:
            # Create browser
            browser = create_browser_with_proxy(p, use_proxy=not args.no_proxy)
            
            try:
                # Step 1: Ensure authentication
                logger.info("Step 1: Ensuring authentication...")
                context = ensure_authenticated(browser, X_USERNAME, X_PASSWORD, PROXY_CONFIG if not args.no_proxy else None)
                
                if not context:
                    logger.error("Authentication failed!")
                    return 1
                
                # Step 2: Create page and post tweet
                logger.info("Step 2: Creating page and posting tweet...")
                page = context.new_page()
                
                # Navigate to home page
                page.goto('https://x.com/home', wait_until='domcontentloaded')
                page.wait_for_timeout(2000)
                
                # Post the tweet using the validated message
                result = post_tweet(page, validated_message)
                
                # Step 3: Report results
                logger.info("=" * 70)
                logger.info("POSTING RESULTS")
                logger.info("=" * 70)
                
                if result['success']:
                    logger.info("TWEET POSTED SUCCESSFULLY!")
                    logger.info(f"  Posted text: '{result['posted_text']}'")
                    logger.info(f"  Character count: {result['char_count']}")
                    logger.info(f"  Was truncated: {result['was_truncated']}")
                    logger.info(f"  Time taken: {result['elapsed_time']:.2f} seconds")
                    logger.info(f"  Post URL: {result.get('post_url', 'Not extracted')}")
                    logger.info(f"  Avatar URL: {result.get('avatar_url', 'Not extracted')}")
                    logger.info(f"  Date posted: {result.get('date_posted', 'Unknown')}")
                    
                    if result.get('duplicate_detected'):
                        logger.warning("  Note: Duplicate post detected")
                    
                    logger.info(f"  Data saved to: twitter_posts.json")
                    
                else:
                    logger.error("TWEET POSTING FAILED!")
                    logger.error(f"  Error: {result.get('error', 'Unknown error')}")
                    logger.error(f"  Time taken: {result['elapsed_time']:.2f} seconds")
                
                logger.info("=" * 70)
                
                # Keep browser open briefly if not headless
                logger.info("Keeping browser open for 3 seconds...")
                page.wait_for_timeout(3000)
                
                return 0 if result['success'] else 1
                
            finally:
                browser.close()
                if debug_session:
                    debug_session.close()
                
    except KeyboardInterrupt:
        logger.info("Script interrupted by user")
        return 1
    except Exception as e:
        logger.error(f"Critical error: {e}")
        return 1

if __name__ == "__main__":
    print("Starting Simplified Twitter Automation...")
    exit_code = main()
    
    if exit_code == 0:
        print("Script completed successfully!")
    else:
        print("Script failed!")
        
    sys.exit(exit_code)

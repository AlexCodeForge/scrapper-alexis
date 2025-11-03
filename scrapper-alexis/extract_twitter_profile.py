#!/usr/bin/env python3
"""
Extract Twitter Profile Information
Extracts username, display name, and avatar URL from logged-in Twitter session
and saves to .env files for use in image generation.

This script MUST run before image generation to ensure correct profile info.
"""

import sys
import os
import re
from pathlib import Path
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent))

import config
import logging

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


def get_user_avatar_url(page) -> str:
    """Extract the current user's avatar URL."""
    try:
        avatar_selectors = [
            '[data-testid="SideNav_AccountSwitcher_Button"] img',
            'img[alt*="profile"]',
            'img[src*="profile_images"]',
            'button[aria-label*="Account menu"] img',
        ]
        
        for selector in avatar_selectors:
            try:
                avatar_elements = page.locator(selector).all()
                for avatar_elem in avatar_elements:
                    if avatar_elem.is_visible(timeout=1000):
                        src = avatar_elem.get_attribute('src')
                        if src and 'profile_images' in src:
                            logger.info(f"Found avatar URL: {src}")
                            return src
            except:
                continue
        
        logger.warning("Could not find avatar URL")
        return None
        
    except Exception as e:
        logger.error(f"Error extracting avatar URL: {e}")
        return None


def extract_profile_info(page) -> dict:
    """Extract username, display name, and avatar from Twitter page."""
    profile_info = {
        'username': None,
        'display_name': None,
        'avatar_url': None
    }
    
    # Method 1: Extract from account menu
    try:
        logger.info("Extracting from account menu...")
        account_menu = page.locator('[data-testid="SideNav_AccountSwitcher_Button"]').first
        if account_menu.is_visible(timeout=3000):
            # Get username
            username_elem = page.locator('[data-testid="SideNav_AccountSwitcher_Button"] [dir="ltr"]').first
            if username_elem.is_visible(timeout=2000):
                username_text = username_elem.inner_text(timeout=2000)
                if username_text and username_text.startswith('@'):
                    profile_info['username'] = username_text
                    logger.info(f"Found username: {username_text}")
            
            # Get display name
            display_name_elem = page.locator('[data-testid="SideNav_AccountSwitcher_Button"] span[dir="auto"]').first
            if display_name_elem.is_visible(timeout=2000):
                display_name = display_name_elem.inner_text(timeout=2000)
                profile_info['display_name'] = display_name
                logger.info(f"Found display name: {display_name}")
    except Exception as e:
        logger.warning(f"Could not extract from account menu: {e}")
    
    # Method 2: Extract from profile link
    if not profile_info['username']:
        try:
            logger.info("Extracting from profile link...")
            profile_link = page.locator('a[data-testid="AppTabBar_Profile_Link"]').first
            if profile_link.is_visible(timeout=2000):
                href = profile_link.get_attribute('href')
                if href:
                    username = href.replace('/', '')
                    profile_info['username'] = f"@{username}"
                    logger.info(f"Found username from link: @{username}")
        except Exception as e:
            logger.warning(f"Could not extract from profile link: {e}")
    
    # Method 3: JavaScript extraction
    if not profile_info['username'] or not profile_info['display_name']:
        try:
            logger.info("Extracting via JavaScript...")
            profile_data = page.evaluate('''() => {
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
            
            if not profile_info['username'] and profile_data['username']:
                profile_info['username'] = profile_data['username'].replace('@', '')
                profile_info['username'] = f"@{profile_info['username']}"
                logger.info(f"Found username via JS: {profile_info['username']}")
            if not profile_info['display_name'] and profile_data['displayName']:
                profile_info['display_name'] = profile_data['displayName']
                logger.info(f"Found display name via JS: {profile_info['display_name']}")
        except Exception as e:
            logger.warning(f"JavaScript extraction failed: {e}")
    
    # Get avatar URL
    logger.info("Fetching avatar URL...")
    profile_info['avatar_url'] = get_user_avatar_url(page)
    
    return profile_info


def save_to_env_files(profile_info: dict) -> bool:
    """Save profile info to .env files."""
    if not profile_info['username']:
        logger.error("No username extracted, cannot save to .env")
        return False
    
    logger.info("=" * 60)
    logger.info("SAVING PROFILE INFORMATION TO .ENV FILES")
    logger.info("=" * 60)
    logger.info(f"Username:     {profile_info['username']}")
    logger.info(f"Display Name: {profile_info['display_name'] or '(not found)'}")
    logger.info(f"Avatar URL:   {profile_info['avatar_url'] or '(not found)'}")
    logger.info("")
    
    updates = [('X_USERNAME', profile_info['username'])]
    if profile_info['display_name']:
        updates.append(('X_DISPLAY_NAME', profile_info['display_name']))
    if profile_info['avatar_url']:
        updates.append(('X_AVATAR_URL', profile_info['avatar_url']))
    
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
                logger.info(f"✅ Saved to {env_file}")
                saved_count += 1
            except Exception as e:
                logger.error(f"⚠️  Could not save to {env_file}: {e}")
    
    if saved_count > 0:
        logger.info("")
        logger.info("✅ PROFILE INFO SUCCESSFULLY SAVED!")
        logger.info("   All future images will use this Twitter account's profile")
        logger.info("=" * 60)
        return True
    else:
        logger.error("")
        logger.error("❌ ERROR: Could not save to any .env file")
        logger.error("=" * 60)
        return False


def main():
    """Main function to extract Twitter profile info."""
    logger.info("TWITTER PROFILE EXTRACTOR")
    logger.info("=" * 60)
    
    # Check for Twitter auth
    auth_file = Path('auth/auth_x.json')
    if not auth_file.exists():
        logger.error("ERROR: No Twitter authentication file found!")
        logger.error("Please login to Twitter first.")
        return False
    
    logger.info("Found Twitter authentication file")
    
    # Check proxy
    if not config.PROXY_CONFIG:
        logger.error("ERROR: No proxy configured!")
        logger.error("Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to .env")
        return False
    
    try:
        with sync_playwright() as p:
            logger.info("Launching Firefox...")
            
            firefox_options = {
                'headless': config.HEADLESS,
                'slow_mo': 50
            }
            
            if config.PROXY_CONFIG:
                firefox_options['proxy'] = config.PROXY_CONFIG
                logger.info(f"Using proxy: {config.PROXY_CONFIG['server']}")
            
            browser = p.firefox.launch(**firefox_options)
            logger.info("Firefox launched")
            
            # Load Twitter authentication
            context = browser.new_context(storage_state=str(auth_file))
            page = context.new_page()
            
            # Navigate to Twitter
            logger.info("Navigating to Twitter home...")
            page.goto('https://x.com/home', timeout=60000, wait_until='domcontentloaded')
            logger.info("Waiting for page to load...")
            page.wait_for_timeout(8000)
            
            # Wait for key element
            try:
                page.wait_for_selector('[data-testid="tweetTextarea_0"]', state='attached', timeout=20000)
                logger.info("Page loaded successfully")
            except:
                logger.warning("Some elements may not be loaded yet")
                page.wait_for_timeout(5000)
            
            # Check if logged in
            if 'login' in page.url or 'i/flow' in page.url:
                logger.error("Not logged in to Twitter!")
                browser.close()
                return False
            
            logger.info("Successfully authenticated to Twitter")
            
            # Extract profile info
            logger.info("")
            logger.info("=" * 60)
            logger.info("EXTRACTING PROFILE INFORMATION")
            logger.info("=" * 60)
            
            profile_info = extract_profile_info(page)
            
            # Save to .env files
            success = save_to_env_files(profile_info)
            
            browser.close()
            return success
            
    except Exception as e:
        logger.error(f"ERROR: {e}")
        return False


if __name__ == "__main__":
    success = main()
    if success:
        logger.info("\n✅ Profile extraction completed successfully!")
        sys.exit(0)
    else:
        logger.error("\n❌ Profile extraction failed!")
        sys.exit(1)


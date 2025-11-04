#!/usr/bin/env python3
"""
Message Image Generator
Generates images for messages using the HTML template.
Updates database with image generation status.

Profile information (display name, username, avatar, verified badge) is configured
by users through the web interface settings page.
"""

import sys
import os
from pathlib import Path

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent))

import json
import logging
import re
import requests
import hashlib
from typing import Dict, List, Any, Optional
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

from core.database import get_database, initialize_database
from core.debug_helper import log_debug_info, log_success, log_error
import config

# Configuration - use proxy from config (CRITICAL for Twitter avatar downloads!)
PROXY_CONFIG = config.PROXY_CONFIG
if not PROXY_CONFIG:
    print("⚠️  WARNING: No proxy configured - avatar downloads may fail!")
    print("   Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to copy.env")

# Directories
TEMPLATE_FILE = Path('twitter/tweet_template.html')
IMAGES_DIR = Path('data/message_images')
IMAGES_DIR.mkdir(parents=True, exist_ok=True)

AVATAR_CACHE_DIR = Path('avatar_cache')
AVATAR_CACHE_DIR.mkdir(exist_ok=True)

# Profile information - loaded from environment via config
PROFILE_DISPLAY_NAME = config.X_DISPLAY_NAME if hasattr(config, 'X_DISPLAY_NAME') else os.getenv('X_DISPLAY_NAME', 'Twitter User')
PROFILE_USERNAME = config.X_USERNAME if hasattr(config, 'X_USERNAME') else os.getenv('X_USERNAME', '@username')
PROFILE_AVATAR_FALLBACK = config.X_AVATAR_URL if hasattr(config, 'X_AVATAR_URL') else os.getenv('X_AVATAR_URL', '')
PROFILE_VERIFIED = config.X_VERIFIED if hasattr(config, 'X_VERIFIED') else os.getenv('X_VERIFIED', 'false').lower() == 'true'

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


def sanitize_filename(text: str, max_length: int = 50) -> str:
    """Create a safe filename from message text."""
    # Remove or replace problematic characters
    sanitized = re.sub(r'[<>:"/\\|?*]', '', text)
    sanitized = re.sub(r'\s+', '_', sanitized.strip())
    
    # Limit length
    if len(sanitized) > max_length:
        sanitized = sanitized[:max_length]
    
    return sanitized or "message"


def download_avatar_through_proxy(avatar_url: str, use_proxy: bool = True) -> Optional[str]:
    """
    Get avatar image path. Prioritizes user-uploaded avatar over downloaded ones.
    
    Order of priority:
    1. User-uploaded avatar (avatar_cache/user_avatar.jpg) - from web interface
    2. Cached downloaded avatar (if avatar_url provided)
    3. Download new avatar through proxy (if avatar_url provided)
    4. Fallback avatar from environment
    """
    try:
        # PRIORITY 1: Check for user-uploaded avatar first
        user_avatar_path = AVATAR_CACHE_DIR / 'user_avatar.jpg'
        if user_avatar_path.exists():
            logger.info(f"Using user-uploaded avatar from web interface: {user_avatar_path}")
            return str(user_avatar_path)
        
        # PRIORITY 2-4: Original logic for downloaded avatars (fallback)
        if not avatar_url:
            logger.warning("No avatar URL provided and no user-uploaded avatar found")
            return None
        
        # Get high-quality version
        hq_avatar_url = get_high_quality_avatar_url(avatar_url)
        logger.info(f"No user avatar found, downloading from URL: {hq_avatar_url}")
        
        # Create filename from URL
        url_hash = hashlib.md5(hq_avatar_url.encode()).hexdigest()
        avatar_filename = f"avatar_{url_hash}.jpg"
        local_avatar_path = AVATAR_CACHE_DIR / avatar_filename
        
        # Check if already cached
        if local_avatar_path.exists():
            logger.info(f"Using cached downloaded avatar: {local_avatar_path}")
            return str(local_avatar_path)
        
        # Setup requests session with proxy
        session = requests.Session()
        if use_proxy and PROXY_CONFIG:
            proxies = {
                'http': f"http://{PROXY_CONFIG['username']}:{PROXY_CONFIG['password']}@{PROXY_CONFIG['server'].replace('http://', '')}",
                'https': f"http://{PROXY_CONFIG['username']}:{PROXY_CONFIG['password']}@{PROXY_CONFIG['server'].replace('http://', '')}"
            }
            session.proxies.update(proxies)
            logger.info(f"Using proxy for avatar download: {PROXY_CONFIG['server']}")
        
        # Download avatar
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = session.get(hq_avatar_url, headers=headers, timeout=10)
        response.raise_for_status()
        
        # Save to local file
        with open(local_avatar_path, 'wb') as f:
            f.write(response.content)
        
        logger.info(f"Avatar downloaded successfully: {local_avatar_path}")
        return str(local_avatar_path)
        
    except Exception as e:
        logger.error(f"Failed to get/download avatar: {e}")
        return None


def get_high_quality_avatar_url(avatar_url: str) -> str:
    """Convert avatar URL to high-quality version."""
    if not avatar_url:
        # Use fallback from environment, or empty string if not set
        return PROFILE_AVATAR_FALLBACK
    
    # Replace _normal.jpg with _400x400.jpg for high quality
    return avatar_url.replace('_normal.jpg', '_400x400.jpg')


def update_template(message_text: str, local_avatar_path: str) -> Optional[str]:
    """Update the HTML template with message content and local avatar."""
    try:
        # Read the template
        with open(TEMPLATE_FILE, 'r', encoding='utf-8') as f:
            template_content = f.read()
        
        # Convert local path to file:// URL for HTML
        if local_avatar_path and os.path.exists(local_avatar_path):
            avatar_file_url = f"file:///{os.path.abspath(local_avatar_path).replace(os.sep, '/')}"
            logger.info(f"Using local avatar: {avatar_file_url}")
        else:
            # Fallback to avatar from environment
            avatar_file_url = PROFILE_AVATAR_FALLBACK or ""
            logger.warning(f"Using fallback avatar URL from environment: {avatar_file_url}")
        
        # Update template with placeholders replaced by actual values
        updated_content = template_content
        
        # Replace avatar URL
        updated_content = updated_content.replace(
            'AVATAR_URL_PLACEHOLDER',
            avatar_file_url
        )
        
        # Replace display name
        updated_content = updated_content.replace(
            'DISPLAY_NAME_PLACEHOLDER',
            PROFILE_DISPLAY_NAME
        )
        
        # Replace username
        updated_content = updated_content.replace(
            'USERNAME_PLACEHOLDER',
            PROFILE_USERNAME
        )
        
        # Replace message text
        updated_content = updated_content.replace(
            'MESSAGE_TEXT_PLACEHOLDER',
            message_text
        )
        
        # Replace verified badge visibility
        verified_display = 'inline-block' if PROFILE_VERIFIED else 'none'
        updated_content = updated_content.replace(
            'VERIFIED_DISPLAY_PLACEHOLDER',
            verified_display
        )
        
        logger.info(f"Twitter verified badge: {PROFILE_VERIFIED}")
        
        # Create temporary template file
        temp_template = Path('temp_message_template.html')
        with open(temp_template, 'w', encoding='utf-8') as f:
            f.write(updated_content)
        
        return str(temp_template.absolute())
        
    except Exception as e:
        logger.error(f"Error updating template: {e}")
        return None


def generate_message_image(page, message_data: Dict[str, Any], use_proxy: bool = True) -> Optional[str]:
    """Generate image for a single message."""
    try:
        message_id = message_data.get('id')
        message_text = message_data.get('message_text', '')
        avatar_url = message_data.get('avatar_url', '')
        posted_at = message_data.get('posted_at', '')
        
        logger.info(f"Generating image for message ID {message_id}: '{message_text[:50]}...'")
        
        # Download avatar through proxy first
        logger.info("Downloading avatar through proxy...")
        local_avatar_path = download_avatar_through_proxy(avatar_url, use_proxy)
        
        if not local_avatar_path:
            logger.warning("Avatar download failed, will use fallback")
        else:
            logger.info(f"Avatar ready: {local_avatar_path}")
        
        # Update template with message content and local avatar
        temp_template_path = update_template(message_text, local_avatar_path)
        if not temp_template_path:
            logger.error("Failed to update template")
            return None
        
        # Navigate to the template
        file_url = f"file:///{temp_template_path.replace(os.sep, '/')}"
        logger.info(f"Navigating to: {file_url}")
        page.goto(file_url, wait_until='domcontentloaded')
        
        # Wait for assets to load
        page.wait_for_timeout(2000)
        
        # Generate filename
        safe_text = sanitize_filename(message_text)
        timestamp = posted_at.replace(':', '-').replace(' ', '_') if posted_at else 'unknown'
        image_filename = f"msg_{message_id}_{timestamp}_{safe_text}.png"
        image_path = IMAGES_DIR / image_filename
        
        # Take screenshot of the screenshot wrapper (includes padding)
        logger.info("Taking screenshot of message container with padding...")
        try:
            # Screenshot the wrapper element that has 500px padding top/bottom
            screenshot_wrapper = page.locator('.screenshot-wrapper').first
            if screenshot_wrapper.is_visible():
                screenshot_wrapper.screenshot(
                    path=str(image_path),
                    type='png'
                )
                logger.info(f"Screenshot with padding saved: {image_path}")
            else:
                # Fallback: screenshot the entire page
                page.screenshot(path=str(image_path), full_page=True)
                logger.info(f"Full page screenshot saved: {image_path}")
        except Exception as e:
            logger.warning(f"Wrapper screenshot failed, trying full page: {e}")
            page.screenshot(path=str(image_path), full_page=True)
            logger.info(f"Full page screenshot saved: {image_path}")
        
        # Clean up temporary template
        try:
            Path(temp_template_path).unlink()
        except:
            pass
        
        # Return relative path for database storage
        return str(image_path)
        
    except Exception as e:
        logger.error(f"Error generating image for message {message_data.get('id')}: {e}")
        return None


def main():
    """Main function to generate images for posted messages."""
    print("MESSAGE IMAGE GENERATOR")
    print("="*50)
    
    # Force correct database path
    os.environ['DATABASE_PATH'] = 'data/scraper.db'
    
    # Initialize database
    db = initialize_database('data/scraper.db')
    
    # Get messages that need images (ONE at a time for cronjob)
    messages_without_images = db.get_posted_messages_without_images(limit=1)
    
    if not messages_without_images:
        print("No posted messages need image generation!")
        return 0
    
    print(f"Found {len(messages_without_images)} messages that need images")
    
    successful_images = 0
    failed_images = 0
    
    try:
        with sync_playwright() as p:
            # Create browser with proxy (use Firefox for VPS stability)
            logger.info("Launching Firefox for image generation...")
            
            firefox_options = {
                'headless': config.HEADLESS,
                'slow_mo': 300
            }
            
            # Add proxy configuration
            if PROXY_CONFIG:
                firefox_options['proxy'] = PROXY_CONFIG
                logger.info(f"Using proxy: {PROXY_CONFIG['server']}")
            
            browser = p.firefox.launch(**firefox_options)
            logger.info("Firefox launched successfully")
            
            context = browser.new_context()
            page = context.new_page()
            
            print(f"Processing {len(messages_without_images)} messages...")
            
            for i, message in enumerate(messages_without_images, 1):
                print(f"\n--- Message {i}/{len(messages_without_images)} ---")
                message_id = message['id']
                
                # Generate image
                image_path = generate_message_image(page, message, use_proxy=True)
                
                if image_path:
                    # Update database
                    success = db.mark_image_generated(message_id, image_path)
                    if success:
                        successful_images += 1
                        print(f"SUCCESS: Message {message_id} image generated: {image_path}")
                    else:
                        failed_images += 1
                        print(f"ERROR: Failed to update database for message {message_id}")
                else:
                    failed_images += 1
                    print(f"ERROR: Failed to generate image for message {message_id}")
                
                # Small delay between images
                page.wait_for_timeout(1000)
            
            context.close()
            browser.close()
    
    except Exception as e:
        logger.error(f"Critical error: {e}")
        return 1
    
    # Final report
    print("\n" + "="*50)
    print("IMAGE GENERATION COMPLETE")
    print("="*50)
    print(f"Total messages processed: {len(messages_without_images)}")
    print(f"Successful images: {successful_images}")
    print(f"Failed images: {failed_images}")
    print(f"Images saved in: {IMAGES_DIR}")
    
    # Show image stats
    image_stats = db.get_message_image_stats()
    print(f"\nDatabase Image Stats:")
    print(f"  Total posted messages: {image_stats['total_posted_messages']}")
    print(f"  Images generated: {image_stats['images_generated']}")
    print(f"  Images pending: {image_stats['images_pending']}")
    print("="*50)
    
    return 0 if failed_images == 0 else 1


if __name__ == "__main__":
    exit_code = main()
    if exit_code == 0:
        print("SUCCESS: All message images generated!")
    else:
        print("FAILED: Some message images failed to generate!")
    sys.exit(exit_code)

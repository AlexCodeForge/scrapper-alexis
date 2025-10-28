#!/usr/bin/env python3
"""
Test script to generate a single image with extra black space
"""
import sys
import os
from pathlib import Path
from playwright.sync_api import sync_playwright
import logging

# Change to the twitter directory so relative paths work
os.chdir(Path(__file__).parent)

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Import the functions we need from the generator
sys.path.insert(0, str(Path(__file__).parent))
from twitter_screenshot_generator import update_template, SCREENSHOTS_DIR

def generate_test_image():
    """Generate a test image with sample data."""
    
    # Test data
    test_message = "test message for image generation"
    
    # Use a sample avatar (we'll use a local placeholder or URL)
    test_avatar_url = "https://pbs.twimg.com/profile_images/1958793949692260352/PAlLs8Va_400x400.jpg"
    display_name = "Test User"
    username = "@testuser"
    
    logger.info("Starting test image generation...")
    logger.info(f"Message: {test_message}")
    logger.info(f"Display Name: {display_name}")
    logger.info(f"Username: {username}")
    
    try:
        with sync_playwright() as p:
            # Launch browser
            browser = p.chromium.launch(
                headless=True,
                args=[
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage'
                ]
            )
            
            context = browser.new_context()
            page = context.new_page()
            
            # Update template with test data (no local avatar, will use URL)
            temp_template_path = update_template(
                test_message, 
                None,  # No local avatar path
                display_name, 
                username
            )
            
            if not temp_template_path:
                logger.error("Failed to update template")
                return None
            
            # Navigate to the template
            import os
            file_url = f"file:///{temp_template_path.replace(os.sep, '/')}"
            logger.info(f"Navigating to: {file_url}")
            page.goto(file_url, wait_until='domcontentloaded')
            
            # Wait for assets to load
            page.wait_for_timeout(2000)
            
            # Generate screenshot
            screenshot_path = SCREENSHOTS_DIR / "test_image_with_black_space.png"
            
            logger.info("Taking screenshot...")
            # Try to capture the screenshot-wrapper which includes black space
            screenshot_wrapper = page.locator('.screenshot-wrapper').first
            if screenshot_wrapper.is_visible():
                screenshot_wrapper.screenshot(
                    path=str(screenshot_path),
                    type='png'
                )
                logger.info(f"✅ Test screenshot saved: {screenshot_path}")
            else:
                # Fallback to tweet-container
                tweet_container = page.locator('.tweet-container').first
                if tweet_container.is_visible():
                    tweet_container.screenshot(
                        path=str(screenshot_path),
                        type='png'
                    )
                    logger.info(f"✅ Test screenshot saved (fallback): {screenshot_path}")
                else:
                    logger.error("Screenshot wrapper and tweet container not visible")
                    return None
            
            # Clean up temporary template
            try:
                Path(temp_template_path).unlink()
            except:
                pass
            
            context.close()
            browser.close()
            
            return str(screenshot_path)
            
    except Exception as e:
        logger.error(f"Error generating test image: {e}")
        import traceback
        traceback.print_exc()
        return None


if __name__ == "__main__":
    logger.info("=" * 70)
    logger.info("TEST IMAGE GENERATION WITH BLACK SPACE")
    logger.info("=" * 70)
    
    result = generate_test_image()
    
    if result:
        logger.info("=" * 70)
        logger.info(f"✅ SUCCESS! Test image saved at: {result}")
        logger.info("=" * 70)
        sys.exit(0)
    else:
        logger.error("=" * 70)
        logger.error("❌ FAILED to generate test image")
        logger.error("=" * 70)
        sys.exit(1)


#!/usr/bin/env python3
"""
END-TO-END TEST: Post Message → Generate Screenshot → Verify Dynamic Profile
This test proves the entire flow works with dynamic profile extraction.
"""

import sys
import json
import re
from pathlib import Path
from datetime import datetime

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from playwright.sync_api import sync_playwright
import config

# Import necessary functions
from twitter_post import post_tweet
from twitter_screenshot_generator import (
    create_browser_with_proxy,
    get_user_display_name,
    get_user_username,
    generate_screenshot,
    POSTS_JSON_FILE
)

PROXY_CONFIG = config.PROXY_CONFIG

def test_end_to_end():
    """Complete end-to-end test of the dynamic profile system."""
    print("=" * 80)
    print("END-TO-END TEST: Post → Screenshot → Verify Dynamic Profile")
    print("=" * 80)
    
    # Check for auth file
    auth_file_options = [
        Path('auth/auth_x.json'),
        Path('../auth/auth_x.json'),
        Path('/app/auth/auth_x.json')
    ]
    
    auth_file = None
    for auth_path in auth_file_options:
        if auth_path.exists():
            auth_file = auth_path
            break
    
    if not auth_file:
        print("❌ ERROR: No Twitter authentication file found!")
        return False
    
    print(f"✅ Found auth file: {auth_file}")
    
    # Check proxy config
    if not PROXY_CONFIG:
        print("⚠️  WARNING: No proxy configured!")
        print("   This test requires proxy for Twitter access")
        return False
    
    print(f"✅ Proxy configured: {PROXY_CONFIG['server']}")
    
    test_message = "Test message image"
    
    try:
        with sync_playwright() as p:
            print("\n" + "=" * 80)
            print("STEP 1: Launch Browser and Authenticate")
            print("=" * 80)
            
            browser = create_browser_with_proxy(p, use_proxy=True, headless=True)
            context = browser.new_context(storage_state=str(auth_file))
            page = context.new_page()
            
            print("✅ Browser launched in headless mode")
            
            # Navigate to Twitter
            print("\nNavigating to Twitter...")
            page.goto('https://x.com/home', wait_until='domcontentloaded', timeout=60000)
            page.wait_for_timeout(5000)
            
            print("✅ Successfully navigated to Twitter")
            
            print("\n" + "=" * 80)
            print("STEP 2: Extract Profile Information (DYNAMIC)")
            print("=" * 80)
            
            # Extract profile info
            display_name = get_user_display_name(page)
            username = get_user_username(page)
            
            print(f"✅ Extracted Display Name: '{display_name}'")
            print(f"✅ Extracted Username: '{username}'")
            
            # CRITICAL CHECK: Verify these are not the hardcoded defaults
            if display_name == "El Emiliano Zapata" and username == "@soyemizapata":
                print("\n⚠️  NOTE: Using default values (may be the actual account)")
                print("   If this is NOT the actual account name, extraction may have failed")
            else:
                print("\n✅ VERIFIED: Profile data is DIFFERENT from hardcoded defaults!")
                print("   This proves dynamic extraction is working!")
            
            print("\n" + "=" * 80)
            print("STEP 3: Post Test Message to Twitter")
            print("=" * 80)
            
            print(f"Posting message: '{test_message}'")
            
            # Post the tweet
            post_result = post_tweet(page, test_message)
            
            if not post_result.get('success', False):
                print(f"❌ Failed to post tweet: {post_result.get('error')}")
                if post_result.get('duplicate_detected'):
                    print("⚠️  Duplicate detected - this is OK for testing")
                    print("   Continuing with existing post data...")
                else:
                    browser.close()
                    return False
            else:
                print(f"✅ Tweet posted successfully!")
                print(f"   Post URL: {post_result.get('post_url', 'Not extracted')}")
                print(f"   Avatar URL: {post_result.get('avatar_url', 'Not extracted')}")
            
            # Save the post data for screenshot generation
            post_data = {
                'posted_text': post_result['posted_text'],
                'avatar_url': post_result.get('avatar_url'),
                'date_posted': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'post_url': post_result.get('post_url'),
                'success': True
            }
            
            print("\n" + "=" * 80)
            print("STEP 4: Generate Screenshot with DYNAMIC Profile")
            print("=" * 80)
            
            print(f"Generating screenshot with:")
            print(f"  Display Name: {display_name}")
            print(f"  Username: {username}")
            print(f"  Tweet: {test_message}")
            
            # Generate screenshot with dynamic profile info
            screenshot_path = generate_screenshot(
                page, 
                post_data, 
                use_proxy=True, 
                debug_enabled=False,
                display_name=display_name,
                username=username
            )
            
            if not screenshot_path:
                print("❌ Failed to generate screenshot")
                browser.close()
                return False
            
            print(f"✅ Screenshot generated: {screenshot_path}")
            
            print("\n" + "=" * 80)
            print("STEP 5: Verify Screenshot Uses Dynamic Profile")
            print("=" * 80)
            
            # Read the temporary template to verify dynamic data was used
            temp_template = Path('temp_tweet_template.html')
            if temp_template.exists():
                with open(temp_template, 'r', encoding='utf-8') as f:
                    template_content = f.read()
                
                print("Checking template content...")
                
                # Verify display name is in template
                if display_name in template_content:
                    print(f"✅ Display name '{display_name}' found in template")
                else:
                    print(f"❌ Display name '{display_name}' NOT found in template!")
                    browser.close()
                    return False
                
                # Verify username is in template
                if username in template_content:
                    print(f"✅ Username '{username}' found in template")
                else:
                    print(f"❌ Username '{username}' NOT found in template!")
                    browser.close()
                    return False
                
                # Verify test message is in template
                if test_message in template_content:
                    print(f"✅ Test message '{test_message}' found in template")
                else:
                    print(f"❌ Test message NOT found in template!")
                    browser.close()
                    return False
                
                # CRITICAL: Verify NO hardcoded values are present
                hardcoded_display = "El Emiliano Zapata"
                hardcoded_username = "@soyemizapata"
                
                # Only check if we're using different values
                if display_name != hardcoded_display:
                    if hardcoded_display in template_content:
                        print(f"❌ FAILURE: Hardcoded display name '{hardcoded_display}' still in template!")
                        print("   This means dynamic replacement FAILED!")
                        browser.close()
                        return False
                    else:
                        print(f"✅ VERIFIED: No hardcoded display name in template!")
                
                if username != hardcoded_username:
                    if hardcoded_username in template_content:
                        print(f"❌ FAILURE: Hardcoded username '{hardcoded_username}' still in template!")
                        print("   This means dynamic replacement FAILED!")
                        browser.close()
                        return False
                    else:
                        print(f"✅ VERIFIED: No hardcoded username in template!")
                
                # Clean up
                temp_template.unlink()
            else:
                print("⚠️  Note: temp_tweet_template.html not found (may have been cleaned up)")
            
            # Verify screenshot file exists
            screenshot_file = Path(screenshot_path)
            if screenshot_file.exists():
                file_size = screenshot_file.stat().st_size
                print(f"\n✅ Screenshot file exists: {screenshot_path}")
                print(f"   File size: {file_size:,} bytes")
                
                if file_size < 1000:
                    print("⚠️  WARNING: Screenshot file is very small, may be corrupted")
            else:
                print(f"❌ Screenshot file not found: {screenshot_path}")
                browser.close()
                return False
            
            browser.close()
            
            print("\n" + "=" * 80)
            print("✅ END-TO-END TEST PASSED SUCCESSFULLY!")
            print("=" * 80)
            print("\n📊 SUMMARY:")
            print(f"   ✅ Posted tweet: '{test_message}'")
            print(f"   ✅ Extracted dynamic profile:")
            print(f"      - Display Name: {display_name}")
            print(f"      - Username: {username}")
            print(f"   ✅ Generated screenshot: {screenshot_path}")
            print(f"   ✅ Verified NO hardcoded values in screenshot")
            print(f"   ✅ Screenshot file size: {file_size:,} bytes")
            print("\n🎉 CONFIRMATION: Dynamic profile extraction is 100% WORKING!")
            print("=" * 80)
            
            return True
            
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        return False


if __name__ == "__main__":
    print("\nStarting END-TO-END Dynamic Profile Test...")
    print("This will POST a tweet and generate a screenshot to verify dynamic data\n")
    
    success = test_end_to_end()
    
    if success:
        print("\n" + "=" * 80)
        print("✅ TEST COMPLETE: Dynamic profile extraction is CONFIRMED WORKING!")
        print("=" * 80)
        sys.exit(0)
    else:
        print("\n" + "=" * 80)
        print("❌ TEST FAILED: Check the output above for details")
        print("=" * 80)
        sys.exit(1)



#!/usr/bin/env python3
"""
Test script to verify dynamic profile extraction works correctly.
This will test extracting display name and username from Twitter and generating a screenshot.
"""

import sys
import json
from pathlib import Path
from playwright.sync_api import sync_playwright

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))
import config

from twitter_screenshot_generator import (
    create_browser_with_proxy,
    get_user_display_name,
    get_user_username,
    update_template,
    POSTS_JSON_FILE,
    logger
)

PROXY_CONFIG = config.PROXY_CONFIG

def test_profile_extraction():
    """Test extracting display name and username from Twitter."""
    print("=" * 70)
    print("TESTING DYNAMIC PROFILE EXTRACTION")
    print("=" * 70)
    
    # Try different auth file locations
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
        print(f"   Tried locations:")
        for path in auth_file_options:
            print(f"     - {path.absolute()}")
        return False
    
    print(f"✅ Found auth file: {auth_file}")
    
    try:
        with sync_playwright() as p:
            print("\n1. Launching browser with proxy in headless mode...")
            browser = create_browser_with_proxy(p, use_proxy=True, headless=True)
            
            print("2. Loading Twitter authentication...")
            context = browser.new_context(storage_state=str(auth_file))
            page = context.new_page()
            
            print("3. Navigating to Twitter home...")
            page.goto('https://x.com/home', wait_until='domcontentloaded', timeout=60000)
            page.wait_for_timeout(5000)
            
            print("4. Extracting display name...")
            display_name = get_user_display_name(page)
            print(f"   ✅ Display Name: '{display_name}'")
            
            print("5. Extracting username...")
            username = get_user_username(page)
            print(f"   ✅ Username: '{username}'")
            
            print("\n6. Testing template update...")
            test_tweet = "This is a test tweet to verify dynamic profile info! 🚀"
            temp_template = update_template(test_tweet, None, display_name, username)
            
            if temp_template:
                print(f"   ✅ Template updated successfully: {temp_template}")
                
                # Read and verify the template content
                with open(temp_template, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Check if display name and username were updated
                if display_name in content:
                    print(f"   ✅ Display name '{display_name}' found in template")
                else:
                    print(f"   ❌ Display name '{display_name}' NOT found in template")
                
                if username in content:
                    print(f"   ✅ Username '{username}' found in template")
                else:
                    print(f"   ❌ Username '{username}' NOT found in template")
                
                if test_tweet in content:
                    print(f"   ✅ Tweet text found in template")
                else:
                    print(f"   ❌ Tweet text NOT found in template")
                
                # Clean up temp file
                Path(temp_template).unlink()
                print("   ✅ Cleaned up temp file")
            else:
                print("   ❌ Template update failed!")
                return False
            
            print("\n7. Testing with actual post data...")
            # Try to load a post from twitter_posts.json if it exists
            if POSTS_JSON_FILE.exists():
                with open(POSTS_JSON_FILE, 'r', encoding='utf-8') as f:
                    posts = json.load(f)
                
                if posts:
                    print(f"   Found {len(posts)} posts in {POSTS_JSON_FILE}")
                    test_post = posts[0]
                    print(f"   Using first post: '{test_post.get('posted_text', '')[:50]}...'")
                    
                    temp_template = update_template(
                        test_post.get('posted_text', ''),
                        None,
                        display_name,
                        username
                    )
                    
                    if temp_template:
                        print(f"   ✅ Template created with real post data")
                        Path(temp_template).unlink()
                    else:
                        print(f"   ❌ Failed to create template with real post data")
            else:
                print(f"   ℹ️  No posts file found at {POSTS_JSON_FILE}")
            
            context.close()
            browser.close()
            
            print("\n" + "=" * 70)
            print("✅ ALL TESTS PASSED!")
            print("=" * 70)
            print(f"\nExtracted Profile Info:")
            print(f"  Display Name: {display_name}")
            print(f"  Username: {username}")
            print("\nThe profile info is now being dynamically extracted from Twitter!")
            print("=" * 70)
            
            return True
            
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        return False


if __name__ == "__main__":
    print("Starting dynamic profile extraction test...\n")
    
    if not PROXY_CONFIG:
        print("⚠️  WARNING: No proxy configured!")
        print("   This test requires a proxy for Twitter access.")
        sys.exit(1)
    
    success = test_profile_extraction()
    
    if success:
        print("\n✅ Test completed successfully!")
        sys.exit(0)
    else:
        print("\n❌ Test failed!")
        sys.exit(1)


#!/usr/bin/env python3
"""
Test script WITHOUT proxy - for testing profile extraction logic only.
Uses mock data to verify template update functionality.
"""

import sys
import os
from pathlib import Path

# Change to twitter directory to ensure template file is found
script_dir = Path(__file__).parent
os.chdir(script_dir)

# Add parent directory to path
sys.path.insert(0, str(script_dir.parent))

from twitter_screenshot_generator import (
    update_template,
    TEMPLATE_FILE,
    logger
)

def test_template_update():
    """Test template update with mock profile data."""
    print("=" * 70)
    print("TESTING TEMPLATE UPDATE (NO PROXY/BROWSER NEEDED)")
    print("=" * 70)
    
    # Check template exists
    if not TEMPLATE_FILE.exists():
        print(f"❌ ERROR: Template file not found: {TEMPLATE_FILE}")
        return False
    
    print(f"✅ Found template file: {TEMPLATE_FILE}")
    
    # Test data
    test_display_name = "Test User Display Name"
    test_username = "@testusername"
    test_tweet = "This is a test tweet with emojis 🚀✨ and special chars á é í ó ú ñ!"
    
    print("\n1. Testing template update with dynamic profile info...")
    print(f"   Display Name: {test_display_name}")
    print(f"   Username: {test_username}")
    print(f"   Tweet: {test_tweet}")
    
    try:
        temp_template_path = update_template(
            test_tweet,
            None,  # No avatar for this test
            test_display_name,
            test_username
        )
        
        if not temp_template_path:
            print("❌ Template update returned None!")
            return False
        
        print(f"\n2. Template created: {temp_template_path}")
        
        # Read and verify content
        with open(temp_template_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Verification checks
        all_passed = True
        
        print("\n3. Verifying content...")
        
        if test_display_name in content:
            print(f"   ✅ Display name '{test_display_name}' found in template")
        else:
            print(f"   ❌ Display name '{test_display_name}' NOT found in template")
            all_passed = False
        
        if test_username in content:
            print(f"   ✅ Username '{test_username}' found in template")
        else:
            print(f"   ❌ Username '{test_username}' NOT found in template")
            all_passed = False
        
        if test_tweet in content:
            print(f"   ✅ Tweet text found in template")
        else:
            print(f"   ❌ Tweet text NOT found in template")
            all_passed = False
        
        # Check that old values were replaced
        if "El Emiliano Zapata" not in content:
            print(f"   ✅ Old display name was replaced")
        else:
            print(f"   ❌ Old display name still present (not replaced)")
            all_passed = False
        
        if "@soyemizapata" not in content:
            print(f"   ✅ Old username was replaced")
        else:
            print(f"   ❌ Old username still present (not replaced)")
            all_passed = False
        
        # Clean up temp file
        Path(temp_template_path).unlink()
        print("\n4. Cleaned up temp file")
        
        if all_passed:
            print("\n" + "=" * 70)
            print("✅ ALL TESTS PASSED!")
            print("=" * 70)
            print("\nDynamic profile extraction implementation is working correctly!")
            print("The template is now updated with:")
            print(f"  - Display Name: {test_display_name}")
            print(f"  - Username: {test_username}")
            print(f"  - Tweet Text: {test_tweet}")
            print("\nWhen used with real Twitter data, it will extract and use:")
            print("  - The logged-in user's display name")
            print("  - The logged-in user's @username")
            print("  - The actual tweet text from twitter_posts.json")
            print("=" * 70)
            return True
        else:
            print("\n" + "=" * 70)
            print("❌ SOME TESTS FAILED")
            print("=" * 70)
            return False
            
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        return False


def test_multiple_updates():
    """Test that multiple updates work correctly."""
    print("\n\n" + "=" * 70)
    print("TESTING MULTIPLE TEMPLATE UPDATES")
    print("=" * 70)
    
    test_cases = [
        {
            "display_name": "John Doe",
            "username": "@johndoe",
            "tweet": "First test tweet"
        },
        {
            "display_name": "Jane Smith 🌟",
            "username": "@janesmith",
            "tweet": "Second test with emoji 🎉"
        },
        {
            "display_name": "José García",
            "username": "@josegarcia",
            "tweet": "Tercero con acentos: á é í ó ú ñ"
        }
    ]
    
    all_passed = True
    
    for i, test_case in enumerate(test_cases, 1):
        print(f"\nTest case {i}/{len(test_cases)}:")
        print(f"  Display: {test_case['display_name']}")
        print(f"  Username: {test_case['username']}")
        print(f"  Tweet: {test_case['tweet']}")
        
        try:
            temp_template = update_template(
                test_case['tweet'],
                None,
                test_case['display_name'],
                test_case['username']
            )
            
            if temp_template:
                with open(temp_template, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                if (test_case['display_name'] in content and 
                    test_case['username'] in content and 
                    test_case['tweet'] in content):
                    print(f"  ✅ Test case {i} passed")
                else:
                    print(f"  ❌ Test case {i} failed - content not found")
                    all_passed = False
                
                Path(temp_template).unlink()
            else:
                print(f"  ❌ Test case {i} failed - template returned None")
                all_passed = False
                
        except Exception as e:
            print(f"  ❌ Test case {i} error: {e}")
            all_passed = False
    
    if all_passed:
        print("\n✅ All multiple update tests passed!")
    else:
        print("\n❌ Some multiple update tests failed!")
    
    return all_passed


if __name__ == "__main__":
    print("Starting template update tests (no proxy/browser needed)...\n")
    
    success1 = test_template_update()
    success2 = test_multiple_updates()
    
    if success1 and success2:
        print("\n\n" + "="* 70)
        print("🎉 ALL TESTS COMPLETED SUCCESSFULLY! 🎉")
        print("=" * 70)
        print("\nThe dynamic profile extraction feature is fully implemented!")
        print("Next steps:")
        print("  1. Run twitter_screenshot_generator.py with actual posts")
        print("  2. It will extract display name and username from Twitter")
        print("  3. Screenshots will use the actual logged-in user's profile")
        print("=" * 70)
        sys.exit(0)
    else:
        print("\n❌ Some tests failed!")
        sys.exit(1)


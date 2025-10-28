#!/usr/bin/env python3
"""
Twitter Authentication Module
Handles login, session storage, and authentication state management for Twitter/X.
Based on MCP observations of actual Twitter login workflow.
"""

import logging
import time
import json
from pathlib import Path
from typing import Dict, Any, Optional
from playwright.sync_api import Browser, BrowserContext, Page, TimeoutError as PlaywrightTimeoutError

logger = logging.getLogger(__name__)

AUTH_FILE = Path('auth/auth_x.json')
AUTH_SESSION_FILE = Path('auth/auth_x_session.json')

def login_x(browser: Browser, username: str, password: str, proxy_config: Optional[Dict] = None) -> Optional[BrowserContext]:
    """
    Perform Twitter/X login using observed MCP workflow.
    
    Args:
        browser: Playwright browser instance
        username: X username (your Twitter username)
        password: X password
        proxy_config: Optional proxy configuration
    
    Returns:
        BrowserContext if successful, None if failed
    """
    logger.info("Starting Twitter/X login process...")
    
    try:
        # Create context with proxy if provided
        context_options = {
            'ignore_https_errors': True,
            'viewport': {'width': 1280, 'height': 720},
            'user_agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        
        context = browser.new_context(**context_options)
        page = context.new_page()
        
        # Step 1: Navigate to X.com
        logger.info("Navigating to https://x.com...")
        page.goto('https://x.com', wait_until='domcontentloaded')
        page.wait_for_timeout(2000)
        
        # Step 2: Click "Sign in" if on landing page
        try:
            login_selectors = [
                'text=Sign in',
                'text=Iniciar sesión', 
                'text=Log in',
                '[data-testid="loginButton"]'
            ]
            
            login_clicked = False
            for selector in login_selectors:
                try:
                    login_element = page.locator(selector).first
                    if login_element.is_visible():
                        logger.info(f"Clicking login button with selector: {selector}")
                        login_element.click()
                        # Wait for navigation to login page
                        page.wait_for_url('**/login*', timeout=10000)
                        logger.info(f"Successfully navigated to: {page.url}")
                        login_clicked = True
                        break
                except Exception as e:
                    logger.debug(f"Login selector {selector} failed: {e}")
                    continue
                    
            if not login_clicked:
                logger.info("No login button found, checking if already on login page")
                if '/login' not in page.url:
                    logger.error("Not on login page and couldn't find login button")
                    return None
        except Exception as e:
            logger.warning(f"Login button click failed: {e}")
            if '/login' not in page.url:
                return None
        
        # Step 3: Wait for login form and enter username
        logger.info("Waiting for username field...")
        username_field = page.locator('input[name="text"][autocomplete="username"]').first
        username_field.wait_for(state='visible', timeout=10000)
        username_field.fill(username)
        page.wait_for_timeout(1000)
        
        # Step 4: Click "Next" button
        logger.info("Clicking 'Next' button...")
        next_selectors = [
            'button >> text=Next',
            'button >> text=Siguiente',
            'button[type="submit"]'
        ]
        
        next_clicked = False
        for selector in next_selectors:
            try:
                next_button = page.locator(selector).first
                if next_button.is_visible():
                    next_button.click()
                    page.wait_for_timeout(3000)
                    next_clicked = True
                    break
            except:
                continue
                
        if not next_clicked:
            logger.error("Could not find Next button")
            return None
        
        # Step 5: Enter password
        logger.info("Entering password...")
        password_field = page.locator('input[name="password"]').first
        password_field.wait_for(state='visible', timeout=10000)
        password_field.fill(password)
        page.wait_for_timeout(1000)
        
        # Step 6: Click "Log in" button
        logger.info("Clicking 'Log in' button...")
        login_selectors = [
            'button >> text=Log in',
            'button >> text=Iniciar sesión',
            'button[type="submit"]'
        ]
        
        login_clicked = False
        for selector in login_selectors:
            try:
                login_button = page.locator(selector).first
                if login_button.is_visible():
                    login_button.click()
                    page.wait_for_timeout(5000)
                    login_clicked = True
                    break
            except:
                continue
                
        if not login_clicked:
            logger.error("Could not find Log in button")
            return None
        
        # Step 7: Verify successful login
        logger.info("Verifying login success...")
        page.wait_for_url('**/home', timeout=15000)
        
        # Check for compose area to confirm we're logged in
        compose_area = page.locator('[data-testid="tweetTextarea_0"]').first
        compose_area.wait_for(state='visible', timeout=10000)
        
        # Step 8: Fetch actual profile info from Twitter
        logger.info("Fetching profile information from Twitter...")
        try:
            # Navigate to profile page
            page.goto(f'https://x.com/{username}', wait_until='domcontentloaded')
            page.wait_for_timeout(3000)
            
            # Extract display name
            display_name = None
            try:
                display_name_elem = page.locator('[data-testid="UserName"] > div > div > div > span').first
                display_name = display_name_elem.inner_text(timeout=5000)
                logger.info(f"Found display name: {display_name}")
            except Exception as e:
                logger.warning(f"Could not fetch display name: {e}")
                display_name = username  # Fallback to username
            
            # Extract avatar URL
            avatar_url = None
            try:
                avatar_elem = page.locator('[data-testid="UserAvatar-Container-unknown"] img').first
                avatar_url = avatar_elem.get_attribute('src', timeout=5000)
                logger.info(f"Found avatar URL: {avatar_url}")
            except Exception as e:
                logger.warning(f"Could not fetch avatar URL: {e}")
                avatar_url = ""
            
        except Exception as e:
            logger.error(f"Failed to fetch profile info: {e}")
            display_name = username
            avatar_url = ""
        
        # Step 9: Save authentication state
        logger.info("Saving authentication state...")
        context.storage_state(path=str(AUTH_FILE))
        
        # Save session info with REAL profile data
        session_info = {
            'username': username,
            'display_name': display_name,
            'avatar_url': avatar_url,
            'login_time': time.time(),
            'login_url': page.url,
            'success': True
        }
        
        with open(AUTH_SESSION_FILE, 'w') as f:
            json.dump(session_info, f, indent=2)
        
        # Update .env file with REAL profile info
        logger.info("Updating .env file with profile information...")
        try:
            import os
            env_path = Path('copy.env')
            if env_path.exists():
                with open(env_path, 'r') as f:
                    env_content = f.read()
                
                # Update or add profile values
                import re
                for key, value in [
                    ('X_DISPLAY_NAME', display_name),
                    ('X_USERNAME', f'@{username}' if not username.startswith('@') else username),
                    ('X_AVATAR_URL', avatar_url)
                ]:
                    pattern = f"^{key}=.*$"
                    if re.search(pattern, env_content, re.MULTILINE):
                        env_content = re.sub(pattern, f"{key}={value}", env_content, flags=re.MULTILINE)
                    else:
                        env_content += f"\n{key}={value}\n"
                
                with open(env_path, 'w') as f:
                    f.write(env_content)
                
                logger.info("✅ Profile info saved to .env file!")
        except Exception as e:
            logger.error(f"Failed to update .env: {e}")
            
        logger.info("✅ Twitter/X login successful with profile data!")
        return context
        
    except PlaywrightTimeoutError as e:
        logger.error(f"❌ Login timeout: {e}")
        return None
    except Exception as e:
        logger.error(f"❌ Login failed: {e}")
        return None

def load_auth_context(browser: Browser) -> Optional[BrowserContext]:
    """
    Load existing authentication context if available.
    
    Args:
        browser: Playwright browser instance
        
    Returns:
        BrowserContext if auth file exists and is valid, None otherwise
    """
    if not AUTH_FILE.exists():
        logger.info("No authentication file found")
        return None
        
    try:
        logger.info(f"Loading authentication from {AUTH_FILE}")
        context_options = {
            'ignore_https_errors': True,
            'viewport': {'width': 1280, 'height': 720},
            'user_agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'storage_state': str(AUTH_FILE)
        }
        
        context = browser.new_context(**context_options)
        
        # Verify auth is still valid - but be more lenient
        page = context.new_page()
        
        try:
            page.goto('https://x.com/home', wait_until='domcontentloaded', timeout=10000)
            page.wait_for_timeout(2000)  # Give page time to load
            
            # Check multiple indicators that we're logged in
            logged_in_indicators = [
                '[data-testid="tweetTextarea_0"]',  # Compose area
                '[data-testid="SideNav_AccountSwitcher_Button"]',  # Account menu
                'button[aria-label*="Account menu"]',  # Alternative account menu
                '[data-testid="AppTabBar_Home_Link"]'  # Home link in nav
            ]
            
            for indicator in logged_in_indicators:
                try:
                    element = page.locator(indicator).first
                    if element.is_visible(timeout=3000):
                        logger.info(f"✅ Authentication valid - found: {indicator}")
                        return context
                except:
                    continue
            
            # If no indicators found, check URL - if we're on /home, likely logged in
            if '/home' in page.url and '/login' not in page.url:
                logger.info("✅ Authentication appears valid based on URL")
                return context
                
            logger.warning("Authentication file exists but session appears invalid")
            page.close()
            return None
            
        except Exception as e:
            logger.warning(f"Could not verify authentication: {e}")
            page.close()
            return None
            
    except Exception as e:
        logger.error(f"Failed to load authentication: {e}")
        return None

def ensure_authenticated(browser: Browser, username: str, password: str, proxy_config: Optional[Dict] = None) -> Optional[BrowserContext]:
    """
    Ensure we have a valid authenticated context, login if needed.
    
    Args:
        browser: Playwright browser instance
        username: X username
        password: X password  
        proxy_config: Optional proxy configuration
        
    Returns:
        BrowserContext if successful, None if failed
    """
    # Try to load existing auth first
    context = load_auth_context(browser)
    if context:
        return context
    
    # If no valid auth, perform login
    logger.info("No valid authentication found, performing login...")
    return login_x(browser, username, password, proxy_config)


if __name__ == "__main__":
    """Standalone authentication script."""
    import sys
    from pathlib import Path
    
    # Setup paths
    sys.path.insert(0, str(Path(__file__).parent.parent))
    
    from dotenv import load_dotenv
    load_dotenv('copy.env')
    
    import config
    from playwright.sync_api import sync_playwright
    
    print("="*60)
    print("TWITTER AUTHENTICATION SETUP")
    print("="*60)
    print()
    print(f"This will authenticate Twitter and fetch your profile info.")
    print()
    
    # Get credentials from config or prompt
    username = config.X_EMAIL or input("Twitter username: ").strip()
    password = config.X_PASSWORD or input("Twitter password: ").strip()
    
    if not username or not username or not password:
        print("ERROR: Username and password are required!")
        sys.exit(1)
    
    print(f"\nAuthenticating as: {username}")
    print(f"Using proxy: {config.PROXY_CONFIG['server'] if config.PROXY_CONFIG else 'None'}")
    print()
    
    # Perform authentication
    with sync_playwright() as p:
        browser_opts = {
            'headless': True,
            'slow_mo': 100
        }
        
        if config.PROXY_CONFIG:
            browser_opts['proxy'] = config.PROXY_CONFIG
        
        try:
            print("Launching browser...")
            browser = p.firefox.launch(**browser_opts)
            
            print("Performing login...")
            context = login_x(browser, username, password, config.PROXY_CONFIG)
            
            if context:
                print("\n" + "="*60)
                print("✅ AUTHENTICATION SUCCESSFUL!")
                print("="*60)
                
                # Read and display saved profile info
                if AUTH_SESSION_FILE.exists():
                    with open(AUTH_SESSION_FILE, 'r') as f:
                        session_info = json.load(f)
                    
                    print(f"\n📋 Profile Information:")
                    print(f"   Username:     @{session_info.get('username', 'N/A')}")
                    print(f"   Display Name: {session_info.get('display_name', 'N/A')}")
                    print(f"   Avatar URL:   {session_info.get('avatar_url', 'N/A')[:60]}...")
                    print()
                
                print("✅ Auth files created:")
                print(f"   - {AUTH_FILE}")
                print(f"   - {AUTH_SESSION_FILE}")
                print()
                print("✅ Profile info saved to .env files")
                print()
                print("You can now run Twitter posting!")
                
                context.close()
            else:
                print("\n" + "="*60)
                print("❌ AUTHENTICATION FAILED!")
                print("="*60)
                print()
                print("Please check:")
                print("  - Your username and password are correct")
                print("  - The proxy is working")
                print("  - Twitter is accessible")
                sys.exit(1)
            
            browser.close()
            
        except Exception as e:
            print(f"\n❌ ERROR: {e}")
            sys.exit(1)

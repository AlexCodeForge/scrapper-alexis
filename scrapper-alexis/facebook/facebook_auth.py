"""
Facebook authentication and session management.

This module handles Facebook login with fallback selectors,
session state persistence, and CAPTCHA/2FA manual intervention.
"""

import logging
import random
import json
import re
from pathlib import Path
from playwright.sync_api import Page, BrowserContext, TimeoutError as PlaywrightTimeoutError

import config
from core.exceptions import LoginError
from utils.selector_strategies import EMAIL_SELECTORS, PASSWORD_SELECTORS, try_selectors
from core.debug_helper import take_debug_screenshot, log_page_state, log_debug_info

logger = logging.getLogger(__name__)


def check_auth_state() -> bool:
    """
    Check if Facebook authentication state file exists.
    
    Returns:
        True if auth state exists, False otherwise
    """
    auth_file = Path('auth/auth_facebook.json')
    exists = auth_file.exists()
    
    if exists:
        logger.info("[OK] Facebook auth state file found")
    else:
        logger.info("[INFO] No Facebook auth state file found")
    
    return exists


def verify_logged_in(page: Page) -> bool:
    """
    Verify if user is actually logged into Facebook by checking for redirect.
    
    This function navigates to facebook.com/login and checks if we're redirected
    to home.php (indicating we're logged in) or stay at login page (not logged in).
    Also handles intermediate pages with "Continue" buttons.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        True if logged in, False otherwise
    """
    try:
        logger.info("=== Verifying Facebook Login Status ===")
        logger.info("Navigating to https://www.facebook.com/login to check for redirect...")
        
        # Navigate to login page with retry logic for network/proxy issues
        # If logged in, Facebook will redirect to home.php or main feed
        max_retries = 3
        for attempt in range(max_retries):
            try:
                page.goto('https://www.facebook.com/login', wait_until='load', timeout=30000)
                break  # Success, exit retry loop
            except Exception as nav_error:
                error_msg = str(nav_error)
                
                # Check for proxy/network errors
                if 'NS_ERROR_UNKNOWN_HOST' in error_msg or 'net::ERR_' in error_msg:
                    logger.error(f"❌ Network/Proxy error on attempt {attempt + 1}/{max_retries}: {error_msg}")
                    if attempt < max_retries - 1:
                        wait_time = 2 ** attempt  # Exponential backoff: 1s, 2s, 4s
                        logger.info(f"Retrying in {wait_time}s...")
                        page.wait_for_timeout(wait_time * 1000)
                        continue
                    else:
                        logger.error("❌ All retry attempts failed - proxy may be dead or blocked")
                        raise
                
                # Check for navigation race condition (redirects happening too fast)
                elif 'interrupted by another navigation' in error_msg:
                    logger.warning(f"⚠️ Navigation race condition on attempt {attempt + 1}/{max_retries}")
                    if attempt < max_retries - 1:
                        logger.info("Retrying with shorter timeout...")
                        page.wait_for_timeout(1000)
                        continue
                    else:
                        # If this keeps happening, it might actually mean we're logged in (rapid redirects)
                        logger.info("Multiple rapid redirects detected - might indicate active session")
                        break
                else:
                    # Unknown error, propagate it
                    raise
        
        # Wait for potential redirects
        logger.info("Waiting 3 seconds for potential redirect...")
        page.wait_for_timeout(3000)
        
        current_url = page.url
        logger.info(f"Final URL after navigation: {current_url}")
        
        # DEBUG: Take screenshot and log page state
        take_debug_screenshot(page, "verify_login_status", "verification", "After navigating to /login")
        log_page_state(page, "Login verification check", "verification")
        
        # CRITICAL: Check for "Continue" button on intermediate pages
        # Sometimes Facebook shows a confirmation page with "Continue" button
        logger.info("Checking for intermediate 'Continue' button...")
        try:
            continue_buttons = [
                'button:has-text("Continue")',
                'button:has-text("Continuar")',
                'div[role="button"]:has-text("Continue")',
                'div[role="button"]:has-text("Continuar")',
                'a:has-text("Continue")',
                'a:has-text("Continuar")',
            ]
            
            for btn_selector in continue_buttons:
                try:
                    btn = page.locator(btn_selector).first
                    if btn.is_visible(timeout=1000):
                        logger.info(f"[ACTION] Found 'Continue' button: {btn_selector}")
                        logger.info("Clicking 'Continue' to proceed...")
                        btn.click(timeout=3000)
                        page.wait_for_timeout(3000)  # Wait for redirect after clicking
                        
                        # Update current URL after clicking Continue
                        current_url = page.url
                        logger.info(f"[OK] ✅ Clicked 'Continue' - new URL: {current_url}")
                        take_debug_screenshot(page, "after_continue_click", "verification", "After clicking Continue")
                        break
                except:
                    continue
        except Exception as e:
            logger.debug(f"Continue button check: {e}")
        
        # Re-check URL after potential Continue click
        current_url = page.url
        
        # CRITICAL CHECK: If redirected to home.php or away from /login, we're logged in
        if 'home.php' in current_url:
            logger.info(f"[OK] ✅ Logged in - redirected to home.php: {current_url}")
            return True
        
        # Check if we were redirected away from /login to the main feed
        if '/login' not in current_url:
            logger.info(f"[OK] ✅ Logged in - redirected away from login page to: {current_url}")
            return True
        
        # Still at /login page - check if login form is visible
        if '/login' in current_url:
            logger.warning(f"[FAIL] ❌ Not logged in - still at login page: {current_url}")
            
            # Double-check by looking for login form
            try:
                email_input = page.locator('input[name="email"]').first
                if email_input.is_visible(timeout=2000):
                    logger.info("[CONFIRM] Login form is visible - definitely not logged in")
                    return False
            except:
                pass
        
        # Default: not logged in
        logger.warning("[INFO] Login verification inconclusive - assuming not logged in")
        return False
        
    except Exception as e:
        logger.error(f"Login verification failed with error: {e}")
        logger.info("[INFO] Assuming not logged in due to verification error")
        return False


def wait_for_manual_intervention(
    page: Page, 
    message: str = "Manual intervention required", 
    timeout: int = 300000
) -> bool:
    """
    Pause execution for manual CAPTCHA/2FA resolution.
    
    Args:
        page: Playwright Page instance
        message: Message to display
        timeout: Milliseconds to wait (default 5 minutes)
        
    Returns:
        True when intervention complete
    """
    logger.warning(f"[WARN] {message}")
    logger.warning(f"Waiting up to {timeout/1000} seconds for manual resolution...")
    logger.warning("Press Ctrl+C when done to continue...")
    
    try:
        page.wait_for_timeout(timeout)
    except KeyboardInterrupt:
        logger.info("[OK] Manual intervention completed by user")
    
    return True


def login_facebook_with_retry(page: Page, max_retries: int = 3, wait_time: int = 50) -> bool:
    """
    Perform Facebook login with retry logic and verification.
    
    This function wraps the login attempt in a retry loop, verifying after each attempt.
    
    Args:
        page: Playwright Page instance
        max_retries: Number of login attempts (default: 3)
        wait_time: Seconds to wait between attempts (default: 50)
        
    Returns:
        True if successful
        
    Raises:
        LoginError: If login fails after all retries
    """
    logger.info("="*70)
    logger.info(f"🔄 FACEBOOK LOGIN WITH RETRY (Max attempts: {max_retries}, Wait: {wait_time}s)")
    logger.info("="*70)
    
    for attempt in range(1, max_retries + 1):
        logger.info("")
        logger.info("="*70)
        logger.info(f"📍 LOGIN ATTEMPT {attempt}/{max_retries}")
        logger.info("="*70)
        
        try:
            # Attempt login
            login_result = login_facebook(page)
            
            if login_result:
                logger.info(f"[OK] ✅ Login function returned success on attempt {attempt}")
                
                # CRITICAL: Verify login actually worked by checking URL/page state
                logger.info(f"🔍 Verifying login success (attempt {attempt})...")
                logger.info(f"⏳ Waiting {wait_time} seconds for session to stabilize...")
                page.wait_for_timeout(wait_time * 1000)
                
                # Check current URL
                current_url = page.url
                logger.info(f"📍 Current URL after {wait_time}s wait: {current_url}")
                take_debug_screenshot(page, f"retry_{attempt}_after_{wait_time}s_wait", "login", f"Attempt {attempt}: After {wait_time}s wait")
                
                # CRITICAL: Check if Facebook is showing a checkpoint/verification screen
                logger.info(f"🔍 Checking for Facebook checkpoint/verification screen...")
                
                if 'checkpoint' in current_url.lower() or 'verify' in current_url.lower() or 'confirm' in current_url.lower():
                    logger.warning("="*70)
                    logger.warning("⚠️ FACEBOOK CHECKPOINT DETECTED")
                    logger.warning("="*70)
                    logger.warning(f"Current URL: {current_url}")
                    logger.warning("")
                    logger.warning("Facebook is asking for additional verification.")
                    logger.warning("This could be:")
                    logger.warning("  - Phone number verification")
                    logger.warning("  - Email verification")
                    logger.warning("  - Security checkpoint")
                    logger.warning("  - 'Is this you?' confirmation")
                    logger.warning("")
                    logger.warning("⏳ PAUSING FOR 2 MINUTES FOR MANUAL INTERVENTION")
                    logger.warning("Please complete the verification in the browser window.")
                    logger.warning("")
                    take_debug_screenshot(page, f"checkpoint_detected_attempt_{attempt}", "verification", "Checkpoint screen detected")
                    
                    # Wait 2 minutes for manual intervention
                    logger.info("⏳ Waiting 120 seconds (2 minutes) for you to complete verification...")
                    page.wait_for_timeout(120000)  # 2 minutes
                    
                    # Take screenshot after manual intervention time
                    current_url = page.url
                    logger.info(f"📍 URL after 2-minute wait: {current_url}")
                    take_debug_screenshot(page, f"after_manual_intervention_attempt_{attempt}", "verification", "After 2-minute manual wait")
                    
                    # Check if still on checkpoint
                    if 'checkpoint' in current_url.lower() or 'verify' in current_url.lower():
                        logger.warning("⚠️ Still on checkpoint page after 2 minutes")
                        logger.warning("Verification may not be complete or may require more time")
                        logger.warning("Continuing to verification check anyway...")
                    else:
                        logger.info("✅ URL changed after manual intervention - proceeding to verify login")
                
                # Verify we're actually logged in
                logger.info(f"🔍 Running full login verification check (attempt {attempt})...")
                is_logged_in = verify_logged_in(page)
                
                if is_logged_in:
                    logger.info("="*70)
                    logger.info(f"🎉 ✅ LOGIN SUCCESSFUL ON ATTEMPT {attempt}/{max_retries}")
                    logger.info("="*70)
                    return True
                else:
                    logger.warning("="*70)
                    logger.warning(f"⚠️ ATTEMPT {attempt} FAILED: Login function succeeded but verification failed")
                    logger.warning("="*70)
                    logger.warning("Login button was clicked but Facebook didn't accept the login")
                    logger.warning("Possible causes:")
                    logger.warning("  - Wrong credentials")
                    logger.warning("  - Proxy is blocked/flagged by Facebook")
                    logger.warning("  - Rate limiting (too many attempts)")
                    logger.warning("  - Account requires verification/2FA")
                    logger.warning("")
                    
                    if attempt < max_retries:
                        logger.info(f"⏳ Waiting {wait_time} seconds before retry {attempt + 1}...")
                        page.wait_for_timeout(wait_time * 1000)
                        logger.info(f"🔄 Retrying login (attempt {attempt + 1})...")
                    continue
            else:
                logger.warning(f"⚠️ ATTEMPT {attempt}: Login function returned False")
                if attempt < max_retries:
                    logger.info(f"⏳ Waiting {wait_time} seconds before retry...")
                    page.wait_for_timeout(wait_time * 1000)
                continue
                
        except Exception as login_error:
            logger.error(f"❌ ATTEMPT {attempt} ERROR: {str(login_error)[:200]}")
            take_debug_screenshot(page, f"retry_{attempt}_error", "errors", f"Attempt {attempt} failed with error")
            
            if attempt < max_retries:
                logger.info(f"⏳ Waiting {wait_time} seconds before retry {attempt + 1}...")
                page.wait_for_timeout(wait_time * 1000)
                logger.info(f"🔄 Retrying after error...")
                continue
            else:
                # Last attempt failed
                logger.error("="*70)
                logger.error(f"❌ ALL {max_retries} LOGIN ATTEMPTS FAILED")
                logger.error("="*70)
                raise
    
    # All retries exhausted
    logger.error("="*70)
    logger.error(f"❌ LOGIN FAILED AFTER {max_retries} ATTEMPTS")
    logger.error("="*70)
    raise LoginError(f"Login failed after {max_retries} attempts with {wait_time}s waits")


def login_facebook(page: Page) -> bool:
    """
    Perform Facebook login with fallback selectors and human-like behavior.
    
    NOTE: This function is wrapped by login_facebook_with_retry() for production use.
    
    Args:
        page: Playwright Page instance
        
    Returns:
        True if successful
        
    Raises:
        LoginError: If login fails
    """
    try:
        logger.info("=== Starting Facebook Login ===")
        
        # Navigate to login page with robust retry logic
        logger.info("Navigating to Facebook login page...")
        
        max_retries = 3
        navigation_success = False
        
        for attempt in range(max_retries):
            try:
                logger.info(f"Navigation attempt {attempt + 1}/{max_retries}...")
                page.goto(
                    'https://www.facebook.com/login',
                    wait_until='domcontentloaded',
                    timeout=config.NAVIGATION_TIMEOUT
                )
                navigation_success = True
                logger.info("✅ Navigation successful")
                break  # Success, exit retry loop
                
            except Exception as nav_error:
                error_msg = str(nav_error)
                
                # Check for proxy/network errors (dead proxy, DNS resolution failure)
                if 'NS_ERROR_UNKNOWN_HOST' in error_msg or 'net::ERR_PROXY_CONNECTION_FAILED' in error_msg or 'net::ERR_NAME_NOT_RESOLVED' in error_msg:
                    logger.error(f"❌ Network/Proxy error on attempt {attempt + 1}/{max_retries}")
                    logger.error(f"   Error: {error_msg[:200]}")
                    
                    if attempt < max_retries - 1:
                        wait_time = 2 ** attempt  # Exponential backoff: 1s, 2s, 4s
                        logger.info(f"⏳ Waiting {wait_time}s before retry...")
                        page.wait_for_timeout(wait_time * 1000)
                        continue
                    else:
                        logger.error("❌ All retry attempts exhausted")
                        logger.error("💡 Possible causes:")
                        logger.error("   - Proxy server is down or unreachable")
                        logger.error("   - Proxy IP is blocked by Facebook")
                        logger.error("   - Network connectivity issue")
                        logger.error("💡 Suggestions:")
                        logger.error("   - Try a different proxy server")
                        logger.error("   - Test login without proxy")
                        logger.error("   - Check if proxy requires authentication")
                        raise LoginError(f"Proxy/Network error: {error_msg[:200]}")
                
                # Check for navigation race condition (Facebook redirecting too fast)
                elif 'interrupted by another navigation' in error_msg:
                    logger.warning(f"⚠️ Navigation race condition on attempt {attempt + 1}/{max_retries}")
                    logger.info("   This can happen when Facebook redirects very quickly")
                    
                    if attempt < max_retries - 1:
                        logger.info("⏳ Retrying with different wait strategy...")
                        page.wait_for_timeout(1500)
                        
                        # Try with 'commit' wait strategy (less strict)
                        try:
                            page.goto(
                                'https://www.facebook.com/login',
                                wait_until='commit',  # Less strict - just wait for navigation to commit
                                timeout=15000
                            )
                            navigation_success = True
                            logger.info("✅ Navigation successful with 'commit' strategy")
                            break
                        except Exception as retry_error:
                            logger.warning(f"Retry with 'commit' failed: {retry_error}")
                            continue
                    else:
                        logger.error("❌ Navigation keeps getting interrupted")
                        raise LoginError(f"Navigation race condition: {error_msg[:200]}")
                
                # Unknown error
                else:
                    logger.error(f"❌ Unexpected navigation error: {error_msg[:300]}")
                    if attempt < max_retries - 1:
                        logger.info("⏳ Retrying...")
                        page.wait_for_timeout(2000)
                        continue
                    else:
                        raise LoginError(f"Navigation failed: {error_msg[:200]}")
        
        if not navigation_success:
            raise LoginError("Failed to navigate to login page after all retries")
        
        # Wait for page to fully load
        page.wait_for_timeout(2000)
        
        # DEBUG: Screenshot before starting login
        take_debug_screenshot(page, "01_before_login", "login", "Login page loaded")
        log_page_state(page, "Before cookie check", "login")
        
        # IMPORTANT: Close cookie policy dialog FIRST before doing anything else
        logger.info("Checking for cookie policy dialog...")
        try:
            # Look for cookie dialog and try to close it
            cookie_dialog_selectors = [
                'div[data-testid="cookie-policy-manage-dialog"]',
                'div[data-testid="cookie-policy-dialog"]',
                'div[role="dialog"][aria-label*="ookie"]',
                'div[role="dialog"]',  # Generic dialog
            ]
            
            cookie_dialog_found = False
            for selector in cookie_dialog_selectors:
                try:
                    dialog = page.locator(selector).first
                    if dialog.is_visible(timeout=2000):
                        logger.info(f"Cookie dialog detected: {selector}")
                        cookie_dialog_found = True
                        
                        # CRITICAL: Try multiple button strategies to close the modal
                        # Facebook shows different cookie consent options, we need to try all
                        accept_buttons = [
                            # Strategy 1: "Allow essential and optional cookies" (full consent)
                            'button:has-text("Allow essential and optional cookies")',
                            'button:has-text("Permitir cookies esenciales y opcionales")',
                            # Strategy 2: "Decline optional cookies" (minimal consent - your suggestion!)
                            'button:has-text("Decline optional cookies")',
                            'button:has-text("Rechazar cookies opcionales")',
                            # Strategy 3: Generic "Allow" buttons
                            'div[role="button"]:has-text("Allow essential and optional")',
                            'div[role="button"]:has-text("Decline optional")',
                            # Strategy 4: Old-style selectors (fallback)
                            'button[data-cookiebanner="accept_button"]',
                            'button:has-text("Allow all")',
                            'button:has-text("Permitir todas")',
                            'button:has-text("Accept all")',
                            'button:has-text("Aceptar todas")',
                            'div[role="button"]:has-text("Allow")',
                            'div[role="button"]:has-text("Permitir")',
                        ]
                        
                        button_clicked = False
                        for btn_selector in accept_buttons:
                            try:
                                btn = page.locator(btn_selector).first
                                if btn.is_visible(timeout=1000):
                                    logger.info(f"Found cookie button: {btn_selector}")
                                    btn.click(force=True)
                                    logger.info(f"[OK] Clicked cookie button: {btn_selector}")
                                    button_clicked = True
                                    page.wait_for_timeout(2000)  # Wait for modal to close
                                    take_debug_screenshot(page, "01b_cookie_closed", "login", f"Cookie modal closed via: {btn_selector}")
                                    
                                    # VERIFY the modal actually closed
                                    try:
                                        if dialog.is_visible(timeout=1000):
                                            logger.warning(f"⚠️ Cookie modal still visible after clicking {btn_selector}")
                                            button_clicked = False
                                            continue
                                        else:
                                            logger.info("[OK] ✅ Cookie modal successfully closed and no longer visible")
                                    except:
                                        logger.info("[OK] ✅ Cookie modal is gone")
                                    break
                            except Exception as btn_err:
                                logger.debug(f"Cookie button '{btn_selector}' not found or clickable: {btn_err}")
                                continue
                        
                        if not button_clicked:
                            logger.warning("⚠️ Cookie dialog detected but no button could be clicked - may cause issues")
                        break
                except Exception as dialog_err:
                    logger.debug(f"Cookie dialog selector '{selector}' failed: {dialog_err}")
                    continue
            
            if not cookie_dialog_found:
                logger.info("No cookie dialog detected - proceeding with login")
        except Exception as e:
            logger.debug(f"Cookie dialog check failed (not critical): {e}")
        
        # DEBUG: Screenshot after cookie handling
        take_debug_screenshot(page, "02_ready_for_login", "login", "Ready to enter credentials")
        
        # Email input with fallback selectors
        logger.info("Entering email address...")
        email_input = try_selectors(page, EMAIL_SELECTORS, timeout=10000)
        
        if not email_input:
            raise LoginError("Could not find email input field with any selector")
        
        email_input.fill(config.FACEBOOK_EMAIL)
        logger.debug(f"Email filled: {config.FACEBOOK_EMAIL}")
        log_debug_info(f"Email entered: {config.FACEBOOK_EMAIL}")
        
        # DEBUG: Screenshot after email
        take_debug_screenshot(page, "03_email_entered", "login", f"Email: {config.FACEBOOK_EMAIL}")
        
        # Human-like delay
        delay = random.randint(500, 1500)
        logger.debug(f"Waiting {delay}ms (human-like delay)...")
        page.wait_for_timeout(delay)
        
        # Password input with fallback selectors
        logger.info("Entering password...")
        password_input = try_selectors(page, PASSWORD_SELECTORS, timeout=5000)
        
        if not password_input:
            raise LoginError("Could not find password input field with any selector")
        
        password_input.fill(config.FACEBOOK_PASSWORD)
        logger.debug("Password filled (hidden)")
        log_debug_info("Password entered successfully")
        
        # DEBUG: Screenshot after password
        take_debug_screenshot(page, "04_password_entered", "login", "Credentials filled")
        
        # Human-like delay
        delay = random.randint(500, 1500)
        logger.debug(f"Waiting {delay}ms (human-like delay)...")
        page.wait_for_timeout(delay)
        
        # DEBUG: Screenshot before clicking login
        take_debug_screenshot(page, "05_before_login_click", "login", "About to click login button")
        log_page_state(page, "Before clicking login button", "login")
        
        # CRITICAL: Check AGAIN for cookie modal right before clicking login button
        # The modal might reappear or still be there
        logger.info("Final check for cookie policy modal before login click...")
        try:
            cookie_modal = page.locator('div[data-testid="cookie-policy-manage-dialog"]').first
            if cookie_modal.is_visible(timeout=1000):
                logger.warning("⚠️ Cookie modal STILL VISIBLE before login click - attempting to close again...")
                
                # Try the most common buttons that actually close the modal
                close_buttons = [
                    'button:has-text("Decline optional cookies")',
                    'button:has-text("Allow essential and optional cookies")',
                    'div[role="button"]:has-text("Decline optional")',
                    'div[role="button"]:has-text("Allow essential and optional")',
                ]
                
                modal_closed = False
                for btn_selector in close_buttons:
                    try:
                        btn = page.locator(btn_selector).first
                        if btn.is_visible(timeout=500):
                            logger.info(f"Attempting to close modal with: {btn_selector}")
                            btn.click(force=True)
                            page.wait_for_timeout(2000)
                            
                            # Verify closure
                            if not cookie_modal.is_visible(timeout=1000):
                                logger.info(f"[OK] ✅ Modal successfully closed with: {btn_selector}")
                                modal_closed = True
                                take_debug_screenshot(page, "05b_modal_finally_closed", "login", "Modal closed before login")
                                break
                    except:
                        continue
                
                if not modal_closed:
                    logger.error("❌ CRITICAL: Cookie modal could not be closed - login click may fail")
                    take_debug_screenshot(page, "05c_modal_still_blocking", "login", "Modal still blocking!")
            else:
                logger.info("[OK] ✅ No cookie modal blocking - safe to click login")
        except Exception as e:
            logger.debug(f"Final cookie check: {e}")
        
        # Click login button with multiple strategies
        logger.info("Clicking login button...")
        
        # Try role-based selector first (most reliable)
        try:
            login_button = page.get_by_role(
                "button", 
                name=re.compile("log in|sign in", re.IGNORECASE)
            )
            # Fallback to name-based selector
            login_button = login_button.or_(page.locator('button[name="login"]'))
            login_button.first.click(force=True)
            logger.debug("Login button clicked (role-based)")
        except Exception as e:
            logger.warning(f"Role-based button click failed: {e}, trying direct selector")
            page.locator('button[name="login"]').first.click(force=True)
            logger.debug("Login button clicked (direct selector)")
        
        # DEBUG: Screenshot immediately after clicking login
        page.wait_for_timeout(2000)  # Wait a bit for initial response
        take_debug_screenshot(page, "06_after_login_click", "login", "Just after clicking login button")
        log_page_state(page, "Immediately after login click", "login")
        
        # Wait for authentication to complete
        logger.info("Waiting for authentication to complete...")
        
        try:
            page.wait_for_load_state('networkidle', timeout=config.LOGIN_TIMEOUT)
            logger.info("[OK] Network idle - login appears successful")
            
        except PlaywrightTimeoutError:
            # Possible CAPTCHA or 2FA
            logger.warning("Login taking longer than expected - checking for CAPTCHA/2FA...")
            
            # Check if we're still on login page (indicates CAPTCHA/2FA)
            current_url = page.url
            if 'login' in current_url or 'checkpoint' in current_url:
                wait_for_manual_intervention(
                    page, 
                    "CAPTCHA or 2FA detected - please complete manually"
                )
            else:
                logger.info("Login completed (URL changed)")
        
        # CRITICAL: Check for 2FA/CAPTCHA/checkpoint pages after login
        current_url = page.url
        
        # Check for "Continue" button first (common after login)
        logger.info("Checking for post-login 'Continue' button...")
        try:
            continue_btn = page.locator('button:has-text("Continue")').first
            if continue_btn.is_visible(timeout=2000):
                logger.info("[ACTION] Found 'Continue' button after login - clicking...")
                continue_btn.click(timeout=3000)
                page.wait_for_timeout(3000)
                current_url = page.url
                logger.info(f"[OK] Clicked 'Continue' - new URL: {current_url}")
        except:
            logger.debug("No 'Continue' button found after login")
        
        # Now check for verification challenges
        if 'two_step_verification' in current_url or 'checkpoint' in current_url:
            # Determine if it's 2FA or CAPTCHA
            page_content = page.content().lower()
            
            if 'captcha' in page_content or 'security check' in page_content:
                logger.warning("="*70)
                logger.warning("🤖 CAPTCHA DETECTED")
                logger.warning("="*70)
                logger.warning("Facebook is showing a CAPTCHA challenge.")
                logger.warning(f"Current URL: {current_url}")
                logger.warning("")
                logger.warning("This usually happens after multiple login attempts.")
                logger.warning("SOLUTION: Wait 15-30 minutes before trying again.")
                logger.warning("")
                logger.error("❌ Cannot proceed with CAPTCHA - please wait and retry")
                raise LoginError("CAPTCHA detected - too many login attempts. Wait 15-30 minutes.")
            
            elif 'two_step' in current_url or 'authentication' in page_content:
                logger.warning("="*70)
                logger.warning("🔐 TWO-FACTOR AUTHENTICATION (2FA) REQUIRED")
                logger.warning("="*70)
                logger.warning("Facebook account has 2FA enabled.")
                logger.warning(f"Current URL: {current_url}")
                logger.warning("")
                logger.warning("OPTIONS:")
                logger.warning("1. Complete 2FA manually within 5 minutes")
                logger.warning("2. Use a valid saved session (auth_facebook.json)")
                logger.warning("")
                logger.warning("Waiting for 2FA completion...")
                
                # Wait for user to complete 2FA (5 minutes timeout)
                try:
                    page.wait_for_function(
                        "() => !window.location.href.includes('two_step_verification') && !window.location.href.includes('checkpoint')",
                        timeout=300000  # 5 minutes
                    )
                    logger.info("[OK] ✅ 2FA completed - proceeding with login")
                    page.wait_for_timeout(3000)  # Extra stabilization time
                except PlaywrightTimeoutError:
                    logger.error("❌ 2FA timeout - manual intervention required")
                    logger.error("Please complete 2FA and re-run the scraper")
                    raise LoginError("2FA timeout - user did not complete verification")
            else:
                # Generic checkpoint
                logger.warning("⚠️ Facebook checkpoint detected - manual review may be needed")
                logger.warning(f"URL: {current_url}")
                wait_for_manual_intervention(page, "Facebook checkpoint detected")
        
        # Give Facebook extra time to set up session cookies
        logger.info("Waiting for session to stabilize (3s)...")
        page.wait_for_timeout(3000)
        
        # DEBUG: Screenshot after login completes
        take_debug_screenshot(page, "07_login_complete", "login", f"Final URL: {page.url}")
        log_page_state(page, "After login completion", "login")
        log_debug_info(f"Login completed - final URL: {page.url}", category="login")
        
        logger.info("[OK] Facebook login successful")
        return True
        
    except PlaywrightTimeoutError as e:
        logger.error(f"Facebook login timeout: {e}")
        raise LoginError(f"Login timed out: {e}")
        
    except Exception as e:
        logger.error(f"Facebook login failed: {e}")
        raise LoginError(f"Login error: {e}")


def save_auth_state(context: BrowserContext, page: Page) -> None:
    """
    Save Facebook authentication state with IndexedDB and session storage.
    
    Args:
        context: Playwright BrowserContext
        page: Playwright Page instance
    """
    try:
        logger.info("Saving Facebook authentication state...")
        
        # Save storage state with IndexedDB support (most important)
        context.storage_state(path='auth/auth_facebook.json')
        logger.info("[OK] Saved storage state to auth/auth_facebook.json")
        
        # Try to save session storage separately (optional, may fail during navigation)
        try:
            session_storage = page.evaluate("() => JSON.stringify(sessionStorage)")
            
            with open('auth/auth_facebook_session.json', 'w') as f:
                json.dump({'session_storage': session_storage}, f, indent=2)
            
            logger.info("[OK] Saved session storage to auth/auth_facebook_session.json")
        except Exception as session_err:
            logger.warning(f"Could not save session storage (not critical): {session_err}")
        
        logger.info("[OK] Authentication state saved successfully")
        
    except Exception as e:
        logger.error(f"Failed to save auth state: {e}")
        raise LoginError(f"Could not save authentication state: {e}")


def restore_session_storage(page: Page) -> None:
    """
    Restore session storage from saved file.
    
    Args:
        page: Playwright Page instance
    """
    try:
        session_file = Path('auth/auth_facebook_session.json')
        
        if not session_file.exists():
            logger.debug("No session storage file found")
            return
        
        with open(session_file, 'r') as f:
            data = json.load(f)
            session_storage = data.get('session_storage', '{}')
        
        # Inject session storage into page
        page.evaluate(f"""(storage => {{
            if (window.location.hostname.includes('facebook')) {{
                const entries = JSON.parse(storage);
                for (const [key, value] of Object.entries(entries)) {{
                    window.sessionStorage.setItem(key, value);
                }}
            }}
        }})('{session_storage}')""")
        
        logger.info("[OK] Restored session storage")
        
    except Exception as e:
        logger.warning(f"Could not restore session storage: {e}")



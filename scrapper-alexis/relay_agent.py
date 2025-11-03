"""Playwright Social Content Relay Agent - Main Script."""
import logging
import os
from pathlib import Path
from datetime import datetime
from playwright.sync_api import sync_playwright
from dotenv import load_dotenv

# Load environment variables from .env
# Note: We removed the dependency on copy.env as part of the migration to database-based configuration
load_dotenv('.env')

import config
from core.exceptions import (
    RelayAgentError, 
    ConfigurationError,
    LoginError,
    NavigationError,
    ExtractionError
)
from utils.browser_config import create_browser_context
from facebook.facebook_auth import check_auth_state, verify_logged_in, login_facebook_with_retry, save_auth_state
from facebook.facebook_extractor import navigate_to_message, extract_message_text_with_database
from core.debug_helper import DebugSession
from core.database import get_database, initialize_database
from core.profile_manager import get_profile_manager
from core.message_deduplicator import get_message_deduplicator

# Create logs directory
logs_dir = Path('logs')
logs_dir.mkdir(exist_ok=True)

# Configure logging with UTF-8 encoding
logging.basicConfig(
    level=getattr(logging, config.LOG_LEVEL),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(
            logs_dir / f'relay_agent_{datetime.now().strftime("%Y%m%d")}.log',
            encoding='utf-8'
        ),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)


def main():
    """Main execution function with multi-profile support."""
    # Disable accessibility features at OS level for Firefox (fixes DBus timeout)
    os.environ['NO_AT_BRIDGE'] = '1'
    os.environ['ACCESSIBILITY_ENABLED'] = '0'
    
    # Initialize debug session for this run (creates per-run folder)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    debug_session = DebugSession(f"multi_profile_scraper_{timestamp}")
    
    try:
        # Initialize database
        logger.info("Initializing database...")
        db = initialize_database()
        
        # Initialize profile manager
        logger.info("Initializing profile manager...")
        profile_manager = get_profile_manager()
        
        # Sync profiles from environment variables to database
        logger.info("Syncing profiles from environment variables...")
        profiles = profile_manager.sync_profiles_to_database()
        
        if not profiles:
            logger.error("No profiles found! Please check your FACEBOOK_PROFILES environment variable.")
            return
        
        logger.info("="*70)
        logger.info("PLAYWRIGHT SOCIAL CONTENT RELAY AGENT - MULTI-PROFILE VERSION")
        logger.info("="*70)
        logger.info(f"Found {len(profiles)} profiles to scrape:")
        
        for i, profile in enumerate(profiles, 1):
            logger.info(f"  {i}. {profile['username']} - {profile['url']}")
        
        # Get Facebook credentials
        facebook_creds = profile_manager.get_facebook_credentials()
        if not facebook_creds:
            logger.error("Facebook credentials not found in credenciales.txt")
            raise ConfigurationError("Missing Facebook credentials")
        
        logger.info(f"Using Facebook account: {facebook_creds['username']}")
        
        with sync_playwright() as p:
            # Launch browser with crash-resistant options for VPS
            logger.info("\n=== Browser Launch ===")
            
            # Use Firefox on Linux VPS (required for scraping to work properly)
            use_firefox = True  # CRITICAL: Must use Firefox for proper functionality!
            
            if use_firefox:
                logger.info("Launching Firefox (better stability for complex pages)...")
                
                # Build Firefox launch options with server-optimized preferences
                firefox_options = {
                    'headless': config.HEADLESS,
                    'slow_mo': config.SLOW_MO if not config.HEADLESS else 0,
                    'firefox_user_prefs': {
                        # Disable accessibility features (fixes DBus errors on servers)
                        'accessibility.force_disabled': 1,
                        'accessibility.handler.enabled': False,
                        'accessibility.support.url': '',
                        # Disable features that can hang Firefox
                        'datareporting.policy.dataSubmissionEnabled': False,
                        'datareporting.healthreport.uploadEnabled': False,
                        'toolkit.telemetry.enabled': False,
                        'toolkit.telemetry.unified': False,
                        'toolkit.telemetry.archive.enabled': False,
                        # Performance optimizations for server
                        'browser.cache.disk.enable': False,
                        'browser.cache.memory.enable': True,
                        'browser.cache.offline.enable': False,
                        'network.http.use-cache': False,
                        # Disable unnecessary features
                        'extensions.pocket.enabled': False,
                        'browser.safebrowsing.downloads.enabled': False,
                        'browser.safebrowsing.malware.enabled': False,
                        'browser.safebrowsing.phishing.enabled': False,
                        # Media settings
                        'media.autoplay.default': 5,
                        'media.autoplay.blocking_policy': 2,
                    }
                }
                
                # Add proxy configuration if available (CRITICAL!)
                if config.PROXY_CONFIG:
                    firefox_options['proxy'] = config.PROXY_CONFIG
                    logger.info(f"🔒 Using proxy: {config.PROXY_CONFIG['server']}")
                else:
                    logger.warning("⚠️  No proxy configured - this may cause issues!")
                
                browser = p.firefox.launch(**firefox_options)
                logger.info(f"Firefox launched (headless={config.HEADLESS}) with server-optimized preferences")
            else:
                logger.info("Launching Chromium...")
                
                # Build launch options with crashpad disabled for server stability
                launch_options = {
                    'headless': config.HEADLESS,
                    'slow_mo': config.SLOW_MO if not config.HEADLESS else 0,
                    'args': [
                        '--disable-dev-shm-usage',  # Overcome limited resource problems on VPS
                        '--no-sandbox',  # Required for Docker/VPS environments
                        '--disable-setuid-sandbox',  # Required for Docker/VPS
                        '--disable-blink-features=AutomationControlled',  # Anti-detection
                        '--disable-features=IsolateOrigins',  # Reduce memory usage
                        '--js-flags="--max-old-space-size=512"',  # Limit JS heap to 512MB
                        '--disable-extensions',  # Disable extensions
                        '--disable-background-networking',  # Reduce network overhead
                        '--disable-default-apps',  # Disable default apps
                        '--disable-sync',  # Disable sync
                        '--metrics-recording-only',  # Minimal metrics
                        '--mute-audio',  # No audio processing
                        '--disable-crash-reporter',  # Disable crash reporter (fixes crashpad handler errors)
                        '--crash-dumps-dir=/tmp',  # Set crash dumps directory
                    ]
                }
                
                # Add proxy configuration if available (CRITICAL!)
                if config.PROXY_CONFIG:
                    launch_options['proxy'] = config.PROXY_CONFIG
                    logger.info(f"🔒 Using proxy: {config.PROXY_CONFIG['server']}")
                else:
                    logger.warning("⚠️  No proxy configured - this may cause issues!")
                
                browser = p.chromium.launch(**launch_options)
                logger.info(f"Chromium launched (headless={config.HEADLESS}) with crash-resistant flags")
            
            try:
                # ============================================================
                # PHASE 1: Multi-Profile Facebook Content Acquisition
                # ============================================================
                logger.info("\n" + "="*70)
                logger.info("PHASE 1: MULTI-PROFILE FACEBOOK CONTENT ACQUISITION")
                logger.info("="*70)
                
                # Create browser context (with or without saved session)
                auth_file_exists = check_auth_state()
                
                if auth_file_exists:
                    logger.info("Loading saved Facebook session...")
                    context = create_browser_context(browser, 'auth/auth_facebook.json')
                else:
                    logger.info("No saved session file - will need to login")
                    context = create_browser_context(browser)
                
                page = context.new_page()
                
                # Add page crash and error handlers
                def handle_page_crash(page):
                    logger.error("⚠️  PAGE CRASHED - Attempting recovery...")
                
                def handle_page_error(error):
                    logger.error(f"⚠️  PAGE ERROR: {error}")
                
                page.on("crash", lambda: handle_page_crash(page))
                page.on("pageerror", handle_page_error)
                logger.info("Page crash handlers installed")
                
                # Step 1: Verify if we're actually logged in
                logger.info("\n--- Step 1: Verify Login Status ---")
                is_logged_in = verify_logged_in(page)
                
                # Step 2: Perform login if needed
                if not is_logged_in:
                    logger.info("\n--- Step 2: Facebook Authentication Required ---")
                    
                    # Get Facebook credentials from profile manager
                    facebook_creds = profile_manager.get_facebook_credentials()
                    if not facebook_creds:
                        raise ConfigurationError("Facebook credentials not found in credenciales.txt")
                    
                    logger.info(f"Using Facebook credentials for: {facebook_creds['username']}")
                    
                    # Update config with credentials from credenciales.txt
                    config.FACEBOOK_EMAIL = facebook_creds['username']
                    config.FACEBOOK_PASSWORD = facebook_creds['password']
                    
                    # Perform login with retry logic (3 attempts, 50s waits, verification after each)
                    logger.info("🔄 Starting Facebook login with retry logic...")
                    login_facebook_with_retry(page, max_retries=3, wait_time=50)
                    
                    # Save authentication state after successful login
                    save_auth_state(context, page)
                    logger.info("[OK] ✅ Authentication complete and saved after retry verification")
                else:
                    logger.info("[OK] Already logged in - skipping authentication")
                
                # Step 3: Iterate through all profiles
                logger.info("\n--- Step 3: Multi-Profile Scraping ---")
                
                total_messages_found = 0
                total_new_messages = 0
                profiles_scraped = 0
                profiles_stopped_due_to_duplicates = 0
                
                for i, profile in enumerate(profiles, 1):
                    logger.info(f"\n{'='*60}")
                    logger.info(f"SCRAPING PROFILE {i}/{len(profiles)}: {profile['username']}")
                    logger.info(f"URL: {profile['url']}")
                    logger.info(f"{'='*60}")
                    
                    try:
                        # Navigate to profile
                        navigate_to_message(page, profile['url'])
                        
                        # Extract messages with database integration
                        messages, extraction_stats = extract_message_text_with_database(
                            page, profile['id'], max_messages=20  # Back to 20 messages per profile
                        )
                        
                        # Update totals
                        total_messages_found += extraction_stats['total_scraped']
                        total_new_messages += extraction_stats['new_messages']
                        profiles_scraped += 1
                        
                        if extraction_stats['stopped_due_to_duplicate']:
                            profiles_stopped_due_to_duplicates += 1
                            logger.info(f"✅ Profile {profile['username']}: Stopped due to duplicate - all new content processed")
                        else:
                            logger.info(f"✅ Profile {profile['username']}: Completed scraping")
                        
                        logger.info(f"  📊 Profile Stats:")
                        logger.info(f"    - Messages found: {extraction_stats['total_scraped']}")
                        logger.info(f"    - New messages: {extraction_stats['new_messages']}")
                        logger.info(f"    - Duplicates: {extraction_stats['duplicates_found']}")
                        logger.info(f"    - Quality filtered: {extraction_stats['quality_filtered']}")
                        
                        # Mark profile as scraped
                        profile_manager.mark_profile_scraped(profile['id'])
                        
                        # Wait between profiles to be respectful
                        if i < len(profiles):  # Don't wait after the last profile
                            logger.info("⏳ Waiting 30 seconds before next profile...")
                            page.wait_for_timeout(30000)
                    
                    except Exception as e:
                        logger.error(f"❌ Error scraping profile {profile['username']}: {e}")
                        continue
                
                # Display overall results
                logger.info("\n" + "="*70)
                logger.info("[OK] PHASE 1 COMPLETE - MULTI-PROFILE SCRAPING")
                logger.info("="*70)
                logger.info(f"📊 Overall Statistics:")
                logger.info(f"  - Profiles processed: {profiles_scraped}/{len(profiles)}")
                logger.info(f"  - Profiles stopped due to duplicates: {profiles_stopped_due_to_duplicates}")
                logger.info(f"  - Total messages found: {total_messages_found}")
                logger.info(f"  - Total new messages stored: {total_new_messages}")
                
                # Show database statistics
                db_stats = db.get_database_stats()
                message_stats = db.get_message_stats()
                logger.info(f"  - Database total messages: {message_stats['total_messages']}")
                logger.info(f"  - Messages ready to post: {message_stats['unposted']}")
                logger.info(f"  - Database size: {db_stats['database_size_mb']:.2f} MB")
                logger.info("="*70)
                
                # ============================================================
                # PHASE 2: X/Twitter Posting
                # ============================================================
                # TODO: Implement Phase 2 - X/Twitter Posting with database integration
                logger.info("\n[INFO] Phase 2 (X/Twitter Posting) - Available for implementation")
                logger.info(f"Ready to post: {message_stats['unposted']} messages from database")
                
                # ============================================================
                # PHASE 3: Screenshot & Database Management
                # ============================================================
                # TODO: Implement Phase 3 - Screenshot & Database maintenance
                logger.info("[INFO] Phase 3 (Screenshot & Database Management) - Available for implementation")
                
                logger.info("\n" + "="*70)
                logger.info("MULTI-PROFILE RELAY AGENT EXECUTION COMPLETE")
                logger.info("="*70)
                
            except LoginError as e:
                logger.error(f"\n[ERROR] Facebook authentication failed: {e}")
                raise
            
            except NavigationError as e:
                logger.error(f"\n[ERROR] Navigation to message failed: {e}")
                raise
            
            except ExtractionError as e:
                logger.error(f"\n[ERROR] Content extraction failed: {e}")
                raise
            
            finally:
                logger.info("\nClosing browser...")
                browser.close()
                logger.info("Browser closed")
        
    except ConfigurationError as e:
        logger.error(f"\n[ERROR] Configuration error: {e}")
        raise
    
    except RelayAgentError as e:
        logger.error(f"\n[ERROR] Relay agent error: {e}")
        raise
    
    except Exception as e:
        logger.error(f"\n[ERROR] Unexpected error: {e}", exc_info=True)
        raise
    
    finally:
        # Close debug session
        try:
            debug_session.close()
        except:
            pass


if __name__ == "__main__":
    main()


# Changelog

All notable changes to Alexis Scrapper.

---

## [2.2.3] - October 24, 2025

### Fixed: Image Padding Not Applied

#### Issue Resolved
- **Fixed** Generated Twitter images missing 500px blank space on top and bottom
- **Root Cause** Screenshot was capturing `.tweet-container` (inner element) instead of `.screenshot-wrapper` (outer element with padding)

#### Solution
Modified `generate_message_images.py` line 228 to screenshot the correct element:
- **Before:** `page.locator('.tweet-container').first` ❌
- **After:** `page.locator('.screenshot-wrapper').first` ✅

#### Result
- ✅ Images now include 500px padding top and bottom
- ✅ Image dimensions: ~1280 x 1580 pixels (vs. 1280 x 580 before)
- ✅ File size increased by ~25% due to additional padding

---

## [2.2.2] - October 23, 2025

### Dynamic Twitter Profile Fetching

#### Issue Resolved
- **Fixed** Hardcoded profile data in generated Twitter images
- **Implemented** Automatic profile info extraction from Twitter session  
- **Removed** Dependency on pre-configured profile data in .env

#### Root Cause
Twitter images were using hardcoded placeholder values (`"Your Display Name"`, `"@yourusername"`) instead of fetching the REAL profile information from the authenticated Twitter session.

#### Solution
1. Enhanced `twitter_auth.py` to fetch display name, username, and avatar URL from Twitter after authentication
2. Modified `twitter_post.py` to extract profile info during posting and save to .env files
3. Updated authentication to automatically populate profile data
4. Added standalone authentication script with profile display
5. Removed old auth files from backups to prevent account conflicts

#### Key Features
- ✅ **Automatic Profile Detection:** System fetches YOUR real Twitter profile info
- ✅ **Per-Installation Auth:** Each installation creates fresh auth for its specific account
- ✅ **Dynamic Image Generation:** Generated images use correct display name, @username, and avatar
- ✅ **No Manual Configuration:** Profile data automatically saved to .env

#### Files Modified
- `twitter/twitter_auth.py` - Added profile info extraction after login
- `twitter/twitter_post.py` - Added profile fetching during posting flow
- `twitter/generate_message_images.py` - Uses dynamic profile data from .env
- `README.md` - Updated authentication instructions
- `docs/TWITTER_AUTH_SETUP.md` - Enhanced with profile configuration steps
- `install.sh` - Added post-installation auth reminder

#### Authentication Process
Each installation now requires one-time manual authentication:
```bash
docker exec -it scraper-alexis-scraper python3 /app/twitter/twitter_auth.py
```

This creates fresh auth files with profile info specific to YOUR Twitter account.

---

## [2.2.1] - October 23, 2025

### Twitter Authentication Fix

#### Issue Resolved
- **Fixed** Twitter posting failure due to missing authentication files
- **Added** comprehensive documentation for Twitter auth setup
- **Restored** Twitter authentication from migration backup

#### Root Cause
Twitter authentication files (`auth_x.json` and `auth_x_session.json`) were not being included in the deployment, causing all Twitter posting attempts to fail with "No Twitter authentication file found!"

#### Solution
1. Restored auth files from migration backup
2. Added `TWITTER_AUTH_SETUP.md` documentation
3. Updated README with post-installation auth instructions

#### Verification
- ✅ Manual Twitter posting working
- ✅ Image generation successful
- ✅ Cron job configured (runs every 11 minutes)
- ✅ Authentication persists across container restarts

### Files Added
- `docs/TWITTER_AUTH_SETUP.md` - Complete Twitter auth setup guide

### Files Modified  
- `README.md` - Added Twitter authentication section
- `docs/CHANGELOG.md` - Documented this fix

---

## [2.2] - October 23, 2025

### Critical Fixes & Security Updates

#### Port Configuration
- **Changed** default port from 8080 to 8006 for better VPS compatibility
- **Updated** all documentation and scripts to reference port 8006
- **Added** firewall configuration notes for UFW users

#### Security Improvements
- **Removed** default credentials display from login page
- **Hidden** admin credentials from public view (still functional)
- **Updated** docker-entrypoint.sh to remove credential console output
- **Improved** security posture by not exposing default login info

#### Manual Execution Detection
- **Added** automatic detection of manual vs cron execution
- **Implemented** terminal detection (`-t 0` check) in run scripts
- **Added** `SKIP_DELAY` environment variable support
- **Fixed** manual runs now execute instantly without delays
- **Maintained** natural timing delays for automated cron jobs

#### Documentation
- **Added** `MANUAL_EXECUTION.md` - Complete guide for manual script execution
- **Added** `OCTOBER_22_FIXES.md` - Detailed changelog of all fixes
- **Updated** `README.md` with latest features and port changes
- **Updated** `CHANGELOG.md` with version 2.2 information

### Technical Changes
- Modified `run_facebook_flow.sh` with execution detection logic
- Modified `run_twitter_flow.sh` with execution detection logic
- Updated `docker-compose.yml` port mapping (8080 → 8006)
- Updated `install.sh` to display port 8006
- Removed hardcoded credentials from login blade template

### Testing & Verification
- ✅ Manual execution runs instantly
- ✅ Cron execution uses natural delays (±20% of interval)
- ✅ Credentials hidden from login page
- ✅ Port 8006 accessible after firewall configuration

---

## [2.1] - October 2025

### Image Generation Updates
- **Added** extra white space to tweet images (500px top/bottom)
- **Updated** image dimensions from ~1262x524 to ~1262x1388 pixels
- **Fixed** screenshot wrapper to properly capture white space
- **Maintained** white background for all generated images
- **Kept** left/right spacing unchanged

### Technical Changes
- Modified `twitter/tweet_template.html` with `.screenshot-wrapper` div
- Updated `twitter/twitter_screenshot_generator.py` to capture wrapper element
- Added `twitter/test_image_generation.py` for standalone testing
- Exported updated Docker image (1.9GB)

---

## [2.0] - October 2025

### Major Features
- **Dynamic Profile Detection**: Automatically extracts Twitter display name and username
- **Enhanced Image Generation**: Uses actual profile information in generated images
- **Improved Template System**: Better HTML template with modern Twitter styling
- **Avatar Caching**: Downloads and caches avatars locally for better performance
- **Proxy Integration**: Full proxy support for Twitter authentication

### Web Dashboard
- Modern Laravel-based interface
- Real-time log viewing
- Message queue management
- Settings management via web UI
- Automatic service restart on settings change

### Automation
- Cron-based scheduling for Facebook and Twitter
- Automatic image generation for all posts
- Session persistence with Playwright
- Comprehensive logging system

---

## [1.0] - Initial Release

### Core Features
- Facebook posting automation
- Twitter/X posting automation
- Basic image generation from templates
- SQLite database for message storage
- Docker containerization
- Manual configuration via .env files

---

## Upgrade Notes

### From v1.0 to v2.x
The portable installation package includes all updates. Simply:
1. Stop old containers: `docker stop scraper-alexis-scraper scraper-alexis-web`
2. Remove old containers: `docker rm scraper-alexis-scraper scraper-alexis-web`
3. Run new installer: `./install.sh`

All data and configurations will be preserved in Docker volumes.

---

**Update Frequency:** As needed based on social media platform changes and user requests.


# Social Media Relay Agent

**Automated Facebook-to-Twitter content distribution with image generation**

## Overview
Python-based automation system that scrapes messages from Facebook profiles/groups, posts them to Twitter, and generates shareable tweet-style images. Built with Playwright for browser automation and SQLite for state management.

## Key Features
- ✅ Multi-profile Facebook scraping with deduplication
- ✅ Automated Twitter posting with proxy support
- ✅ High-quality tweet image generation
- ✅ Complete database tracking and state management
- ✅ UTF-8 encoding for Spanish content
- ✅ Session-based authentication (no repeated logins)
- ✅ Production-ready Windows batch scripts

## Technology Stack
- **Python 3.10+** - Core language
- **Playwright** - Browser automation (Chromium)
- **SQLite** - Local database
- **Requests** - HTTP client for avatar downloads
- **python-dotenv** - Environment configuration

## Quick Start

### 1. Install
```bash
pip install -r requirements.txt
python -m playwright install chromium
```

### 2. Configure
Create `copy.env`:
```env
FACEBOOK_PROFILES=https://facebook.com/profile1,https://facebook.com/profile2
X_EMAIL=your_twitter_email
X_PASSWORD=your_twitter_password
DATABASE_PATH=data/scraper.db
```

### 3. Run
```bash
# Scrape Facebook messages
run_facebook_flow.bat

# Post to Twitter
run_twitter_flow.bat

# Generate images
run_image_generation.bat
```

## Project Structure
```
alexis scrapper/
├── core/              # Core modules (database, deduplication)
├── facebook/          # Facebook scraping modules
├── twitter/           # Twitter posting modules
├── auth/              # Authentication sessions
├── data/              # Database and generated images
├── relay_agent.py     # Main Facebook scraper
└── generate_message_images.py  # Image generator
```

## Workflows

### 1. Facebook Scraping
- Scrapes 20 messages per profile
- Prevents duplicate scraping via SHA256 hashing
- UTF-8 encoding for Spanish characters
- JSON backups exported

### 2. Twitter Posting
- Posts messages via proxy
- Captures post URLs and avatar URLs
- Updates database with posting status
- Handles authentication automatically

### 3. Image Generation
- Creates tweet-style images using HTML template
- Downloads and caches avatars
- Generates high-quality PNG files
- Tracks generation status in database

## Database
**Primary Database**: `data/scraper.db` (SQLite)

**Tables**:
- `profiles` - Facebook sources to scrape
- `messages` - Scraped messages and lifecycle
- `scraping_sessions` - Audit trail

**Key Features**:
- UNIQUE constraint on message_hash prevents duplicates
- Full lifecycle tracking (scraped → posted → image generated)
- UTF-8 encoding enforced

## Documentation
For detailed information, see:
- **[SPECIFICATION.md](SPECIFICATION.md)** - Project requirements and scope
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - System design and modules
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** - Database structure
- **[WORKFLOWS.md](WORKFLOWS.md)** - Detailed process flows
- **[USAGE.md](USAGE.md)** - Setup and operation guide

## Current Status
- ✅ 6 active Facebook profiles
- ✅ 45 total messages scraped
- ✅ 7 messages posted to Twitter
- ✅ 7 images generated
- ✅ 100% UTF-8 encoding working
- ✅ Production-ready

## Requirements
- Windows 10/11
- Python 3.10+
- Playwright browsers
- Proxy access (for Twitter)
- Facebook credentials
- Twitter credentials

## Configuration Files
- `copy.env` - Environment variables (not committed)
- `config.py` - Python configuration
- `auth/auth_facebook.json` - Facebook session (gitignored)
- `auth/auth_x.json` - Twitter session (gitignored)

## Key Scripts

### Production Batch Scripts
- `run_facebook_flow.bat` - Complete Facebook scraping workflow
- `run_twitter_flow.bat` - Complete Twitter posting workflow
- `run_image_generation.bat` - Complete image generation workflow

### Python Scripts
- `relay_agent.py` - Facebook scraping orchestrator
- `twitter/twitter_post.py` - Twitter posting (has main function)
- `generate_message_images.py` - Image generation orchestrator

## Data Storage

### Database
`data/scraper.db` - All scraped messages, profiles, sessions

### Images
`data/message_images/` - Generated tweet-style PNG images

### Avatars
`avatar_cache/` - Cached user avatars (reused across images)

### Logs
`logs/relay_agent_YYYYMMDD.log` - Daily operation logs

### Debug
`debug_output/run_*/` - Screenshots and session logs per run

## Security
- Credentials stored in `.env` file (not committed)
- Session files in `auth/` (gitignored)
- Proxy authentication via environment variables
- No hardcoded passwords

## Limitations
- Single-threaded execution (one operation at a time)
- Windows-only batch scripts (Python scripts cross-platform)
- Requires manual initial authentication
- Proxy required for Twitter access

## Future Enhancements
- Async/parallel processing for multiple profiles
- Scheduling system with cron/Task Scheduler integration
- Web dashboard for monitoring
- Content filtering and moderation
- Multi-language support

## Support
For issues or questions:
1. Check logs in `logs/` directory
2. Review debug screenshots in `debug_output/`
3. Consult USAGE.md for common operations
4. Check WORKFLOWS.md for process details

## License
Private project - All rights reserved


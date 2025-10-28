# Project Specification

## Project Name
**Social Media Relay Agent** - Automated Facebook-to-Twitter content distribution system

## Purpose
Automate the process of scraping messages from Facebook groups/profiles, posting them to Twitter, and generating shareable images.

## Core Objectives
1. **Scrape** messages from multiple Facebook profiles/groups
2. **Post** selected messages to Twitter with proper formatting
3. **Generate** high-quality tweet-style images for posted messages
4. **Track** all operations in a centralized database
5. **Prevent** duplicate scraping and posting

## Key Requirements

### Functional Requirements
- Multi-profile Facebook scraping (configurable via environment)
- Automatic deduplication using message hashing
- Twitter posting with proxy support
- Image generation using HTML templates
- Session-based authentication for both platforms
- UTF-8 encoding for Spanish content

### Non-Functional Requirements
- Robust error handling and retry logic
- Comprehensive logging for debugging
- Database-driven state management
- Configurable via environment variables
- Production-ready batch scripts for Windows

## Technical Constraints
- **Platform**: Windows 10/11
- **Language**: Python 3.10+
- **Browser Automation**: Playwright (Chromium)
- **Database**: SQLite
- **Proxy Required**: For Twitter operations

## Success Metrics
- Zero duplicate messages scraped
- 100% proper UTF-8 encoding
- Images generated for all posted messages
- Automated workflow requiring minimal user intervention

## Out of Scope
- Real-time monitoring
- Multi-user support
- Cloud deployment
- Mobile interface
- Sentiment analysis
- Content moderation


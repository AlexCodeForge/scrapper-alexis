# Database Schema

## Database Location
**Primary Database**: `data/scraper.db` (SQLite)

## Tables

### 1. `profiles`
Stores Facebook profile/group information for scraping.

**Columns**:
- `id` (INTEGER, PRIMARY KEY, AUTOINCREMENT)
- `username` (TEXT, NOT NULL) - Profile identifier
- `url` (TEXT, NOT NULL, UNIQUE) - Facebook profile/group URL
- `credentials_reference` (TEXT) - Reference to credentials (e.g., 'env')
- `last_scraped_at` (TIMESTAMP) - Last successful scrape time
- `is_active` (BOOLEAN, DEFAULT 1) - Profile active status
- `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**Purpose**: Track which Facebook sources to scrape

---

### 2. `messages`
Stores scraped messages and their processing status.

**Columns**:
- `id` (INTEGER, PRIMARY KEY, AUTOINCREMENT)
- `profile_id` (INTEGER, NOT NULL, FOREIGN KEY → profiles.id)
- `message_text` (TEXT, NOT NULL) - The actual message content
- `message_hash` (TEXT, NOT NULL, UNIQUE) - SHA256 hash for deduplication
- `scraped_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
- `posted_to_twitter` (BOOLEAN, DEFAULT 0) - Twitter posting status
- `posted_at` (TIMESTAMP) - When posted to Twitter
- `post_url` (TEXT) - Twitter post URL
- `avatar_url` (TEXT) - User avatar URL from Twitter
- `image_generated` (BOOLEAN, DEFAULT 0) - Image generation status
- `image_path` (TEXT) - Path to generated image file

**Purpose**: Central storage for all scraped messages and their lifecycle

**Key Features**:
- Deduplication via `message_hash` (UNIQUE constraint)
- Full lifecycle tracking from scrape → post → image generation
- UTF-8 encoding enforced at connection level

---

### 3. `scraping_sessions`
Tracks individual scraping operations for audit/analytics.

**Columns**:
- `id` (INTEGER, PRIMARY KEY, AUTOINCREMENT)
- `profile_id` (INTEGER, NOT NULL, FOREIGN KEY → profiles.id)
- `started_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
- `completed_at` (TIMESTAMP)
- `messages_found` (INTEGER) - Total messages found during scrape
- `messages_new` (INTEGER) - New messages added (not duplicates)
- `stopped_reason` (TEXT) - Why scraping stopped (completed/duplicate/error)

**Purpose**: Session tracking and performance analytics

---

## Key Relationships

```
profiles (1) ─── (many) messages
    │
    └─── (many) scraping_sessions

messages.profile_id → profiles.id
scraping_sessions.profile_id → profiles.id
```

## Indexing Strategy
- `messages.message_hash` - UNIQUE index for deduplication
- `messages.posted_to_twitter` - Filtered queries for posting queue
- `messages.image_generated` - Filtered queries for image generation

## Database Operations

### Read Operations
- Get unposted messages (for Twitter posting)
- Get messages without images (for image generation)
- Get active profiles (for scraping)
- Get session statistics

### Write Operations
- Add profiles (from config)
- Add messages (from scraping)
- Mark message as posted (after Twitter post)
- Mark image as generated (after image creation)
- Update scraping sessions

## Data Flow
1. **Profiles** loaded from `copy.env` → synced to `profiles` table
2. **Scraping** creates `scraping_sessions` → adds to `messages` table
3. **Twitter posting** updates `messages.posted_to_twitter`, stores URLs
4. **Image generation** updates `messages.image_generated`, stores paths


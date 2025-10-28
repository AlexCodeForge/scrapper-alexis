# System Workflows

## Overview
Three independent workflows that can run separately or in sequence.

---

## Workflow 1: Facebook Scraping

### Entry Point
**Batch Script**: `run_facebook_flow.bat`
**Main Script**: `relay_agent.py`

### Process Flow

```
START
  ↓
Validate Environment
  ├─ copy.env exists?
  ├─ FACEBOOK_PROFILES configured?
  └─ Database directory exists?
  ↓
Initialize Database
  ├─ Create/migrate schema
  ├─ Sync profiles from env to DB
  └─ Create data/ directory
  ↓
Check Facebook Authentication
  ├─ auth/auth_facebook.json exists?
  ├─ YES → Load session
  └─ NO → Login with credentials from copy.env
  ↓
FOR EACH Profile:
  ├─ Start Scraping Session (DB record)
  ├─ Navigate to Profile URL
  ├─ Extract Messages (up to 20 per profile)
  │   ├─ Scroll page progressively
  │   ├─ Extract text via JavaScript
  │   ├─ Calculate SHA256 hash
  │   ├─ Check if duplicate (DB lookup)
  │   ├─ If duplicate → continue or stop
  │   └─ If new → add to database
  ├─ Export to JSON (debug_output/extraction/)
  ├─ Complete Session (DB update)
  └─ Wait 30s before next profile
  ↓
Show Results
  ├─ Total messages scraped
  ├─ New vs duplicate count
  └─ Ready for Twitter posting
  ↓
END
```

### Key Features
- **Deduplication**: Stops when encountering consecutive duplicates
- **Quality Filter**: Min 10 chars, max 280 chars
- **UTF-8 Encoding**: text.normalize('NFC') in JavaScript
- **Session Tracking**: Each scrape creates a session record

### Output
- Messages in `data/scraper.db` with `posted_to_twitter = 0`
- JSON backup in `debug_output/extraction/`
- Debug screenshots in `debug_output/run_*/`

---

## Workflow 2: Twitter Posting

### Entry Point
**Batch Script**: `run_twitter_flow.bat`
**Main Script**: `twitter/twitter_post.py` (main function)

### Process Flow

```
START
  ↓
Validate Prerequisites
  ├─ copy.env exists?
  ├─ data/scraper.db exists?
  ├─ auth/auth_x.json exists?
  └─ Unposted messages available?
  ↓
Get Next Message
  ├─ Query: posted_to_twitter = 0
  ├─ Order by scraped_at ASC
  └─ Limit 1
  ↓
Initialize Browser with Proxy
  ├─ Launch Chromium
  ├─ Configure proxy (77.47.156.7:50100)
  └─ Load Twitter session
  ↓
Verify Authentication
  ├─ Navigate to twitter.com/home
  ├─ Check URL doesn't contain 'login'
  └─ If not logged in → ERROR and exit
  ↓
Post Tweet
  ├─ Click compose textbox
  ├─ Type message text
  ├─ Truncate if > 280 chars
  ├─ Click post button
  └─ Wait for confirmation
  ↓
Extract Metadata
  ├─ Get post URL from page
  ├─ Get avatar URL from profile
  └─ Capture screenshot
  ↓
Update Database
  ├─ posted_to_twitter = 1
  ├─ posted_at = CURRENT_TIMESTAMP
  ├─ post_url = extracted URL
  └─ avatar_url = extracted URL
  ↓
Show Results
  ├─ Success/failure status
  ├─ Post URL (if successful)
  └─ Database update confirmation
  ↓
END
```

### Key Features
- **Proxy Support**: All Twitter requests go through proxy
- **Session Reuse**: Avoids login on every run
- **URL Extraction**: Captures actual Twitter post URL
- **Avatar Capture**: Stores high-quality avatar URL

### Output
- Message marked as posted in database
- Post URL and avatar URL stored
- Debug screenshots of posting process

---

## Workflow 3: Image Generation

### Entry Point
**Batch Script**: `run_image_generation.bat`
**Main Script**: `generate_message_images.py`

### Process Flow

```
START
  ↓
Validate Prerequisites
  ├─ copy.env exists?
  ├─ data/scraper.db exists?
  ├─ twitter/tweet_template.html exists?
  └─ Posted messages without images exist?
  ↓
Get Messages Needing Images
  ├─ Query: posted_to_twitter = 1 AND image_generated = 0
  ├─ Order by posted_at DESC
  └─ Limit 50
  ↓
FOR EACH Message:
  ├─ Download Avatar
  │   ├─ Check avatar_cache/ for existing
  │   ├─ If not cached → download via proxy
  │   ├─ Convert _normal.jpg → _400x400.jpg
  │   └─ Save to avatar_cache/
  ├─ Update Template
  │   ├─ Read tweet_template.html
  │   ├─ Replace avatar src with local file://
  │   ├─ Replace tweet text with message_text
  │   └─ Save as temp_message_template.html
  ├─ Generate Screenshot
  │   ├─ Launch browser (headless=False)
  │   ├─ Navigate to temp template
  │   ├─ Wait for assets to load (2s)
  │   ├─ Screenshot .tweet-container element
  │   └─ Save as msg_{id}_{timestamp}_{text}.png
  ├─ Update Database
  │   ├─ image_generated = 1
  │   └─ image_path = relative path
  └─ Wait 1s before next image
  ↓
Show Results
  ├─ Total images generated
  ├─ Success/failure count
  └─ Images directory location
  ↓
END
```

### Key Features
- **Template-Based**: Uses HTML template for consistent styling
- **Avatar Caching**: Avoids re-downloading same avatars
- **High-Quality**: 400x400px avatars, PNG format
- **Element Screenshot**: Only captures tweet container, not full page

### Output
- Images in `data/message_images/`
- Database updated with `image_generated = 1`
- Image paths stored for reference

---

## Workflow Integration

### Sequential Execution
```
1. run_facebook_flow.bat
   ↓ (Scraped messages in DB)
2. run_twitter_flow.bat (run multiple times)
   ↓ (Posted messages in DB)
3. run_image_generation.bat
   ↓ (Images generated and stored)
```

### Independent Execution
Each workflow can run independently:
- **Facebook**: Can scrape without posting
- **Twitter**: Can post existing unposted messages
- **Images**: Can generate images for any posted messages

### Error Handling

#### All Workflows
- Validate prerequisites before execution
- Graceful exit on missing requirements
- Comprehensive error logging
- User-friendly error messages

#### Facebook Workflow
- Retry login if session expired
- Stop gracefully on consecutive duplicates
- Log extraction errors but continue

#### Twitter Workflow
- Detect posting failures
- Don't mark as posted if posting fails
- Handle duplicate tweet errors

#### Image Generation
- Skip messages with missing avatars
- Continue on individual image failures
- Report success/failure counts

---

## Configuration

### Environment Variables (copy.env)
```
FACEBOOK_PROFILES=url1,url2,url3...
DATABASE_PATH=data/scraper.db
X_EMAIL=twitter_email
X_PASSWORD=twitter_password
```

### Profile Manager
Syncs `FACEBOOK_PROFILES` from env to database on each run.

### Database Initialization
Auto-creates schema and applies migrations on first run.


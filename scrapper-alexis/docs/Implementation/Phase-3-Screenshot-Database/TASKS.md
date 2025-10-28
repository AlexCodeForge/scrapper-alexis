# Phase 3: Screenshot & Database Storage - Tasks

## Task 1: Create Database Schema
**Priority:** CRITICAL  
**Estimated Time:** 30 minutes

### Steps:
1. Create `schema.sql` with table definitions
2. Add indexes for performance
3. Add foreign key constraints

### Code Template:
```sql
-- schema.sql

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Message content
    message_text TEXT NOT NULL,
    message_text_original TEXT NOT NULL,
    message_length INTEGER NOT NULL,
    was_truncated BOOLEAN DEFAULT 0,
    
    -- Source information
    facebook_url TEXT NOT NULL,
    facebook_message_id TEXT,
    
    -- Screenshot reference
    screenshot_path TEXT NOT NULL,
    screenshot_filename TEXT NOT NULL,
    screenshot_size_bytes INTEGER,
    
    -- X/Twitter post information
    x_post_url TEXT,
    x_posted_at TIMESTAMP,
    
    -- Execution metadata
    execution_status TEXT DEFAULT 'success',
    execution_error TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Prevent duplicate processing
    UNIQUE(facebook_url, created_at)
);

-- Execution log table
CREATE TABLE IF NOT EXISTS execution_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER,
    
    phase TEXT NOT NULL,
    status TEXT NOT NULL,
    duration_seconds REAL,
    error_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_messages_facebook_url ON messages(facebook_url);
CREATE INDEX IF NOT EXISTS idx_messages_status ON messages(execution_status);
CREATE INDEX IF NOT EXISTS idx_execution_log_message_id ON execution_log(message_id);
CREATE INDEX IF NOT EXISTS idx_execution_log_phase ON execution_log(phase);
```

### Verification:
```bash
sqlite3 relay_agent.db < schema.sql
sqlite3 relay_agent.db ".schema"
```

---

## Task 2: Implement Database Abstraction Layer
**Priority:** CRITICAL  
**Estimated Time:** 60 minutes

### Steps:
1. Create `database.py`
2. Implement `MessageDatabase` class
3. Add all CRUD operations
4. Implement context manager support

### Code Template:
```python
# database.py
import sqlite3
from pathlib import Path
from datetime import datetime
from typing import Optional, Dict, List
import json
import logging

logger = logging.getLogger(__name__)

class MessageDatabase:
    def __init__(self, db_path: str = "relay_agent.db"):
        self.db_path = Path(db_path)
        self.conn = None
        self.init_database()
    
    def init_database(self):
        """Initialize database with schema."""
        self.conn = sqlite3.connect(str(self.db_path))
        self.conn.row_factory = sqlite3.Row
        
        # Load and execute schema
        schema_path = Path('schema.sql')
        if schema_path.exists():
            with open(schema_path, 'r') as f:
                self.conn.executescript(f.read())
        
        self.conn.commit()
        logger.info(f"Database initialized: {self.db_path}")
    
    def insert_message(
        self,
        message_text: str,
        message_text_original: str,
        facebook_url: str,
        screenshot_path: str,
        screenshot_size_bytes: int,
        was_truncated: bool = False,
        x_post_url: Optional[str] = None,
        execution_status: str = 'success',
        execution_error: Optional[str] = None
    ) -> int:
        """Insert a new message record. Returns message_id."""
        cursor = self.conn.cursor()
        
        screenshot_filename = Path(screenshot_path).name
        
        cursor.execute("""
            INSERT INTO messages (
                message_text,
                message_text_original,
                message_length,
                was_truncated,
                facebook_url,
                screenshot_path,
                screenshot_filename,
                screenshot_size_bytes,
                x_post_url,
                x_posted_at,
                execution_status,
                execution_error
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            message_text,
            message_text_original,
            len(message_text),
            was_truncated,
            facebook_url,
            str(screenshot_path),
            screenshot_filename,
            screenshot_size_bytes,
            x_post_url,
            datetime.now() if x_post_url else None,
            execution_status,
            execution_error
        ))
        
        self.conn.commit()
        message_id = cursor.lastrowid
        logger.info(f"Inserted message record with ID: {message_id}")
        return message_id
    
    def log_execution_phase(
        self,
        message_id: Optional[int],
        phase: str,
        status: str,
        duration_seconds: Optional[float] = None,
        error_message: Optional[str] = None
    ):
        """Log execution phase details."""
        cursor = self.conn.cursor()
        
        cursor.execute("""
            INSERT INTO execution_log (
                message_id, phase, status, duration_seconds, error_message
            ) VALUES (?, ?, ?, ?, ?)
        """, (message_id, phase, status, duration_seconds, error_message))
        
        self.conn.commit()
    
    def get_message_by_id(self, message_id: int) -> Optional[Dict]:
        """Retrieve a message by ID."""
        cursor = self.conn.cursor()
        cursor.execute("SELECT * FROM messages WHERE id = ?", (message_id,))
        row = cursor.fetchone()
        return dict(row) if row else None
    
    def get_recent_messages(self, limit: int = 10) -> List[Dict]:
        """Get recent messages."""
        cursor = self.conn.cursor()
        cursor.execute("""
            SELECT * FROM messages 
            ORDER BY created_at DESC 
            LIMIT ?
        """, (limit,))
        return [dict(row) for row in cursor.fetchall()]
    
    def message_exists(self, facebook_url: str, hours: int = 24) -> bool:
        """Check if message was processed recently."""
        cursor = self.conn.cursor()
        cursor.execute("""
            SELECT COUNT(*) FROM messages 
            WHERE facebook_url = ? 
            AND created_at > datetime('now', '-' || ? || ' hours')
        """, (facebook_url, hours))
        count = cursor.fetchone()[0]
        return count > 0
    
    def search_messages(self, query: str) -> List[Dict]:
        """Full-text search in message content."""
        cursor = self.conn.cursor()
        cursor.execute("""
            SELECT * FROM messages 
            WHERE message_text LIKE ? OR message_text_original LIKE ?
            ORDER BY created_at DESC
        """, (f'%{query}%', f'%{query}%'))
        return [dict(row) for row in cursor.fetchall()]
    
    def get_statistics(self) -> Dict:
        """Get database statistics."""
        cursor = self.conn.cursor()
        stats = {}
        
        # Total messages
        cursor.execute("SELECT COUNT(*) FROM messages")
        stats['total_messages'] = cursor.fetchone()[0]
        
        # Successful executions
        cursor.execute("SELECT COUNT(*) FROM messages WHERE execution_status = 'success'")
        stats['successful_executions'] = cursor.fetchone()[0]
        
        # Truncated messages
        cursor.execute("SELECT COUNT(*) FROM messages WHERE was_truncated = 1")
        stats['truncated_messages'] = cursor.fetchone()[0]
        
        # Average message length
        cursor.execute("SELECT AVG(message_length) FROM messages")
        stats['avg_message_length'] = round(cursor.fetchone()[0] or 0, 2)
        
        # Total screenshot size
        cursor.execute("SELECT SUM(screenshot_size_bytes) FROM messages")
        total_bytes = cursor.fetchone()[0] or 0
        stats['total_screenshot_size_mb'] = round(total_bytes / (1024 * 1024), 2)
        
        return stats
    
    def close(self):
        """Close database connection."""
        if self.conn:
            self.conn.close()
            logger.info("Database connection closed")
    
    def __enter__(self):
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        self.close()
```

### Verification:
```python
from database import MessageDatabase

with MessageDatabase() as db:
    stats = db.get_statistics()
    print(stats)
```

---

## Task 3: Implement Screenshot Capture
**Priority:** CRITICAL  
**Estimated Time:** 30 minutes

### Steps:
1. Create `screenshot_capture.py`
2. Implement re-focus logic
3. Implement screenshot capture with validation
4. Implement metadata generation

### Code Template:
```python
# screenshot_capture.py
import logging
from pathlib import Path
from datetime import datetime
from typing import Tuple
import json
from playwright.sync_api import Page, Locator

from exceptions import ScreenshotError

logger = logging.getLogger(__name__)

def refocus_facebook_page(page: Page, message_url: str, message_locator: Locator):
    """Re-focus on Facebook page and ensure message is visible."""
    try:
        logger.info("Re-focusing on Facebook page...")
        
        # Bring page to front
        page.bring_to_front()
        
        # Navigate back if needed
        if page.url != message_url:
            logger.info(f"Navigating back to: {message_url}")
            page.goto(message_url, wait_until='domcontentloaded', timeout=30000)
        
        # Wait for message to be visible
        message_locator.wait_for(state='visible', timeout=10000)
        
        logger.info("✅ Re-focus successful")
        return True
        
    except Exception as e:
        logger.error(f"Re-focus failed: {e}")
        raise ScreenshotError(f"Could not re-focus on Facebook page: {e}")


def capture_message_screenshot(
    page: Page,
    message_locator: Locator,
    message_url: str,
    message_text: str
) -> Tuple[Path, int, dict]:
    """
    Capture screenshot of message element.
    
    Returns:
        Tuple of (screenshot_path, file_size, metadata)
    """
    try:
        # Generate filename
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        screenshot_dir = Path('screenshots')
        screenshot_dir.mkdir(exist_ok=True)
        screenshot_path = screenshot_dir / f'fb_message_{timestamp}.png'
        
        # Capture screenshot
        logger.info(f"Capturing screenshot to: {screenshot_path}")
        message_locator.screenshot(path=str(screenshot_path), timeout=10000)
        
        # Verify screenshot
        if not screenshot_path.exists():
            raise ScreenshotError("Screenshot file was not created")
        
        file_size = screenshot_path.stat().st_size
        
        if file_size < 100:
            raise ScreenshotError(f"Screenshot file too small: {file_size} bytes")
        
        logger.info(f"✅ Screenshot saved: {screenshot_path} ({file_size} bytes)")
        
        # Generate metadata
        metadata = {
            'timestamp': timestamp,
            'source_url': message_url,
            'extracted_text': message_text,
            'screenshot_path': str(screenshot_path),
            'screenshot_size_bytes': file_size,
            'capture_time': datetime.now().isoformat()
        }
        
        # Save metadata
        metadata_path = screenshot_dir / f'metadata_{timestamp}.json'
        with open(metadata_path, 'w', encoding='utf-8') as f:
            json.dump(metadata, f, indent=2, ensure_ascii=False)
        
        logger.info(f"✅ Metadata saved: {metadata_path}")
        
        return screenshot_path, file_size, metadata
        
    except Exception as e:
        logger.error(f"Screenshot capture failed: {e}")
        raise ScreenshotError(f"Failed to capture screenshot: {e}")
```

### Verification:
Test screenshot capture with real Facebook page.

---

## Task 4: Create Query CLI Tool
**Priority:** MEDIUM  
**Estimated Time:** 30 minutes

### Steps:
1. Create `query_database.py`
2. Implement command-line arguments
3. Add query functions (recent, search, stats, export)

### Code Template:
```python
# query_database.py
#!/usr/bin/env python3
import argparse
import json
import sys
from tabulate import tabulate
from database import MessageDatabase

def main():
    parser = argparse.ArgumentParser(description='Query relay agent database')
    parser.add_argument('--recent', type=int, help='Show N recent messages', metavar='N')
    parser.add_argument('--search', type=str, help='Search messages by text')
    parser.add_argument('--stats', action='store_true', help='Show database statistics')
    parser.add_argument('--message-id', type=int, help='Get message by ID')
    parser.add_argument('--export', type=str, help='Export to JSON file')
    
    args = parser.parse_args()
    
    with MessageDatabase() as db:
        if args.stats:
            stats = db.get_statistics()
            print("\n=== Database Statistics ===")
            for key, value in stats.items():
                print(f"{key.replace('_', ' ').title()}: {value}")
        
        elif args.recent:
            messages = db.get_recent_messages(limit=args.recent)
            print(f"\n=== {len(messages)} Recent Messages ===")
            table_data = []
            for msg in messages:
                table_data.append([
                    msg['id'],
                    msg['created_at'][:19],
                    msg['message_text'][:50] + '...' if len(msg['message_text']) > 50 else msg['message_text'],
                    '✂️' if msg['was_truncated'] else '',
                    msg['execution_status']
                ])
            print(tabulate(table_data, 
                          headers=['ID', 'Date', 'Message', 'Truncated', 'Status'],
                          tablefmt='grid'))
        
        elif args.search:
            messages = db.search_messages(args.search)
            print(f"\n=== Found {len(messages)} Messages ===")
            for msg in messages:
                print(f"\nID: {msg['id']}")
                print(f"Date: {msg['created_at']}")
                print(f"Text: {msg['message_text']}")
                print(f"Screenshot: {msg['screenshot_filename']}")
                print("-" * 80)
        
        elif args.message_id:
            msg = db.get_message_by_id(args.message_id)
            if msg:
                print(f"\n=== Message {msg['id']} ===")
                for key, value in msg.items():
                    print(f"{key}: {value}")
            else:
                print(f"Message {args.message_id} not found")
        
        elif args.export:
            messages = db.get_recent_messages(limit=1000)
            with open(args.export, 'w') as f:
                json.dump(messages, f, indent=2, default=str)
            print(f"Exported {len(messages)} messages to {args.export}")
        
        else:
            parser.print_help()

if __name__ == "__main__":
    main()
```

### Verification:
```bash
python query_database.py --stats
python query_database.py --recent 5
```

---

## Task 5: Create Backup Utility
**Priority:** MEDIUM  
**Estimated Time:** 20 minutes

### Steps:
1. Create `backup_database.py`
2. Implement timestamped backup
3. Implement cleanup of old backups

### Code Template:
```python
# backup_database.py
#!/usr/bin/env python3
import shutil
from datetime import datetime
from pathlib import Path
import logging

logger = logging.getLogger(__name__)

def backup_database():
    """Create timestamped backup of database and screenshots."""
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup_dir = Path('backups') / timestamp
    backup_dir.mkdir(parents=True, exist_ok=True)
    
    # Backup database
    db_path = Path('relay_agent.db')
    if db_path.exists():
        shutil.copy2(db_path, backup_dir / 'relay_agent.db')
        logger.info(f"Database backed up to {backup_dir}")
    
    # Backup screenshots
    screenshots_dir = Path('screenshots')
    if screenshots_dir.exists():
        shutil.copytree(screenshots_dir, backup_dir / 'screenshots')
        logger.info(f"Screenshots backed up to {backup_dir}")
    
    # Cleanup old backups
    cleanup_old_backups(days=7)
    
    print(f"✅ Backup created: {backup_dir}")
    return backup_dir

def cleanup_old_backups(days=7):
    """Remove backups older than specified days."""
    backup_root = Path('backups')
    if not backup_root.exists():
        return
    
    cutoff = datetime.now().timestamp() - (days * 24 * 60 * 60)
    
    for backup_dir in backup_root.iterdir():
        if backup_dir.is_dir():
            if backup_dir.stat().st_mtime < cutoff:
                shutil.rmtree(backup_dir)
                logger.info(f"Removed old backup: {backup_dir}")
                print(f"🗑️ Removed old backup: {backup_dir.name}")

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    backup_database()
```

### Verification:
```bash
python backup_database.py
ls backups/
```

---

## Task 6: Integrate Phase 3 into Main Script
**Priority:** CRITICAL  
**Estimated Time:** 45 minutes

### Steps:
1. Update `relay_agent.py` imports
2. Add duplicate check before execution
3. Add screenshot capture after X posting
4. Add database storage
5. Add execution phase logging
6. Add auto-backup option

### Code Template:
```python
# relay_agent.py (Phase 3 integration)
import time
from playwright.sync_api import sync_playwright
import logging

import config
from database import MessageDatabase
from screenshot_capture import refocus_facebook_page, capture_message_screenshot
from backup_database import backup_database

# Previous phase imports...

logger = logging.getLogger(__name__)

def main():
    config.validate_config()
    
    db = MessageDatabase()
    start_time = time.time()
    message_id = None
    
    try:
        # Check for duplicates
        if db.message_exists(config.FACEBOOK_MESSAGE_URL, hours=config.DUPLICATE_CHECK_HOURS):
            logger.warning(f"Message already processed in last {config.DUPLICATE_CHECK_HOURS} hours")
            return
        
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=config.HEADLESS, slow_mo=config.SLOW_MO)
            
            # === PHASE 1: Facebook ===
            db.log_execution_phase(None, 'facebook_auth', 'started')
            phase_start = time.time()
            
            # ... Phase 1 code ...
            message_text_original = extract_message_text(page)
            message_locator = # store the locator
            
            db.log_execution_phase(None, 'facebook_auth', 'completed', 
                                  time.time() - phase_start)
            
            # === PHASE 2: X/Twitter ===
            db.log_execution_phase(None, 'x_posting', 'started')
            phase_start = time.time()
            
            # ... Phase 2 code ...
            posted_text = post_to_x(x_page, message_text_original)
            was_truncated = len(message_text_original) > len(posted_text)
            
            db.log_execution_phase(None, 'x_posting', 'completed',
                                  time.time() - phase_start)
            
            # === PHASE 3: Screenshot & Database ===
            logger.info("=== Phase 3: Screenshot & Database ===")
            
            db.log_execution_phase(None, 'screenshot', 'started')
            phase_start = time.time()
            
            # Re-focus and capture
            refocus_facebook_page(page, config.FACEBOOK_MESSAGE_URL, message_locator)
            screenshot_path, screenshot_size, metadata = capture_message_screenshot(
                page, message_locator, config.FACEBOOK_MESSAGE_URL, message_text_original
            )
            
            db.log_execution_phase(None, 'screenshot', 'completed',
                                  time.time() - phase_start)
            
            # Database storage
            db.log_execution_phase(None, 'database', 'started')
            phase_start = time.time()
            
            message_id = db.insert_message(
                message_text=posted_text,
                message_text_original=message_text_original,
                facebook_url=config.FACEBOOK_MESSAGE_URL,
                screenshot_path=str(screenshot_path),
                screenshot_size_bytes=screenshot_size,
                was_truncated=was_truncated,
                execution_status='success'
            )
            
            db.log_execution_phase(message_id, 'database', 'completed',
                                  time.time() - phase_start)
            
            logger.info(f"✅ Message stored with ID: {message_id}")
            logger.info(f"✅ Total execution time: {time.time() - start_time:.2f}s")
            
            browser.close()
        
        # Auto-backup if enabled
        if config.AUTO_BACKUP:
            backup_database()
        
    except Exception as e:
        logger.error(f"Execution failed: {e}")
        if message_id:
            db.log_execution_phase(message_id, 'general', 'failed', error_message=str(e))
        raise
    finally:
        db.close()

if __name__ == "__main__":
    main()
```

### Verification:
Run complete Phases 1-3 end-to-end.

---

## ✅ Phase 3 Completion Checklist

### Code Implementation
- [ ] `schema.sql` created with all tables and indexes
- [ ] `database.py` created with MessageDatabase class
- [ ] `screenshot_capture.py` created with capture logic
- [ ] `query_database.py` CLI tool created
- [ ] `backup_database.py` utility created
- [ ] `relay_agent.py` updated with Phase 3

### Functionality
- [ ] Database initializes correctly
- [ ] Re-focus on Facebook page works
- [ ] Screenshot captures successfully
- [ ] Screenshot validates (size > 100 bytes)
- [ ] Metadata JSON saves
- [ ] Message inserts into database
- [ ] Execution phases log correctly
- [ ] Duplicate check prevents re-processing
- [ ] Query CLI works (--recent, --search, --stats)
- [ ] Backup utility creates backups
- [ ] Auto-backup works if enabled

### Database Operations
- [ ] Can insert messages
- [ ] Can query by ID
- [ ] Can search messages
- [ ] Can get statistics
- [ ] Duplicate detection works
- [ ] Foreign keys enforce correctly
- [ ] Indexes improve query speed

### Testing
- [ ] End-to-end test completes
- [ ] Database persists across runs
- [ ] Screenshots accessible
- [ ] Backup/restore works

---

## 🚀 Next Steps
Once Phase 3 is complete, proceed to **Phase 4: Testing & Hardening**


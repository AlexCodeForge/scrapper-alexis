# Phase 3: Testing & Validation

## Test Categories

### 1. Database Schema Tests

#### Test 1.1: Schema Creation
```python
# test_schema.py
import sqlite3
from pathlib import Path

def test_schema_creation():
    """Test that schema creates successfully."""
    # Use test database
    test_db = Path('test_relay_agent.db')
    test_db.unlink(missing_ok=True)
    
    conn = sqlite3.connect(str(test_db))
    
    # Load schema
    with open('schema.sql', 'r') as f:
        conn.executescript(f.read())
    
    # Verify tables exist
    cursor = conn.cursor()
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
    tables = [row[0] for row in cursor.fetchall()]
    
    assert 'messages' in tables
    assert 'execution_log' in tables
    
    print(f"✅ Schema created successfully")
    print(f"Tables: {tables}")
    
    conn.close()
    test_db.unlink()

if __name__ == "__main__":
    test_schema_creation()
```

**Pass Criteria:** Both tables created

---

#### Test 1.2: Indexes Created
```python
# test_indexes.py
import sqlite3
from pathlib import Path

def test_indexes_created():
    """Test that all indexes are created."""
    from database import MessageDatabase
    
    with MessageDatabase('test_relay_agent.db') as db:
        cursor = db.conn.cursor()
        cursor.execute("SELECT name FROM sqlite_master WHERE type='index'")
        indexes = [row[0] for row in cursor.fetchall()]
        
        expected_indexes = [
            'idx_messages_created_at',
            'idx_messages_facebook_url',
            'idx_execution_log_message_id'
        ]
        
        for idx in expected_indexes:
            assert idx in indexes, f"Index {idx} not found"
            print(f"✅ Index {idx} exists")
    
    Path('test_relay_agent.db').unlink()

if __name__ == "__main__":
    test_indexes_created()
```

**Pass Criteria:** All indexes present

---

### 2. Database Operations Tests

#### Test 2.1: Insert Message
```python
# test_insert_message.py
from database import MessageDatabase
from pathlib import Path

def test_insert_message():
    """Test inserting a message record."""
    test_db = 'test_relay_agent.db'
    Path(test_db).unlink(missing_ok=True)
    
    with MessageDatabase(test_db) as db:
        message_id = db.insert_message(
            message_text="Test message posted",
            message_text_original="Test message original",
            facebook_url="https://facebook.com/test",
            screenshot_path="screenshots/test.png",
            screenshot_size_bytes=12345,
            was_truncated=False,
            execution_status='success'
        )
        
        assert message_id > 0
        print(f"✅ Inserted message with ID: {message_id}")
        
        # Verify retrieval
        msg = db.get_message_by_id(message_id)
        assert msg is not None
        assert msg['message_text'] == "Test message posted"
        print(f"✅ Retrieved message: {msg['message_text']}")
    
    Path(test_db).unlink()

if __name__ == "__main__":
    test_insert_message()
```

**Pass Criteria:** Message inserted and retrieved

---

#### Test 2.2: Duplicate Detection
```python
# test_duplicate_detection.py
from database import MessageDatabase
from pathlib import Path

def test_duplicate_detection():
    """Test that duplicate detection works."""
    test_db = 'test_relay_agent.db'
    Path(test_db).unlink(missing_ok=True)
    
    with MessageDatabase(test_db) as db:
        url = "https://facebook.com/test_message"
        
        # First insert
        db.insert_message(
            message_text="Test",
            message_text_original="Test",
            facebook_url=url,
            screenshot_path="test.png",
            screenshot_size_bytes=100
        )
        
        # Check exists within 24 hours
        assert db.message_exists(url, hours=24) == True
        print("✅ Duplicate detected within 24 hours")
        
        # Check doesn't exist if we look back only 0 hours
        assert db.message_exists(url, hours=0) == False
        print("✅ Duplicate not detected outside time window")
    
    Path(test_db).unlink()

if __name__ == "__main__":
    test_duplicate_detection()
```

**Pass Criteria:** Duplicate detection works correctly

---

#### Test 2.3: Statistics Query
```python
# test_statistics.py
from database import MessageDatabase
from pathlib import Path

def test_statistics():
    """Test statistics query."""
    test_db = 'test_relay_agent.db'
    Path(test_db).unlink(missing_ok=True)
    
    with MessageDatabase(test_db) as db:
        # Insert test data
        for i in range(5):
            db.insert_message(
                message_text=f"Message {i}",
                message_text_original=f"Message {i} original",
                facebook_url=f"https://facebook.com/msg{i}",
                screenshot_path=f"test{i}.png",
                screenshot_size_bytes=1024 * i,
                was_truncated=(i % 2 == 0)
            )
        
        stats = db.get_statistics()
        
        assert stats['total_messages'] == 5
        assert stats['successful_executions'] == 5
        assert stats['truncated_messages'] == 3  # 0, 2, 4
        
        print(f"✅ Statistics: {stats}")
    
    Path(test_db).unlink()

if __name__ == "__main__":
    test_statistics()
```

**Pass Criteria:** Statistics calculated correctly

---

#### Test 2.4: Search Messages
```python
# test_search.py
from database import MessageDatabase
from pathlib import Path

def test_search_messages():
    """Test message search functionality."""
    test_db = 'test_relay_agent.db'
    Path(test_db).unlink(missing_ok=True)
    
    with MessageDatabase(test_db) as db:
        # Insert test messages
        db.insert_message(
            message_text="Python is awesome",
            message_text_original="Python is awesome",
            facebook_url="https://facebook.com/msg1",
            screenshot_path="test1.png",
            screenshot_size_bytes=100
        )
        
        db.insert_message(
            message_text="JavaScript is cool",
            message_text_original="JavaScript is cool",
            facebook_url="https://facebook.com/msg2",
            screenshot_path="test2.png",
            screenshot_size_bytes=100
        )
        
        # Search for Python
        results = db.search_messages("Python")
        assert len(results) == 1
        assert "Python" in results[0]['message_text']
        print(f"✅ Search found: {results[0]['message_text']}")
    
    Path(test_db).unlink()

if __name__ == "__main__":
    test_search_messages()
```

**Pass Criteria:** Search returns correct results

---

### 3. Screenshot Tests

#### Test 3.1: Screenshot Capture
```python
# test_screenshot_capture.py
# ⚠️ Requires running browser and Facebook page

from playwright.sync_api import sync_playwright
from screenshot_capture import capture_message_screenshot
from pathlib import Path
import logging

logging.basicConfig(level=logging.INFO)

def test_screenshot_capture():
    """Test screenshot capture functionality."""
    print("⚠️ This test requires authenticated Facebook session")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False)
        context = browser.new_context(storage_state='auth_facebook.json')
        page = context.new_page()
        
        try:
            # Navigate to test page
            page.goto('https://facebook.com')
            page.wait_for_load_state('networkidle')
            
            # Find any visible element to screenshot
            locator = page.locator('body').first
            
            screenshot_path, size, metadata = capture_message_screenshot(
                page, locator, page.url, "Test screenshot"
            )
            
            assert screenshot_path.exists()
            assert size > 100
            assert metadata['screenshot_size_bytes'] == size
            
            print(f"✅ Screenshot captured: {screenshot_path}")
            print(f"✅ Size: {size} bytes")
            
            # Cleanup
            screenshot_path.unlink()
            
        finally:
            browser.close()

if __name__ == "__main__":
    test_screenshot_capture()
```

**Pass Criteria:** Screenshot captured and validated

---

#### Test 3.2: Screenshot Metadata
```python
# test_screenshot_metadata.py
import json
from pathlib import Path
from screenshot_capture import capture_message_screenshot
from playwright.sync_api import sync_playwright

def test_screenshot_metadata():
    """Test metadata JSON generation."""
    # This is a simpler test using a basic page
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto('https://example.com')
        
        locator = page.locator('body')
        
        screenshot_path, size, metadata = capture_message_screenshot(
            page, locator, page.url, "Test message"
        )
        
        # Check metadata structure
        assert 'timestamp' in metadata
        assert 'source_url' in metadata
        assert 'extracted_text' in metadata
        assert 'screenshot_path' in metadata
        assert 'screenshot_size_bytes' in metadata
        
        # Check metadata file exists
        metadata_file = Path('screenshots') / f"metadata_{metadata['timestamp']}.json"
        assert metadata_file.exists()
        
        # Verify JSON is valid
        with open(metadata_file) as f:
            loaded = json.load(f)
            assert loaded['extracted_text'] == "Test message"
        
        print(f"✅ Metadata valid: {metadata_file}")
        
        # Cleanup
        screenshot_path.unlink()
        metadata_file.unlink()
        browser.close()

if __name__ == "__main__":
    test_screenshot_metadata()
```

**Pass Criteria:** Metadata JSON created and valid

---

### 4. Query CLI Tests

#### Test 4.1: Recent Messages Query
```bash
# Create test database with sample data first
python -c "
from database import MessageDatabase
db = MessageDatabase('test_relay_agent.db')
for i in range(3):
    db.insert_message(
        message_text=f'Test {i}',
        message_text_original=f'Test {i}',
        facebook_url=f'http://test{i}',
        screenshot_path=f'test{i}.png',
        screenshot_size_bytes=100
    )
db.close()
"

# Test query
DATABASE_PATH=test_relay_agent.db python query_database.py --recent 3
```

**Pass Criteria:** Shows 3 recent messages in table format

---

#### Test 4.2: Statistics Query
```bash
DATABASE_PATH=test_relay_agent.db python query_database.py --stats
```

**Pass Criteria:** Shows database statistics

---

### 5. Backup Tests

#### Test 5.1: Backup Creation
```python
# test_backup.py
from backup_database import backup_database
from pathlib import Path
import shutil

def test_backup_creation():
    """Test backup creation."""
    # Create test database
    Path('relay_agent.db').touch()
    Path('screenshots').mkdir(exist_ok=True)
    Path('screenshots/test.png').touch()
    
    # Create backup
    backup_dir = backup_database()
    
    # Verify backup exists
    assert backup_dir.exists()
    assert (backup_dir / 'relay_agent.db').exists()
    assert (backup_dir / 'screenshots' / 'test.png').exists()
    
    print(f"✅ Backup created: {backup_dir}")
    
    # Cleanup
    shutil.rmtree('backups')
    Path('relay_agent.db').unlink()
    shutil.rmtree('screenshots')

if __name__ == "__main__":
    test_backup_creation()
```

**Pass Criteria:** Backup directory created with files

---

### 6. End-to-End Integration Tests

#### Test 6.1: Complete Phase 3 Flow
```python
# test_phase3_e2e.py
# ⚠️ Full integration test

from playwright.sync_api import sync_playwright
from pathlib import Path
import logging

import config
from utils.browser_config import create_browser_context
from facebook_extractor import navigate_to_message, extract_message_text
from utils.selector_strategies import MESSAGE_SELECTORS, try_selectors
from screenshot_capture import refocus_facebook_page, capture_message_screenshot
from database import MessageDatabase

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def test_phase3_complete():
    """Test complete Phase 3 flow."""
    print("\n" + "="*50)
    print("Phase 3 End-to-End Test")
    print("="*50 + "\n")
    
    test_db = 'test_relay_agent.db'
    Path(test_db).unlink(missing_ok=True)
    
    with MessageDatabase(test_db) as db:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=False)
            context = create_browser_context(browser, 'auth_facebook.json')
            page = context.new_page()
            
            try:
                # Extract message (from Phase 1)
                navigate_to_message(page, config.FACEBOOK_MESSAGE_URL)
                message_text = extract_message_text(page)
                
                # Get message locator
                message_locator = try_selectors(page, MESSAGE_SELECTORS)
                
                # Capture screenshot
                logger.info("Capturing screenshot...")
                screenshot_path, size, metadata = capture_message_screenshot(
                    page, message_locator, config.FACEBOOK_MESSAGE_URL, message_text
                )
                
                assert screenshot_path.exists()
                assert size > 100
                
                # Store in database
                logger.info("Storing in database...")
                message_id = db.insert_message(
                    message_text=message_text,
                    message_text_original=message_text,
                    facebook_url=config.FACEBOOK_MESSAGE_URL,
                    screenshot_path=str(screenshot_path),
                    screenshot_size_bytes=size,
                    was_truncated=False,
                    execution_status='success'
                )
                
                assert message_id > 0
                
                # Verify retrieval
                msg = db.get_message_by_id(message_id)
                assert msg is not None
                assert msg['screenshot_path'] == str(screenshot_path)
                
                print("\n" + "="*50)
                print("✅ PHASE 3 COMPLETE")
                print(f"Message ID: {message_id}")
                print(f"Screenshot: {screenshot_path}")
                print(f"Size: {size} bytes")
                print("="*50 + "\n")
                
            finally:
                browser.close()
    
    Path(test_db).unlink()

if __name__ == "__main__":
    test_phase3_complete()
```

**Pass Criteria:** Screenshot captured and stored in database

---

## Manual Testing Checklist

### Database Tests
- [ ] Database file created on first run
- [ ] Tables created correctly
- [ ] Indexes improve query performance
- [ ] Message inserts successfully
- [ ] Duplicate detection prevents re-processing
- [ ] Statistics query returns correct counts
- [ ] Search finds messages by text
- [ ] Foreign key constraints enforced

### Screenshot Tests
- [ ] Re-focus on Facebook page works
- [ ] Screenshot captures correctly
- [ ] Screenshot file size > 100 bytes
- [ ] Metadata JSON created
- [ ] Metadata contains correct data
- [ ] Screenshots accessible from database records

### CLI Tool Tests
- [ ] `--recent N` shows N messages
- [ ] `--search "query"` finds messages
- [ ] `--stats` shows statistics
- [ ] `--message-id N` shows specific message
- [ ] `--export file.json` creates JSON export
- [ ] Table formatting is readable

### Backup Tests
- [ ] Backup directory created with timestamp
- [ ] Database file backed up
- [ ] Screenshots directory backed up
- [ ] Old backups cleaned up (after 7 days)
- [ ] Backup can be restored manually

### Integration Tests
- [ ] Complete Phases 1-3 run end-to-end
- [ ] All data persists correctly
- [ ] Duplicate check prevents re-run
- [ ] Auto-backup works if enabled

---

## Performance Benchmarks

### Database Operations
- Insert message: < 10ms
- Query by ID: < 5ms
- Search messages: < 50ms (for 1000 records)
- Statistics query: < 20ms

### Screenshot Operations
- Capture screenshot: 2-5 seconds
- Save metadata: < 100ms

### Backup Operations
- Backup database: < 1 second
- Backup screenshots: depends on count (1-10 seconds)

---

## ✅ Phase 3 Test Completion Criteria

- [ ] All unit tests pass
- [ ] Database operations work correctly
- [ ] Screenshot capture succeeds
- [ ] Metadata generation works
- [ ] Duplicate detection functions
- [ ] Query CLI tool works
- [ ] Backup utility succeeds
- [ ] End-to-end test completes
- [ ] Manual checklist completed
- [ ] Performance benchmarks met

**When all criteria are met, Phase 3 is fully tested!** ✅


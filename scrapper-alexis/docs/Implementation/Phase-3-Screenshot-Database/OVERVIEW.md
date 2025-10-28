# Phase 3: Screenshot & Database Storage

## 🎯 Objective
Capture screenshot of the Facebook message and store all execution data in SQLite database for audit trail and record-keeping.

## 📋 Prerequisites
- Phases 0, 1, and 2 completed
- Message text extracted from Phase 1
- Message posted to X in Phase 2
- SQLite (built into Python)

## ⏱️ Estimated Time
2-3 hours

## 🏗️ Architecture Overview

### Component Structure
```
Phase 3 Components:
├── screenshot_capture.py      # Screenshot logic
├── database.py                # Database abstraction layer
├── schema.sql                 # Database schema definition
├── query_database.py          # CLI query tool
└── backup_database.py         # Backup utility
```

### Data Flow
```
1. Re-focus on Facebook page
2. Wait for message element visibility
3. Capture element screenshot
4. Save screenshot with timestamp
5. Generate metadata JSON
6. Insert record into SQLite database:
   - Message text (original & truncated)
   - Screenshot path and size
   - Execution timestamps
   - Success/failure status
7. Log execution phases to audit trail
8. Optional: Auto-backup database
```

## 📊 Database Schema

### Tables

#### messages
```sql
- id (PRIMARY KEY)
- message_text (posted version)
- message_text_original (before truncation)
- message_length
- was_truncated
- facebook_url
- facebook_message_id
- screenshot_path
- screenshot_filename
- screenshot_size_bytes
- x_post_url (optional)
- x_posted_at
- execution_status
- execution_error
- created_at
- updated_at
```

#### execution_log
```sql
- id (PRIMARY KEY)
- message_id (FOREIGN KEY)
- phase (facebook_auth, extraction, x_auth, posting, screenshot, database)
- status (started, completed, failed)
- duration_seconds
- error_message
- created_at
```

## 🔧 Key Components

### 1. Screenshot Capture
```python
# Element-specific screenshot
locator.screenshot(path='screenshots/fb_message_{timestamp}.png')

# With validation
- Verify file created
- Check file size > 100 bytes
- Save metadata JSON
```

### 2. Database Operations
```python
class MessageDatabase:
    - insert_message() -> message_id
    - log_execution_phase()
    - get_message_by_id()
    - get_recent_messages()
    - message_exists() -> bool (duplicate check)
    - search_messages()
    - get_statistics()
```

### 3. Metadata Storage
```json
{
  "timestamp": "20241009_143022",
  "source_url": "https://facebook.com/...",
  "extracted_text": "...",
  "posted_to_x": true,
  "screenshot_path": "screenshots/fb_message_20241009_143022.png",
  "screenshot_size_bytes": 45678
}
```

## 📁 File Structure Updates
```
project/
├── screenshot_capture.py         # NEW
├── database.py                   # NEW
├── schema.sql                    # NEW
├── query_database.py             # NEW
├── backup_database.py            # NEW
├── relay_agent.db                # AUTO-GENERATED
├── screenshots/                  
│   ├── fb_message_*.png         # AUTO-GENERATED
│   └── metadata_*.json          # AUTO-GENERATED
├── backups/                      # AUTO-GENERATED
│   └── 20241009_143022/
└── relay_agent.py                # UPDATED with Phase 3
```

## 🔐 Security Considerations
- **Screenshot Privacy**: May contain personal information
- **Database Security**: Contains message history
- **Backup Strategy**: Encrypted backups recommended
- **Retention Policy**: Implement data cleanup for old records

## ✅ Acceptance Criteria
- [ ] Re-focus on Facebook page succeeds
- [ ] Message element screenshot captures correctly
- [ ] Screenshot file validates (size > 100 bytes)
- [ ] Metadata JSON saves with screenshot
- [ ] Database schema creates successfully
- [ ] Message record inserts into database
- [ ] Execution log tracks all phases
- [ ] Duplicate prevention works (24-hour window)
- [ ] Query CLI tool works
- [ ] Backup utility functions correctly
- [ ] Statistics query returns correct data
- [ ] Search functionality works

## 🚧 Known Challenges
1. **Element Re-location**: Message might move in DOM after navigation
2. **Screenshot Size**: Large screenshots consume disk space
3. **Database Growth**: Implement cleanup/archival strategy
4. **Concurrent Access**: SQLite doesn't support high concurrency
5. **Backup Timing**: Coordinate with automation schedule


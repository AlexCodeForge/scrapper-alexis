"""
Database management module for the Facebook scraper.

Handles SQLite database operations, schema management, and data persistence
for profiles, messages, and scraping sessions.
"""

import sqlite3
import hashlib
import logging
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Optional, Tuple
from contextlib import contextmanager
import config

logger = logging.getLogger(__name__)


class DatabaseManager:
    """Manages SQLite database operations for the scraper."""
    
    def __init__(self, db_path: str = None):
        """
        Initialize database manager.
        
        Args:
            db_path: Path to SQLite database file (defaults to config.DATABASE_PATH)
        """
        self.db_path = Path(db_path or config.DATABASE_PATH)
        self.ensure_database_exists()
    
    def ensure_database_exists(self):
        """Create database and tables if they don't exist."""
        with self.get_connection() as conn:
            self._create_tables(conn)
            self._migrate_database(conn)
            logger.info(f"Database initialized at {self.db_path}")
    
    def _migrate_database(self, conn: sqlite3.Connection):
        """Apply database migrations for schema updates."""
        try:
            # Check if avatar_url column exists, if not add it
            cursor = conn.execute("PRAGMA table_info(messages)")
            columns = [row[1] for row in cursor.fetchall()]

            if 'avatar_url' not in columns:
                logger.info("Adding avatar_url column to messages table...")
                conn.execute("ALTER TABLE messages ADD COLUMN avatar_url TEXT")
                conn.commit()
                logger.info("✅ Avatar URL column added successfully")
            
            if 'image_generated' not in columns:
                logger.info("Adding image_generated column to messages table...")
                conn.execute("ALTER TABLE messages ADD COLUMN image_generated BOOLEAN DEFAULT 0")
                conn.commit()
                logger.info("✅ Image generated column added successfully")
            
            if 'image_path' not in columns:
                logger.info("Adding image_path column to messages table...")
                conn.execute("ALTER TABLE messages ADD COLUMN image_path TEXT")
                conn.commit()
                logger.info("✅ Image path column added successfully")
            
            if 'auto_post_enabled' not in columns:
                logger.info("Adding auto_post_enabled column to messages table...")
                conn.execute("ALTER TABLE messages ADD COLUMN auto_post_enabled BOOLEAN DEFAULT 1")
                conn.commit()
                logger.info("✅ Auto post enabled column added successfully")
            
            if 'approval_type' not in columns:
                logger.info("Adding approval_type column to messages table...")
                conn.execute("ALTER TABLE messages ADD COLUMN approval_type TEXT")
                conn.commit()
                logger.info("✅ Approval type column added successfully")

        except Exception as e:
            logger.warning(f"Migration warning (non-critical): {e}")
    
    @contextmanager
    def get_connection(self):
        """Context manager for database connections with proper UTF-8 encoding."""
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row  # Enable column access by name
        # Ensure proper UTF-8 encoding
        conn.execute("PRAGMA encoding = 'UTF-8'")
        conn.text_factory = str  # Ensure text is returned as str, not bytes
        try:
            yield conn
        finally:
            conn.close()
    
    def _create_tables(self, conn: sqlite3.Connection):
        """Create database tables."""
        
        # Profiles table
        conn.execute('''
            CREATE TABLE IF NOT EXISTS profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                url TEXT NOT NULL UNIQUE,
                credentials_reference TEXT,
                last_scraped_at TIMESTAMP,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        # Messages table
        conn.execute('''
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                profile_id INTEGER NOT NULL,
                message_text TEXT NOT NULL,
                message_hash TEXT NOT NULL UNIQUE,
                scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                posted_to_twitter BOOLEAN DEFAULT 0,
                posted_at TIMESTAMP,
                post_url TEXT,
                avatar_url TEXT,
                auto_post_enabled BOOLEAN DEFAULT 1,
                approval_type TEXT,
                FOREIGN KEY (profile_id) REFERENCES profiles (id)
            )
        ''')
        
        # Scraping sessions table
        conn.execute('''
            CREATE TABLE IF NOT EXISTS scraping_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                profile_id INTEGER NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP,
                messages_found INTEGER DEFAULT 0,
                messages_new INTEGER DEFAULT 0,
                stopped_reason TEXT,
                FOREIGN KEY (profile_id) REFERENCES profiles (id)
            )
        ''')
        
        # Create indexes for performance
        conn.execute('CREATE INDEX IF NOT EXISTS idx_messages_hash ON messages(message_hash)')
        conn.execute('CREATE INDEX IF NOT EXISTS idx_messages_profile ON messages(profile_id)')
        conn.execute('CREATE INDEX IF NOT EXISTS idx_messages_posted ON messages(posted_to_twitter)')
        conn.execute('CREATE INDEX IF NOT EXISTS idx_sessions_profile ON scraping_sessions(profile_id)')
        
        conn.commit()
        logger.debug("Database tables created successfully")
    
    # Profile Management
    def add_profile(self, username: str, url: str, credentials_reference: str = None) -> int:
        """
        Add a new profile to the database.
        
        Args:
            username: Profile username/identifier
            url: Facebook profile/group URL
            credentials_reference: Reference to credentials (e.g., line number in credenciales.txt)
            
        Returns:
            Profile ID
        """
        with self.get_connection() as conn:
            cursor = conn.execute(
                '''INSERT INTO profiles (username, url, credentials_reference) 
                   VALUES (?, ?, ?)''',
                (username, url, credentials_reference)
            )
            conn.commit()
            profile_id = cursor.lastrowid
            logger.info(f"Added profile {username} with ID {profile_id}")
            return profile_id
    
    def get_active_profiles(self) -> List[Dict]:
        """Get all active profiles."""
        with self.get_connection() as conn:
            cursor = conn.execute(
                'SELECT * FROM profiles WHERE is_active = 1 ORDER BY id'
            )
            profiles = [dict(row) for row in cursor.fetchall()]
            logger.debug(f"Retrieved {len(profiles)} active profiles")
            return profiles
    
    def update_profile_scraped_time(self, profile_id: int):
        """Update the last scraped timestamp for a profile."""
        with self.get_connection() as conn:
            conn.execute(
                'UPDATE profiles SET last_scraped_at = CURRENT_TIMESTAMP WHERE id = ?',
                (profile_id,)
            )
            conn.commit()
            logger.debug(f"Updated scraped time for profile {profile_id}")
    
    def deactivate_profile(self, profile_id: int):
        """Deactivate a profile."""
        with self.get_connection() as conn:
            conn.execute(
                'UPDATE profiles SET is_active = 0 WHERE id = ?',
                (profile_id,)
            )
            conn.commit()
            logger.info(f"Deactivated profile {profile_id}")
    
    # Message Management
    @staticmethod
    def generate_message_hash(message_text: str) -> str:
        """
        Generate a hash for message deduplication.
        
        BUGFIX: Must use same normalization as MessageDeduplicator
        to avoid hash mismatches between deduplicator checks and database inserts.
        """
        # BUGFIX: Import and use the same normalization as MessageDeduplicator
        # This ensures hash consistency across the entire system
        from core.message_deduplicator import MessageDeduplicator
        normalized = MessageDeduplicator.normalize_message_text(message_text)
        return hashlib.sha256(normalized.encode('utf-8')).hexdigest()
    
    def message_exists(self, message_text: str) -> bool:
        """Check if a message already exists in the database."""
        message_hash = self.generate_message_hash(message_text)
        with self.get_connection() as conn:
            cursor = conn.execute(
                'SELECT 1 FROM messages WHERE message_hash = ?',
                (message_hash,)
            )
            exists = cursor.fetchone() is not None
            logger.debug(f"Message exists check: {exists}")
            return exists
    
    def add_message(self, profile_id: int, message_text: str) -> Optional[int]:
        """
        Add a new message to the database.
        
        Args:
            profile_id: ID of the profile this message belongs to
            message_text: The message content
            
        Returns:
            Message ID if added, None if duplicate
        """
        message_hash = self.generate_message_hash(message_text)
        
        with self.get_connection() as conn:
            try:
                # BUGFIX: Include scraped_at timestamp (required NOT NULL field)
                from datetime import datetime
                scraped_at = datetime.now().isoformat()
                
                cursor = conn.execute(
                    '''INSERT INTO messages (profile_id, message_text, message_hash, scraped_at) 
                       VALUES (?, ?, ?, ?)''',
                    (profile_id, message_text, message_hash, scraped_at)
                )
                conn.commit()
                message_id = cursor.lastrowid
                logger.debug(f"Added message {message_id} for profile {profile_id}")
                return message_id
            except sqlite3.IntegrityError as e:
                # BUGFIX: Enhanced logging to understand why insert is failing
                # Message already exists (duplicate hash)
                logger.warning(f"Bugfix: IntegrityError inserting message for profile {profile_id}: {e}")
                logger.warning(f"Bugfix: Message text: {message_text[:100]}...")
                logger.warning(f"Bugfix: Message hash: {message_hash[:16]}...")
                # Check if this hash really exists
                check_cursor = conn.execute('SELECT id, message_text FROM messages WHERE message_hash = ?', (message_hash,))
                existing = check_cursor.fetchone()
                if existing:
                    logger.warning(f"Bugfix: Existing message ID {existing[0]}: {existing[1][:100]}...")
                return None
    
    def add_messages_batch(self, profile_id: int, messages: List[str]) -> Tuple[int, int]:
        """
        Add multiple messages in a batch operation.
        
        Args:
            profile_id: Profile ID
            messages: List of message texts
            
        Returns:
            Tuple of (new_messages_count, duplicate_messages_count)
        """
        new_count = 0
        duplicate_count = 0
        
        with self.get_connection() as conn:
            for message_text in messages:
                message_hash = self.generate_message_hash(message_text)
                try:
                    conn.execute(
                        '''INSERT INTO messages (profile_id, message_text, message_hash) 
                           VALUES (?, ?, ?)''',
                        (profile_id, message_text, message_hash)
                    )
                    new_count += 1
                except sqlite3.IntegrityError:
                    duplicate_count += 1
            
            conn.commit()
            logger.info(f"Batch insert: {new_count} new, {duplicate_count} duplicates")
            return new_count, duplicate_count
    
    def get_unposted_messages(self, limit: Optional[int] = None) -> List[Dict]:
        """Get messages that haven't been posted yet."""
        query = '''
            SELECT m.*, p.username as profile_username 
            FROM messages m
            JOIN profiles p ON m.profile_id = p.id
            WHERE m.posted_to_twitter = 0
            ORDER BY m.scraped_at ASC
        '''
        
        if limit:
            query += f' LIMIT {limit}'
        
        with self.get_connection() as conn:
            cursor = conn.execute(query)
            messages = [dict(row) for row in cursor.fetchall()]
            logger.debug(f"Retrieved {len(messages)} unposted messages")
            return messages
    
    def mark_message_posted(self, message_id: int, post_url: str = None, avatar_url: str = None):
        """Mark a message as posted with post URL and avatar URL."""
        with self.get_connection() as conn:
            conn.execute(
                '''UPDATE messages 
                   SET posted_to_twitter = 1, posted_at = CURRENT_TIMESTAMP, post_url = ?, avatar_url = ?
                   WHERE id = ?''',
                (post_url, avatar_url, message_id)
            )
            conn.commit()
            logger.info(f"Marked message {message_id} as posted (URL: {post_url}, Avatar: {avatar_url})")
    
    def get_message_stats(self) -> Dict:
        """Get statistics about messages in the database."""
        with self.get_connection() as conn:
            cursor = conn.execute('''
                SELECT 
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN posted_to_twitter = 1 THEN 1 END) as posted,
                    COUNT(CASE WHEN posted_to_twitter = 0 THEN 1 END) as unposted,
                    COUNT(DISTINCT profile_id) as profiles_with_messages
                FROM messages
            ''')
            stats = dict(cursor.fetchone())
            logger.debug(f"Message stats: {stats}")
            return stats
    
    # Scraping Session Management
    def start_scraping_session(self, profile_id: int) -> int:
        """Start a new scraping session."""
        with self.get_connection() as conn:
            cursor = conn.execute(
                'INSERT INTO scraping_sessions (profile_id) VALUES (?)',
                (profile_id,)
            )
            conn.commit()
            session_id = cursor.lastrowid
            logger.info(f"Started scraping session {session_id} for profile {profile_id}")
            return session_id
    
    def complete_scraping_session(self, session_id: int, messages_found: int, 
                                 messages_new: int, stopped_reason: str = "completed"):
        """Complete a scraping session with results."""
        with self.get_connection() as conn:
            conn.execute(
                '''UPDATE scraping_sessions 
                   SET completed_at = CURRENT_TIMESTAMP, messages_found = ?, 
                       messages_new = ?, stopped_reason = ?
                   WHERE id = ?''',
                (messages_found, messages_new, stopped_reason, session_id)
            )
            conn.commit()
            logger.info(f"Completed scraping session {session_id}: {messages_new}/{messages_found} new messages")
    
    def get_session_stats(self, profile_id: Optional[int] = None) -> List[Dict]:
        """Get scraping session statistics."""
        query = 'SELECT * FROM scraping_sessions'
        params = ()
        
        if profile_id:
            query += ' WHERE profile_id = ?'
            params = (profile_id,)
        
        query += ' ORDER BY started_at DESC'
        
        with self.get_connection() as conn:
            cursor = conn.execute(query, params)
            sessions = [dict(row) for row in cursor.fetchall()]
            logger.debug(f"Retrieved {len(sessions)} scraping sessions")
            return sessions
    
    # Utility Methods
    def cleanup_old_sessions(self, days: int = 30):
        """Clean up old scraping sessions."""
        with self.get_connection() as conn:
            cursor = conn.execute(
                '''DELETE FROM scraping_sessions 
                   WHERE started_at < datetime('now', '-{} days')'''.format(days)
            )
            conn.commit()
            logger.info(f"Cleaned up {cursor.rowcount} old sessions")
    
    # Image Generation Management
    def get_posted_messages_without_images(self, limit: int = 1) -> List[Dict]:
        """Get posted messages that don't have images generated yet.
        
        Only includes messages that were successfully posted,
        excluding messages that were skipped due to quality filtering or duplicates.
        """
        with self.get_connection() as conn:
            cursor = conn.execute('''
                SELECT m.id, m.message_text, m.post_url, m.avatar_url, m.posted_at,
                       p.username as profile_username
                FROM messages m
                JOIN profiles p ON m.profile_id = p.id
                WHERE m.posted_to_twitter = 1 
                AND (m.image_generated = 0 OR m.image_generated IS NULL)
                AND m.post_url IS NOT NULL
                AND m.post_url != 'SKIPPED_QUALITY_FILTER'
                AND m.post_url != 'DUPLICATE_SKIPPED'
                ORDER BY m.posted_at DESC
                LIMIT ?
            ''', (limit,))
            messages = [dict(row) for row in cursor.fetchall()]
            logger.debug(f"Found {len(messages)} posted messages without images (excluding skipped)")
            return messages
    
    def mark_image_generated(self, message_id: int, image_path: str) -> bool:
        """Mark a message as having its image generated."""
        try:
            with self.get_connection() as conn:
                conn.execute(
                    '''UPDATE messages 
                       SET image_generated = 1, image_path = ?
                       WHERE id = ?''',
                    (image_path, message_id)
                )
                conn.commit()
                logger.info(f"Marked message {message_id} as image generated: {image_path}")
                return True
        except Exception as e:
            logger.error(f"Failed to mark message {message_id} as image generated: {e}")
            return False
    
    def get_message_image_stats(self) -> Dict:
        """Get statistics about message images."""
        with self.get_connection() as conn:
            cursor = conn.execute('''
                SELECT 
                    COUNT(*) as total_posted_messages,
                    COUNT(CASE WHEN image_generated = 1 THEN 1 END) as images_generated,
                    COUNT(CASE WHEN image_generated = 0 OR image_generated IS NULL THEN 1 END) as images_pending
                FROM messages
                WHERE posted_to_twitter = 1
            ''')
            stats = dict(cursor.fetchone())
            logger.debug(f"Image stats: {stats}")
            return stats
    
    def get_database_stats(self) -> Dict:
        """Get overall database statistics."""
        with self.get_connection() as conn:
            # Get table counts
            stats = {}
            for table in ['profiles', 'messages', 'scraping_sessions']:
                cursor = conn.execute(f'SELECT COUNT(*) FROM {table}')
                stats[f'{table}_count'] = cursor.fetchone()[0]
            
            # Get database size
            stats['database_size_mb'] = self.db_path.stat().st_size / (1024 * 1024)
            
            logger.debug(f"Database stats: {stats}")
            return stats


# Global database instance
_db_instance = None


def get_database(db_path: str = None) -> DatabaseManager:
    """Get global database instance."""
    global _db_instance
    if _db_instance is None:
        _db_instance = DatabaseManager(db_path or config.DATABASE_PATH)
    return _db_instance


def initialize_database(db_path: str = None):
    """Initialize the database with the given path."""
    global _db_instance
    _db_instance = DatabaseManager(db_path or config.DATABASE_PATH)
    return _db_instance


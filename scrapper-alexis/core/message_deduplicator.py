"""
Message deduplication module for Facebook scraper.

Handles message comparison, hashing, and duplicate detection
to prevent re-scraping existing content.
"""

import hashlib
import logging
from typing import List, Set, Dict, Optional
from difflib import SequenceMatcher
from .database import get_database, DatabaseManager

logger = logging.getLogger(__name__)


class MessageDeduplicator:
    """Handles message deduplication and duplicate detection."""
    
    def __init__(self, db: DatabaseManager = None):
        """
        Initialize message deduplicator.
        
        Args:
            db: Database manager instance
        """
        self.db = db or get_database()
        self._hash_cache = {}  # Cache for computed hashes
    
    @staticmethod
    def normalize_message_text(text: str) -> str:
        """
        Normalize message text for consistent comparison.
        
        Args:
            text: Raw message text
            
        Returns:
            Normalized text for comparison
        """
        if not text:
            return ""
        
        # Convert to lowercase and strip whitespace
        normalized = text.strip().lower()
        
        # Remove extra whitespace and normalize line breaks
        import re
        normalized = re.sub(r'\s+', ' ', normalized)
        normalized = re.sub(r'\n+', '\n', normalized)
        
        # Remove common social media artifacts
        # Remove mentions and hashtags for comparison (but keep the text)
        # This is conservative - we only remove obvious UI elements
        social_artifacts = [
            'compartir', 'comentar', 'me gusta', 'reaccionar',
            'share', 'comment', 'like', 'react'
        ]
        
        for artifact in social_artifacts:
            pattern = rf'\b{re.escape(artifact)}\b'
            normalized = re.sub(pattern, '', normalized, flags=re.IGNORECASE)
        
        # Clean up extra spaces after artifact removal
        normalized = re.sub(r'\s+', ' ', normalized).strip()
        
        return normalized
    
    @staticmethod
    def generate_message_hash(text: str) -> str:
        """
        Generate a consistent hash for message text.
        
        Args:
            text: Message text
            
        Returns:
            SHA256 hash of normalized text
        """
        normalized = MessageDeduplicator.normalize_message_text(text)
        return hashlib.sha256(normalized.encode('utf-8')).hexdigest()
    
    def is_duplicate(self, message_text: str, profile_id: Optional[int] = None) -> bool:
        """
        Check if a message is a duplicate of existing content.
        
        Args:
            message_text: Text to check for duplicates
            profile_id: Optional profile ID to limit scope
            
        Returns:
            True if message is a duplicate
        """
        if not message_text or len(message_text.strip()) < 3:
            # Skip very short messages
            return True
        
        # Generate hash for the message
        message_hash = self.generate_message_hash(message_text)
        
        # Check if we've already computed this hash
        if message_hash in self._hash_cache:
            is_duplicate = self._hash_cache[message_hash]
            logger.debug(f"Hash found in cache - {'duplicate' if is_duplicate else 'unique'}")
            return is_duplicate
        
        # Check database for existing message with this hash
        exists = self.db.message_exists(message_text)
        
        if exists:
            # Add to cache for faster future lookups
            self._hash_cache[message_hash] = True
            logger.debug(f"Duplicate message detected: {message_text[:50]}...")
            return True
        else:
            # Add to cache as non-duplicate
            self._hash_cache[message_hash] = False
            logger.debug(f"New message detected: {message_text[:50]}...")
            return False
    
    def filter_duplicates(self, messages: List[str], profile_id: Optional[int] = None) -> List[str]:
        """
        Filter out duplicate messages from a list.
        
        Args:
            messages: List of message texts
            profile_id: Optional profile ID for context
            
        Returns:
            List of unique messages
        """
        unique_messages = []
        seen_hashes = set()
        
        logger.info(f"Filtering duplicates from {len(messages)} messages")
        
        for message in messages:
            if not message or len(message.strip()) < 3:
                continue
            
            # Check against our local set first (for this batch)
            message_hash = self.generate_message_hash(message)
            if message_hash in seen_hashes:
                logger.debug(f"Duplicate in batch: {message[:50]}...")
                continue
            
            # Check against database
            if not self.is_duplicate(message, profile_id):
                unique_messages.append(message)
                seen_hashes.add(message_hash)
            
        logger.info(f"Filtered to {len(unique_messages)} unique messages")
        return unique_messages
    
    def find_duplicate_threshold(self, messages: List[str]) -> Optional[int]:
        """
        Find the index where we first encounter a duplicate message.
        This is used to stop scraping when we hit existing content.
        
        Args:
            messages: List of scraped messages in chronological order
            
        Returns:
            Index of first duplicate, or None if no duplicates found
        """
        logger.info(f"Checking {len(messages)} messages for duplicate threshold")
        
        for i, message in enumerate(messages):
            if self.is_duplicate(message):
                logger.info(f"Found duplicate at index {i}: {message[:50]}...")
                return i
        
        logger.info("No duplicates found in message batch")
        return None
    
    def analyze_message_batch(self, messages: List[str], profile_id: Optional[int] = None) -> Dict:
        """
        Analyze a batch of messages for duplicates and provide statistics.
        
        Args:
            messages: List of message texts
            profile_id: Optional profile ID for context
            
        Returns:
            Dictionary with analysis results
        """
        logger.info(f"Analyzing batch of {len(messages)} messages")
        
        results = {
            'total_messages': len(messages),
            'unique_messages': 0,
            'duplicate_messages': 0,
            'first_duplicate_index': None,
            'should_stop_scraping': False,
            'unique_message_list': [],
            'duplicate_details': []
        }
        
        for i, message in enumerate(messages):
            if not message or len(message.strip()) < 3:
                continue
            
            if self.is_duplicate(message, profile_id):
                results['duplicate_messages'] += 1
                results['duplicate_details'].append({
                    'index': i,
                    'message': message[:100] + '...' if len(message) > 100 else message
                })
                
                # Mark first duplicate
                if results['first_duplicate_index'] is None:
                    results['first_duplicate_index'] = i
                    results['should_stop_scraping'] = True
                    logger.info(f"First duplicate found at index {i} - should stop scraping")
            else:
                results['unique_messages'] += 1
                results['unique_message_list'].append(message)
        
        logger.info(f"Analysis complete: {results['unique_messages']} unique, "
                   f"{results['duplicate_messages']} duplicates")
        
        return results
    
    def get_similar_messages(self, text: str, threshold: float = 0.8, limit: int = 5) -> List[Dict]:
        """
        Find messages similar to the given text using fuzzy matching.
        This is for debugging and analysis purposes.
        
        Args:
            text: Text to find similar messages for
            threshold: Similarity threshold (0.0 to 1.0)
            limit: Maximum number of results
            
        Returns:
            List of similar messages with similarity scores
        """
        logger.debug(f"Finding messages similar to: {text[:50]}...")
        
        # Get recent messages from database for comparison
        recent_messages = self.db.get_unposted_messages(limit=100)
        
        similar_messages = []
        normalized_input = self.normalize_message_text(text)
        
        for msg_record in recent_messages:
            msg_text = msg_record['message_text']
            normalized_msg = self.normalize_message_text(msg_text)
            
            # Calculate similarity
            similarity = SequenceMatcher(None, normalized_input, normalized_msg).ratio()
            
            if similarity >= threshold:
                similar_messages.append({
                    'message_id': msg_record['id'],
                    'message_text': msg_text,
                    'similarity': similarity,
                    'profile_id': msg_record['profile_id']
                })
        
        # Sort by similarity (highest first)
        similar_messages.sort(key=lambda x: x['similarity'], reverse=True)
        
        logger.debug(f"Found {len(similar_messages)} similar messages")
        return similar_messages[:limit]
    
    def clear_cache(self):
        """Clear the internal hash cache."""
        self._hash_cache.clear()
        logger.debug("Hash cache cleared")
    
    def get_cache_stats(self) -> Dict:
        """Get statistics about the internal cache."""
        total_entries = len(self._hash_cache)
        duplicates = sum(1 for v in self._hash_cache.values() if v)
        unique = total_entries - duplicates
        
        stats = {
            'total_cached_hashes': total_entries,
            'cached_duplicates': duplicates,
            'cached_unique': unique
        }
        
        logger.debug(f"Cache stats: {stats}")
        return stats
    
    def preload_existing_hashes(self, profile_id: Optional[int] = None, limit: int = 1000):
        """
        Preload message hashes from database into cache for faster lookups.
        
        Args:
            profile_id: Optional profile ID to limit scope
            limit: Maximum number of hashes to preload
        """
        logger.info(f"Preloading message hashes (limit: {limit})")
        
        # This would require a database query to get existing message hashes
        # For now, we'll implement this as needed
        # The cache will be populated naturally as we check for duplicates
        
        logger.debug("Hash preloading completed")


class MessageQualityFilter:
    """Additional filtering for message quality and relevance."""
    
    @staticmethod
    def is_valid_message(text: str) -> bool:
        """
        Check if a message meets quality criteria.
        
        Args:
            text: Message text to validate
            
        Returns:
            True if message meets quality standards
        """
        if not text or not isinstance(text, str):
            return False
        
        text = text.strip()
        
        # Minimum length check
        if len(text) < 5:
            return False
        
        # Maximum length check (avoid extremely long posts)
        if len(text) > 2000:
            return False
        
        # Skip obvious UI elements
        ui_elements = [
            'compartir', 'comentar', 'me gusta', 'reaccionar',
            'share', 'comment', 'like', 'react', 'loading',
            'ver más', 'see more', 'mostrar más'
        ]
        
        text_lower = text.lower()
        if any(element in text_lower and len(text) < 50 for element in ui_elements):
            return False
        
        # Check word count - must have more than 4 words
        words = text.split()
        if len(words) <= 4:
            return False
        
        # Skip messages with excessive special characters
        special_chars = sum(1 for c in text if not c.isalnum() and not c.isspace())
        if special_chars > len(text) * 0.3:  # More than 30% special characters
            return False
        
        return True
    
    @staticmethod
    def filter_quality_messages(messages: List[str]) -> List[str]:
        """
        Filter messages based on quality criteria.
        
        Args:
            messages: List of message texts
            
        Returns:
            List of quality messages
        """
        quality_messages = []
        
        for message in messages:
            if MessageQualityFilter.is_valid_message(message):
                quality_messages.append(message)
        
        logger.info(f"Quality filter: {len(quality_messages)}/{len(messages)} messages passed")
        return quality_messages


# Global deduplicator instance
_deduplicator_instance = None


def get_message_deduplicator() -> MessageDeduplicator:
    """Get global message deduplicator instance."""
    global _deduplicator_instance
    if _deduplicator_instance is None:
        _deduplicator_instance = MessageDeduplicator()
    return _deduplicator_instance


"""
Profile management module for handling multiple Facebook profiles.

Manages profiles from environment variables and database for multi-profile scraping operations.
"""

import re
import logging
from typing import List, Dict, Tuple, Optional
from pathlib import Path
from .database import get_database, DatabaseManager
import config

logger = logging.getLogger(__name__)


class ProfileManager:
    """Manages multiple Facebook profiles for scraping."""
    
    def __init__(self, db: DatabaseManager = None):
        """
        Initialize profile manager.
        
        Args:
            db: Database manager instance
        """
        self.db = db or get_database()
        self.profiles = []
        self.credentials = {}
    
    def parse_profiles_from_env(self) -> Dict:
        """
        Parse Facebook profiles from database via config module.
        
        Returns:
            Dictionary containing parsed credentials and Facebook group URLs
        """
        logger.info("Loading profiles from database via config module")
        
        # Use config module which loads from database (no .env fallback)
        profile_urls = config.FACEBOOK_PROFILES
        
        if not profile_urls:
            logger.error("No Facebook profiles found in database")
            return {}
        
        logger.info(f"Found {len(profile_urls)} Facebook profiles from database")
        
        # Get Facebook credentials from config (loaded from database)
        facebook_credentials = {
            'username': config.FACEBOOK_EMAIL,
            'password': config.FACEBOOK_PASSWORD
        }
        
        # Get Twitter credentials from config (loaded from database)  
        twitter_credentials = {
            'username': config.X_EMAIL,
            'password': config.X_PASSWORD
        }
        
        if not facebook_credentials['username'] or not facebook_credentials['password']:
            logger.error("Facebook credentials not found in database")
            return {}
        
        logger.info("Found Facebook credentials")
        if twitter_credentials['username'] and twitter_credentials['password']:
            logger.info("Found Twitter credentials")
        
        logger.info(f"Parsed {len(profile_urls)} Facebook group URLs")
        
        return {
            'facebook_groups': profile_urls,
            'facebook_credentials': facebook_credentials,
            'twitter_credentials': twitter_credentials
        }
    
    def extract_profile_id_from_url(self, url: str) -> str:
        """
        Extract a unique profile identifier from a Facebook URL.
        
        Args:
            url: Facebook URL
            
        Returns:
            Profile identifier
        """
        # Extract ID from share URLs like https://www.facebook.com/share/1E8ChgJj5b/
        share_match = re.search(r'/share/([^/?]+)', url)
        if share_match:
            return share_match.group(1)
        
        # Extract from other Facebook URL patterns
        # Pattern: facebook.com/profile.php?id=123
        id_match = re.search(r'[?&]id=([^&]+)', url)
        if id_match:
            return id_match.group(1)
        
        # Pattern: facebook.com/username
        username_match = re.search(r'facebook\.com/([^/?]+)', url)
        if username_match:
            return username_match.group(1)
        
        # Fallback: use hash of URL
        import hashlib
        return hashlib.md5(url.encode()).hexdigest()[:10]
    
    def sync_profiles_to_database(self) -> List[Dict]:
        """
        Sync parsed profiles to the database.
        Deactivates profiles not in .env and activates/creates profiles from .env.
        
        Returns:
            List of profile dictionaries with database IDs
        """
        parsed_data = self.parse_profiles_from_env()
        
        if not parsed_data or not parsed_data.get('facebook_groups'):
            logger.error("No profiles to sync")
            return []
        
        # Store credentials for later use
        self.credentials = {
            'facebook': parsed_data.get('facebook_credentials', {}),
            'twitter': parsed_data.get('twitter_credentials', {})
        }
        
        # Get URLs from .env
        env_urls = set(parsed_data['facebook_groups'])
        
        # Get all existing profiles from database (active or not)
        with self.db.get_connection() as conn:
            cursor = conn.execute('SELECT id, url, is_active FROM profiles')
            all_db_profiles = [dict(row) for row in cursor.fetchall()]
        
        # Deactivate profiles not in .env
        for db_profile in all_db_profiles:
            if db_profile['url'] not in env_urls and db_profile['is_active']:
                logger.info(f"Deactivating profile no longer in .env: {db_profile['url']}")
                self.db.deactivate_profile(db_profile['id'])
        
        # Activate or create profiles from .env
        profiles = []
        for url in env_urls:
            profile_id = self.extract_profile_id_from_url(url)
            
            # Check if profile already exists
            existing_profile = next((p for p in all_db_profiles if p['url'] == url), None)
            
            if existing_profile:
                # Profile exists, activate it if needed
                db_profile_id = existing_profile['id']
                if not existing_profile['is_active']:
                    logger.info(f"Reactivating profile: {url}")
                    with self.db.get_connection() as conn:
                        conn.execute('UPDATE profiles SET is_active = 1 WHERE id = ?', (db_profile_id,))
                        conn.commit()
            else:
                # Profile doesn't exist, create it
                try:
                    db_profile_id = self.db.add_profile(
                        username=profile_id,
                        url=url,
                        credentials_reference='env'
                    )
                    logger.info(f"Created new profile: {url}")
                except Exception as e:
                    logger.error(f"Error adding profile {profile_id}: {e}")
                    continue
            
            profiles.append({
                'id': db_profile_id,
                'username': profile_id,
                'url': url
            })
        
        self.profiles = profiles
        logger.info(f"Synced {len(profiles)} profiles to database ({len(env_urls)} active)")
        return profiles
    
    def get_active_profiles(self, limit: Optional[int] = None) -> List[Dict]:
        """
        Get active profiles for scraping.
        
        Args:
            limit: Maximum number of profiles to return
            
        Returns:
            List of active profiles
        """
        if not self.profiles:
            self.sync_profiles_to_database()
        
        active_profiles = [p for p in self.profiles]
        
        if limit:
            active_profiles = active_profiles[:limit]
        
        return active_profiles
    
    def get_facebook_credentials(self) -> Optional[Dict[str, str]]:
        """
        Get Facebook login credentials.
        
        Returns:
            Dictionary with username and password, or None if not found
        """
        # Bugfix: parse_profiles_from_env() returns a dict but doesn't save to self.credentials
        # We need to call it and extract facebook_credentials from the returned data
        data = self.parse_profiles_from_env()
        
        if not data:
            logger.error("Bugfix: parse_profiles_from_env() returned empty data")
            return None
        
        facebook_creds = data.get('facebook_credentials')
        
        if not facebook_creds:
            logger.error("Bugfix: No facebook_credentials in parsed data")
            return None
        
        logger.info(f"Bugfix: Returning Facebook credentials for user: {facebook_creds.get('username', 'unknown')}")
        return facebook_creds
    
    def get_twitter_credentials(self) -> Optional[Dict[str, str]]:
        """
        Get Twitter login credentials.
        
        Returns:
            Dictionary with username and password, or None if not found
        """
        # Bugfix: Same fix as get_facebook_credentials()
        data = self.parse_profiles_from_env()
        
        if not data:
            logger.error("Bugfix: parse_profiles_from_env() returned empty data")
            return None
        
        twitter_creds = data.get('twitter_credentials')
        
        if not twitter_creds:
            logger.error("Bugfix: No twitter_credentials in parsed data")
            return None
        
        logger.info(f"Bugfix: Returning Twitter credentials for user: {twitter_creds.get('username', 'unknown')}")
        return twitter_creds
    
    def mark_profile_as_scraped(self, profile_id: int) -> bool:
        """
        Mark a profile as successfully scraped.
        
        Args:
            profile_id: Database profile ID
            
        Returns:
            True if successful
        """
        # This method exists for compatibility with relay_agent.py
        # The actual tracking is done in the database via scraping sessions
        logger.info(f"Profile {profile_id} marked as scraped (session tracking in database)")
        return True
    
    def get_profile_stats(self, profile_id: int) -> Dict:
        """
        Get scraping statistics for a profile.
        
        Args:
            profile_id: Database profile ID
            
        Returns:
            Statistics dictionary
        """
        # Get total messages for this profile
        total_messages = len(self.db.get_messages_by_profile(profile_id))
        
        # Get unposted messages for this profile
        unposted_messages = len([
            msg for msg in self.db.get_unposted_messages()
            if msg['profile_id'] == profile_id
        ])
        
        return {
            'total_messages': total_messages,
            'unposted_messages': unposted_messages,
            'posted_messages': total_messages - unposted_messages
        }
    
    def mark_profile_scraped(self, profile_id: int) -> bool:
        """
        Mark a profile as successfully scraped.
        
        Args:
            profile_id: Database profile ID
            
        Returns:
            True if successful
        """
        # This method exists for compatibility with relay_agent.py
        # The actual tracking is done in the database via scraping sessions
        logger.info(f"Profile {profile_id} marked as scraped (session tracking in database)")
        return True


# Global instance
_profile_manager = None

def get_profile_manager() -> ProfileManager:
    """Get global ProfileManager instance."""
    global _profile_manager
    if _profile_manager is None:
        _profile_manager = ProfileManager()
    return _profile_manager
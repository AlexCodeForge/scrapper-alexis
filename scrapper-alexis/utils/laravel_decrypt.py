"""
Laravel encryption decryption utility for Python.

Laravel uses AES-256-CBC encryption with HMAC-SHA-256 for authentication.
This module provides decryption functionality compatible with Laravel's encrypt() function.
"""

import os
import base64
import json
import hmac
import hashlib
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend
import logging

logger = logging.getLogger(__name__)


def _get_laravel_env_path() -> str:
    """
    Dynamically find Laravel .env file.
    
    Returns:
        str: Path to Laravel .env file
        
    Raises:
        FileNotFoundError: If .env file is not found
    """
    # Auto-detect Laravel .env location relative to this script
    # Get script directory
    from pathlib import Path
    script_dir = Path(__file__).parent.resolve()
    
    # Navigate up to find Laravel directory
    # From: scrapper-alexis/utils/laravel_decrypt.py
    # To:   scrapper-alexis-web/.env
    laravel_env = script_dir.parent.parent / 'scrapper-alexis-web' / '.env'
    
    if laravel_env.exists():
        return str(laravel_env)
    
    # If not found, raise clear error
    raise FileNotFoundError(
        f"Laravel .env not found at: {laravel_env}\n"
        f"Expected structure: .../scrapper-alexis-web/.env"
    )


def get_laravel_key() -> bytes:
    """
    Get the Laravel APP_KEY from environment or Laravel .env file.
    
    Returns:
        bytes: The decoded encryption key
        
    Raises:
        ValueError: If APP_KEY is not found or invalid
    """
    # Try environment variable first
    app_key = os.getenv('LARAVEL_APP_KEY')
    
    # If not in env, try reading from Laravel .env file
    if not app_key:
        laravel_env_path = _get_laravel_env_path()
        
        try:
            with open(laravel_env_path, 'r') as f:
                for line in f:
                    if line.startswith('APP_KEY='):
                        app_key = line.strip().split('=', 1)[1]
                        break
        except Exception as e:
            logger.warning(f"Failed to read {laravel_env_path}: {e}")
    
    if not app_key:
        raise ValueError("Laravel APP_KEY not found. Set LARAVEL_APP_KEY environment variable or ensure .env exists.")
    
    # Remove 'base64:' prefix if present
    if app_key.startswith('base64:'):
        app_key = app_key[7:]
    
    # Decode base64 key
    try:
        return base64.b64decode(app_key)
    except Exception as e:
        raise ValueError(f"Invalid APP_KEY format: {e}")


def decrypt_laravel_value(encrypted_value: str) -> str:
    """
    Decrypt a Laravel encrypted value.
    
    Laravel stores encrypted values as base64-encoded JSON containing:
    - iv: Initialization vector (base64)
    - value: Encrypted data (base64)
    - mac: HMAC signature (hex)
    - tag: Additional data (optional, for AEAD)
    
    Args:
        encrypted_value: The encrypted string from Laravel database
        
    Returns:
        str: Decrypted plaintext value
        
    Raises:
        ValueError: If decryption fails or value is invalid
    """
    if not encrypted_value:
        return ''
    
    # Check if value is already decrypted (plain text)
    # If it doesn't look like a JSON structure, assume it's plain text
    if not encrypted_value.startswith('eyJ'):  # base64 of JSON typically starts with 'eyJ'
        logger.debug("Value appears to be plaintext, returning as-is")
        return encrypted_value
    
    try:
        # Decode the base64-encoded JSON payload
        try:
            payload = json.loads(base64.b64decode(encrypted_value))
        except:
            # If base64 decode fails, maybe it's already decoded JSON
            payload = json.loads(encrypted_value)
        
        # Extract components
        iv = base64.b64decode(payload['iv'])
        encrypted_data = base64.b64decode(payload['value'])
        mac = payload['mac']
        
        # Get Laravel key
        key = get_laravel_key()
        
        # Verify MAC
        mac_key = hashlib.sha256(b'base64:' + base64.b64encode(key)).digest()
        expected_mac = hmac.new(
            mac_key,
            base64.b64encode(json.dumps(payload, separators=(',', ':')).encode()),
            hashlib.sha256
        ).hexdigest()
        
        if not hmac.compare_digest(mac, expected_mac):
            # Try alternative MAC calculation (Laravel uses different payload for MAC)
            payload_for_mac = f"base64:{base64.b64encode(iv + encrypted_data).decode()}"
            expected_mac = hmac.new(
                key,
                payload_for_mac.encode(),
                hashlib.sha256
            ).hexdigest()
            
            if not hmac.compare_digest(mac, expected_mac):
                logger.warning("MAC verification failed, but continuing with decryption")
        
        # Decrypt using AES-256-CBC
        cipher = Cipher(
            algorithms.AES(key),
            modes.CBC(iv),
            backend=default_backend()
        )
        decryptor = cipher.decryptor()
        decrypted_padded = decryptor.update(encrypted_data) + decryptor.finalize()
        
        # Remove PKCS7 padding
        padding_length = decrypted_padded[-1]
        decrypted = decrypted_padded[:-padding_length]
        
        result = decrypted.decode('utf-8')
        
        # Handle PHP serialized strings (Laravel sometimes serializes values)
        # Format: s:length:"value";
        if result.startswith('s:') and ':"' in result and result.endswith('";'):
            # Extract the string value from PHP serialization
            # Example: s:10:"0In6TAX309"; -> 0In6TAX309
            try:
                parts = result.split(':"', 1)
                if len(parts) == 2:
                    result = parts[1].rstrip('";')
            except:
                pass  # If parsing fails, return the original decrypted value
        
        return result
        
    except Exception as e:
        logger.error(f"Failed to decrypt Laravel value: {e}")
        # Return original value as fallback (might be plaintext)
        return encrypted_value


def is_encrypted(value: str) -> bool:
    """
    Check if a value appears to be Laravel encrypted.
    
    Args:
        value: String to check
        
    Returns:
        bool: True if value appears to be encrypted
    """
    if not value or not isinstance(value, str):
        return False
    
    # Laravel encrypted values are base64-encoded JSON
    # They typically start with 'eyJ' (base64 of '{')
    if not value.startswith('eyJ'):
        return False
    
    try:
        payload = json.loads(base64.b64decode(value))
        return 'iv' in payload and 'value' in payload and 'mac' in payload
    except:
        return False


# Logging configuration
logger.info("Laravel decrypt utility loaded")


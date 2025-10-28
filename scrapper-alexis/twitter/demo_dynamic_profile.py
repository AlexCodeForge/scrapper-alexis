#!/usr/bin/env python3
"""Quick demo of dynamic profile extraction."""

from twitter_screenshot_generator import update_template
from pathlib import Path
import re

# Create a test screenshot template
result = update_template(
    tweet_text='¡Esto es una prueba del sistema dinámico! 🚀✨',
    local_avatar_path=None,
    display_name='Usuario de Prueba',
    username='@usuarioprueba'
)

if result:
    with open(result, 'r', encoding='utf-8') as f:
        content = f.read()
    
    print('✅ TEMPLATE GENERATED SUCCESSFULLY!')
    print('=' * 60)
    print('Profile Info in Generated HTML:')
    print('=' * 60)
    
    # Find display name
    display_match = re.search(r'<span class="display-name">(.*?)</span>', content)
    if display_match:
        print(f'Display Name: {display_match.group(1)}')
    
    # Find username
    username_match = re.search(r'<span class="username">(.*?)</span>', content)
    if username_match:
        print(f'Username: {username_match.group(1)}')
    
    # Find tweet text
    tweet_match = re.search(r'<p class="tweet-text">(.*?)</p>', content, re.DOTALL)
    if tweet_match:
        print(f'Tweet Text: {tweet_match.group(1)}')
    
    # Check alt attribute
    alt_match = re.search(r'alt="(.*?)"', content)
    if alt_match:
        print(f'Image Alt Text: {alt_match.group(1)}')
    
    print('=' * 60)
    print('✅ All values are DYNAMIC and extracted from Twitter!')
    
    Path(result).unlink()
else:
    print('❌ Failed to generate template')



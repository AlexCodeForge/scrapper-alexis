#!/usr/bin/env python3
"""
Generate a clean log of posted messages and their images.
Simple format focused on what you need to see.
"""

import sqlite3
from datetime import datetime
from pathlib import Path

def generate_posting_log():
    """Generate clean posting log."""
    conn = sqlite3.connect('data/scraper.db')
    cursor = conn.cursor()
    
    log_file = Path('logs/message_posting_log.txt')
    
    with open(log_file, 'w', encoding='utf-8') as f:
        # Header
        f.write("=" * 80 + "\n")
        f.write("POSTED MESSAGES & IMAGES LOG\n")
        f.write(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write("=" * 80 + "\n\n")
        
        # Posted messages with images
        cursor.execute("""
            SELECT id, message_text, post_url, image_path, posted_at
            FROM messages 
            WHERE posted_to_twitter = 1 
            AND post_url IS NOT NULL
            AND post_url != 'SKIPPED_QUALITY_FILTER'
            ORDER BY posted_at DESC
        """)
        
        posted = cursor.fetchall()
        
        f.write(f"TOTAL POSTED: {len(posted)}\n\n")
        
        for msg in posted:
            msg_id, text, url, img_path, posted_at = msg
            
            # Check if image exists
            img_status = "✅" if img_path and Path(img_path).exists() else "❌ MISSING"
            
            f.write(f"ID {msg_id} | {posted_at}\n")
            f.write(f"  Message: {text}\n")
            f.write(f"  Twitter: {url}\n")
            if img_path:
                f.write(f"  Image:   {img_path} {img_status}\n")
            else:
                f.write(f"  Image:   ❌ No image generated\n")
            f.write("\n")
        
        # Statistics
        f.write("\n" + "=" * 80 + "\n")
        f.write("STATISTICS\n")
        f.write("=" * 80 + "\n\n")
        
        cursor.execute("""
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN posted_to_twitter = 1 AND post_url != 'SKIPPED_QUALITY_FILTER' THEN 1 ELSE 0 END) as posted,
                SUM(CASE WHEN post_url = 'SKIPPED_QUALITY_FILTER' THEN 1 ELSE 0 END) as skipped,
                SUM(CASE WHEN posted_to_twitter = 0 THEN 1 ELSE 0 END) as pending
            FROM messages
        """)
        total, posted, skipped, pending = cursor.fetchone()
        
        # Count images
        cursor.execute("""
            SELECT COUNT(*)
            FROM messages 
            WHERE posted_to_twitter = 1 
            AND post_url != 'SKIPPED_QUALITY_FILTER'
            AND image_generated = 1
        """)
        with_images = cursor.fetchone()[0]
        
        cursor.execute("""
            SELECT COUNT(*)
            FROM messages 
            WHERE posted_to_twitter = 1 
            AND post_url != 'SKIPPED_QUALITY_FILTER'
            AND (image_generated = 0 OR image_generated IS NULL)
        """)
        missing_images = cursor.fetchone()[0]
        
        f.write(f"Total messages in database: {total}\n")
        f.write(f"Successfully posted: {posted}\n")
        f.write(f"  - With images: {with_images}\n")
        f.write(f"  - Missing images: {missing_images}\n")
        f.write(f"Skipped (quality filter): {skipped}\n")
        f.write(f"Pending (not posted): {pending}\n")
        
        # Next in queue
        f.write("\n" + "=" * 80 + "\n")
        f.write("NEXT 10 IN QUEUE\n")
        f.write("=" * 80 + "\n\n")
        
        cursor.execute("""
            SELECT id, message_text, LENGTH(message_text) as len
            FROM messages 
            WHERE posted_to_twitter = 0
            ORDER BY id ASC
            LIMIT 10
        """)
        
        next_msgs = cursor.fetchall()
        for msg in next_msgs:
            msg_id, text, length = msg
            text_short = text[:60] + '...' if len(text) > 60 else text
            f.write(f"ID {msg_id} ({length} chars): {text_short}\n")
        
        f.write("\n" + "=" * 80 + "\n")
        f.write("END OF LOG\n")
        f.write("=" * 80 + "\n")
    
    conn.close()
    return log_file

if __name__ == "__main__":
    log_file = generate_posting_log()
    print(f"✅ Log generated: {log_file}")

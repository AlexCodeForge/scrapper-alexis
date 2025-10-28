#!/bin/bash
# Test Twitter Posting and Image Generation only
# Skips Facebook scraping (for when you already have messages in DB)

echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║      Twitter Posting + Image Generation Test                    ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

cd /var/www/scrapper-alexis

# Check prerequisites
echo "=== Checking Prerequisites ==="
echo ""

# Check if virtual environment exists
if [ -d "venv" ]; then
    echo "✓ Virtual environment found"
    source venv/bin/activate
else
    echo "⚠ Warning: Virtual environment not found"
fi

# Check if copy.env exists
if [ -f "copy.env" ]; then
    echo "✓ Configuration file (copy.env) found"
else
    echo "✗ ERROR: copy.env not found"
    exit 1
fi

# Check if database exists
if [ -f "data/scraper.db" ]; then
    echo "✓ Database found"
    # Check if there are unposted messages
    UNPOSTED=$(sqlite3 data/scraper.db "SELECT COUNT(*) FROM messages WHERE posted_to_twitter = 0;")
    echo "✓ Unposted messages: $UNPOSTED"
    if [ "$UNPOSTED" -eq 0 ]; then
        echo "⚠ WARNING: No unposted messages available!"
        echo "   Run Facebook scraper first to get messages."
        read -p "Continue anyway? (y/n) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
else
    echo "✗ ERROR: Database not found"
    echo "   Run Facebook scraper first to create database."
    exit 1
fi

# Create logs directory
mkdir -p logs
echo "✓ Logs directory ready"

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Test 1: Twitter Posting
echo "=== Test 1: Twitter Posting ==="
echo ""
echo "Running Twitter poster with Xvfb (VPS fix)..."
xvfb-run -a python3 -m twitter.twitter_post
TWITTER_EXIT=$?
echo ""
if [ $TWITTER_EXIT -eq 0 ]; then
    echo "✓ Twitter posting completed successfully"
else
    echo "✗ Twitter posting failed (exit code: $TWITTER_EXIT)"
    read -p "Continue to image generation anyway? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi
echo ""

# Test 2: Image Generation
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "=== Test 2: Image Generation ==="
echo ""
echo "Running image generator with Xvfb (VPS fix)..."
xvfb-run -a python3 generate_message_images.py
IMAGE_EXIT=$?
echo ""
if [ $IMAGE_EXIT -eq 0 ]; then
    echo "✓ Image generation completed successfully"
else
    echo "✗ Image generation failed (exit code: $IMAGE_EXIT)"
fi
echo ""

# Summary
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "=== Test Summary ==="
echo ""
if [ $TWITTER_EXIT -eq 0 ]; then
    echo "✓ Twitter Posting: SUCCESS"
else
    echo "✗ Twitter Posting: FAILED"
fi

if [ $IMAGE_EXIT -eq 0 ]; then
    echo "✓ Image Generation: SUCCESS"
else
    echo "✗ Image Generation: FAILED"
fi
echo ""

# Database stats
echo "=== Database Statistics ==="
sqlite3 data/scraper.db << EOF
SELECT 
    COUNT(*) as 'Total Messages',
    SUM(CASE WHEN posted_to_twitter = 1 THEN 1 ELSE 0 END) as 'Posted',
    SUM(CASE WHEN posted_to_twitter = 0 THEN 1 ELSE 0 END) as 'Pending',
    SUM(CASE WHEN image_generated = 1 THEN 1 ELSE 0 END) as 'Images'
FROM messages;
EOF
echo ""

echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "Tests completed!"
echo ""
echo "If all tests passed, you can now set up cronjobs:"
echo "  bash setup_cron.sh"
echo ""
echo "To run Twitter + Images again:"
echo "  bash test_twitter_and_images.sh"
echo ""





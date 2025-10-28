#!/bin/bash
# Test script to verify all workflows before setting up cronjobs
# Run this manually to ensure everything works correctly

echo "=== Social Media Relay Agent - Workflow Test ==="
echo ""
echo "This script will test each workflow in sequence."
echo "Press Ctrl+C at any time to abort."
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
    echo "  Consider creating one: python3 -m venv venv"
fi

# Check if copy.env exists
if [ -f "copy.env" ]; then
    echo "✓ Configuration file (copy.env) found"
else
    echo "✗ ERROR: copy.env not found"
    echo "  Create copy.env with your credentials before running"
    exit 1
fi

# Check if Python scripts exist
if [ -f "relay_agent.py" ] && [ -f "generate_message_images.py" ]; then
    echo "✓ Main scripts found"
else
    echo "✗ ERROR: Main scripts not found"
    exit 1
fi

# Create logs directory
mkdir -p logs
echo "✓ Logs directory ready"

echo ""
echo "=== Test 1: Facebook Scraping ==="
read -p "Test Facebook scraper? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running Facebook scraper with Xvfb (VPS crash fix)..."
    xvfb-run -a python3 relay_agent.py
    echo ""
    echo "Facebook scraper test completed."
    echo ""
fi

echo "=== Test 2: Twitter Posting ==="
read -p "Test Twitter poster? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running Twitter poster with Xvfb (VPS fix)..."
    xvfb-run -a python3 -m twitter.twitter_post
    echo ""
    echo "Twitter poster test completed."
    echo ""
fi

echo "=== Test 3: Image Generation ==="
read -p "Test image generator? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running image generator with Xvfb (VPS fix)..."
    xvfb-run -a python3 generate_message_images.py
    echo ""
    echo "Image generator test completed."
    echo ""
fi

echo "=== All Tests Completed ==="
echo ""
echo "If all tests passed, you can now set up cronjobs:"
echo "  bash setup_cron.sh"
echo ""
echo "To monitor the system:"
echo "  - Check database: sqlite3 data/scraper.db 'SELECT COUNT(*) FROM messages;'"
echo "  - View logs: tail -f logs/*.log"
echo ""


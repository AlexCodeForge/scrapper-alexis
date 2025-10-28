#!/bin/bash
# Real-time monitoring dashboard for Social Media Relay Agent
# Shows current system status, database stats, and recent activity

cd /var/www/scrapper-alexis

clear

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Social Media Relay Agent - System Status Monitor          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if database exists
if [ ! -f "data/scraper.db" ]; then
    echo "⚠️  ERROR: Database not found at data/scraper.db"
    echo "   Run Facebook scraper first to initialize the database"
    exit 1
fi

# Cron status
echo "═══ CRONJOB STATUS ═══"
if crontab -l &> /dev/null; then
    echo "✓ Cronjobs installed"
    echo ""
    crontab -l | grep -v "^#" | grep -v "^$"
    echo ""
else
    echo "⚠️  No cronjobs installed"
    echo "   Run: bash setup_cron.sh"
    echo ""
fi

# Database statistics
echo "═══ DATABASE STATISTICS ═══"
sqlite3 data/scraper.db << EOF
.mode column
.headers on
SELECT 
    COUNT(*) as 'Total Messages',
    SUM(CASE WHEN posted_to_twitter = 1 THEN 1 ELSE 0 END) as 'Posted to Twitter',
    SUM(CASE WHEN posted_to_twitter = 0 THEN 1 ELSE 0 END) as 'Pending Posts',
    SUM(CASE WHEN image_generated = 1 THEN 1 ELSE 0 END) as 'Images Generated'
FROM messages;
EOF
echo ""

# Profile statistics
echo "═══ MESSAGES BY PROFILE ═══"
sqlite3 data/scraper.db << EOF
.mode column
.headers on
SELECT 
    profile_name as 'Profile',
    COUNT(*) as 'Messages',
    SUM(CASE WHEN posted_to_twitter = 1 THEN 1 ELSE 0 END) as 'Posted'
FROM messages
GROUP BY profile_name
ORDER BY COUNT(*) DESC;
EOF
echo ""

# Recent activity
echo "═══ RECENT ACTIVITY (Last 5 Messages) ═══"
sqlite3 data/scraper.db << EOF
.mode column
.headers on
SELECT 
    substr(message_text, 1, 40) || '...' as 'Message Preview',
    profile_name as 'Profile',
    CASE WHEN posted_to_twitter = 1 THEN '✓' ELSE '○' END as 'Posted',
    CASE WHEN image_generated = 1 THEN '✓' ELSE '○' END as 'Image',
    datetime(scraped_at, 'localtime') as 'Scraped At'
FROM messages
ORDER BY scraped_at DESC
LIMIT 5;
EOF
echo ""

# Scraping sessions
echo "═══ SCRAPING SESSIONS (Last 3) ═══"
sqlite3 data/scraper.db << EOF
.mode column
.headers on
SELECT 
    profile_name as 'Profile',
    new_messages as 'New',
    duplicate_messages as 'Duplicates',
    datetime(started_at, 'localtime') as 'Started',
    CASE WHEN completed_at IS NOT NULL THEN 'Completed' ELSE 'In Progress' END as 'Status'
FROM scraping_sessions
ORDER BY started_at DESC
LIMIT 3;
EOF
echo ""

# Recent log entries
echo "═══ RECENT LOG ENTRIES ═══"
if [ -f "logs/cron_execution.log" ]; then
    tail -n 5 logs/cron_execution.log
else
    echo "No execution logs yet"
fi
echo ""

# File system info
echo "═══ STORAGE USAGE ═══"
echo "Database size: $(du -h data/scraper.db 2>/dev/null | cut -f1 || echo 'N/A')"
echo "Images folder: $(du -sh data/message_images 2>/dev/null | cut -f1 || echo 'N/A')"
echo "Logs folder: $(du -sh logs 2>/dev/null | cut -f1 || echo 'N/A')"
echo "Avatar cache: $(du -sh avatar_cache 2>/dev/null | cut -f1 || echo 'N/A')"
echo ""

# Process check
echo "═══ ACTIVE PROCESSES ═══"
PYTHON_PROCS=$(ps aux | grep -E "(relay_agent|twitter_post|generate_message)" | grep -v grep | wc -l)
BROWSER_PROCS=$(ps aux | grep -E "(chromium|chrome)" | grep -v grep | wc -l)

if [ $PYTHON_PROCS -gt 0 ]; then
    echo "✓ Python processes running: $PYTHON_PROCS"
else
    echo "○ No Python processes running"
fi

if [ $BROWSER_PROCS -gt 0 ]; then
    echo "✓ Browser processes running: $BROWSER_PROCS"
else
    echo "○ No browser processes running"
fi
echo ""

# Next run prediction
echo "═══ NEXT SCHEDULED RUNS ═══"
CURRENT_MIN=$(date +%M)
CURRENT_HOUR=$(date +%H)

# Calculate next 8-minute interval
NEXT_8MIN=$(( (($CURRENT_MIN / 8) * 8 + 8) % 60 ))
if [ $NEXT_8MIN -lt $CURRENT_MIN ]; then
    NEXT_TWITTER_HOUR=$(( ($CURRENT_HOUR + 1) % 24 ))
else
    NEXT_TWITTER_HOUR=$CURRENT_HOUR
fi

# Next hour
NEXT_FB_HOUR=$(( ($CURRENT_HOUR + 1) % 24 ))

printf "Twitter poster: %02d:%02d\n" $NEXT_TWITTER_HOUR $NEXT_8MIN
printf "Facebook scraper: %02d:00\n" $NEXT_FB_HOUR
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "Refresh this view: bash monitor_status.sh"
echo "Watch logs live: tail -f logs/cron_*.log"
echo "═══════════════════════════════════════════════════════════════"





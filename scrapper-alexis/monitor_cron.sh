#!/bin/bash
# Quick monitoring script for cronjobs

clear
echo "╔═══════════════════════════════════════════════════════════════════════════╗"
echo "║               CRONJOB MONITORING - Social Media Relay Agent              ║"
echo "╚═══════════════════════════════════════════════════════════════════════════╝"
echo ""

# Show current time
echo "📅 Current Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Show crontab schedule
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 CRONJOB SCHEDULE:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
crontab -l | grep -v "^#" | grep -v "^$"
echo ""

# Calculate next run times
CURRENT_MIN=$(date +%M)
CURRENT_HOUR=$(date +%H)

# Twitter runs every 8 minutes
TWITTER_NEXT=$((8 - CURRENT_MIN % 8))
if [ $TWITTER_NEXT -eq 8 ]; then TWITTER_NEXT=0; fi

# Facebook runs every hour at minute 0
FACEBOOK_NEXT=$((60 - CURRENT_MIN))

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⏰ NEXT EXECUTION:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🐦 Twitter Poster: in ~${TWITTER_NEXT} minutes"
echo "  📘 Facebook Scraper: in ~${FACEBOOK_NEXT} minutes"
echo ""

# Show log file sizes and last modified times
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📁 LOG FILES:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "logs/cron_facebook.log" ]; then
    SIZE=$(du -h logs/cron_facebook.log | cut -f1)
    MODIFIED=$(stat -c %y logs/cron_facebook.log | cut -d'.' -f1)
    echo "  📘 Facebook: $SIZE (last: $MODIFIED)"
else
    echo "  📘 Facebook: No log yet (waiting for first run)"
fi

if [ -f "logs/cron_twitter.log" ]; then
    SIZE=$(du -h logs/cron_twitter.log | cut -f1)
    MODIFIED=$(stat -c %y logs/cron_twitter.log | cut -d'.' -f1)
    echo "  🐦 Twitter: $SIZE (last: $MODIFIED)"
else
    echo "  🐦 Twitter: No log yet (waiting for first run)"
fi

echo ""

# Show database stats
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 DATABASE STATS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v sqlite3 &> /dev/null && [ -f "data/scraper.db" ]; then
    sqlite3 data/scraper.db <<EOF
.mode column
SELECT 
    (SELECT COUNT(*) FROM messages) AS total_messages,
    (SELECT COUNT(*) FROM messages WHERE posted_to_twitter = 1) AS posted,
    (SELECT COUNT(*) FROM messages WHERE posted_to_twitter = 0) AS pending,
    (SELECT COUNT(*) FROM messages WHERE image_generated = 1) AS images
FROM (SELECT 1);
EOF
else
    echo "  SQLite not available or database not found"
fi

echo ""

# Show last few entries from logs
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📝 RECENT LOG ENTRIES:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "logs/cron_twitter.log" ]; then
    echo "🐦 Twitter (last 5 lines):"
    tail -n 5 logs/cron_twitter.log | sed 's/^/   /'
    echo ""
fi

if [ -f "logs/cron_facebook.log" ]; then
    echo "📘 Facebook (last 5 lines):"
    tail -n 5 logs/cron_facebook.log | sed 's/^/   /'
    echo ""
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📌 MONITORING COMMANDS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Watch logs in real-time:"
echo "    tail -f logs/cron_twitter.log"
echo "    tail -f logs/cron_facebook.log"
echo ""
echo "  Refresh this status:"
echo "    bash monitor_cron.sh"
echo ""
echo "  Stop all cronjobs:"
echo "    crontab -r"
echo ""
echo "  View current crontab:"
echo "    crontab -l"
echo ""
echo "╚═══════════════════════════════════════════════════════════════════════════╝"





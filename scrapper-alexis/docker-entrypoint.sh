#!/bin/bash
set -e

# Clean logs directory on container start
# For clean installs or fresh starts, remove all existing log files
# Logs will be created fresh by the scraper scripts
echo "Cleaning logs directory for fresh start..."
rm -f /app/logs/*.log 2>/dev/null || true
rm -f /app/logs/*.txt 2>/dev/null || true
echo "✓ Logs directory cleaned (all old logs removed)"

echo "========================================="
echo "Starting Scraper Container"
echo "========================================="

# Fix permissions for shared data directory
echo "Setting up permissions for shared data..."
chmod 777 /app/data

# Create empty database file if it doesn't exist (with proper permissions from the start)
if [ ! -f /app/data/scraper.db ]; then
    echo "Creating empty database file..."
    touch /app/data/scraper.db
    chown 33:33 /app/data/scraper.db
    chmod 666 /app/data/scraper.db
    echo "✓ Empty database created with www-data permissions"
fi

# Fix permissions if database already exists
if [ -f /app/data/scraper.db ]; then
    chown 33:33 /app/data/scraper.db
    chmod 666 /app/data/scraper.db
    echo "✓ Database permissions set for www-data (UID 33)"
fi

# Load environment variables from .env file if it exists
if [ -f /app/.env ]; then
    echo "Loading environment variables from .env..."
    while IFS= read -r line; do
        # Skip comments and empty lines
        if [[ ! "$line" =~ ^[[:space:]]*# && -n "$line" ]]; then
            # Export the line as-is
            export "$line"
        fi
    done < <(grep -v '^[[:space:]]*#' /app/.env | grep -v '^[[:space:]]*$')
fi

# Initialize Python database schema (creates messages table if it doesn't exist)
echo "Initializing Python database schema..."
python3 -c "from core.database import DatabaseManager; DatabaseManager(); print('✓ Python database schema ready')"

# Start Xvfb in the background
echo "Starting Xvfb virtual display..."
Xvfb :99 -screen 0 1920x1080x24 -ac &
XVFB_PID=$!
sleep 2

# Verify Xvfb is running
if ps -p $XVFB_PID > /dev/null; then
    echo "✓ Xvfb started successfully (PID: $XVFB_PID)"
else
    echo "✗ Xvfb failed to start"
fi

# Set up cron jobs based on environment variables
echo "Setting up cron jobs..."

# Read interval settings from environment (with defaults)
FACEBOOK_INTERVAL_MIN=${FACEBOOK_INTERVAL_MIN:-45}
FACEBOOK_INTERVAL_MAX=${FACEBOOK_INTERVAL_MAX:-80}
TWITTER_INTERVAL_MIN=${TWITTER_INTERVAL_MIN:-8}
TWITTER_INTERVAL_MAX=${TWITTER_INTERVAL_MAX:-15}
FACEBOOK_SCRAPER_ENABLED=${FACEBOOK_SCRAPER_ENABLED:-true}
TWITTER_POSTER_ENABLED=${TWITTER_POSTER_ENABLED:-true}

# Calculate average intervals in minutes
FB_AVG=$(( (FACEBOOK_INTERVAL_MIN + FACEBOOK_INTERVAL_MAX) / 2 ))
TW_AVG=$(( (TWITTER_INTERVAL_MIN + TWITTER_INTERVAL_MAX) / 2 ))

# Create crontab file
CRON_FILE="/tmp/scraper_cron"
echo "# Scraper Cron Jobs - Generated at $(date)" > $CRON_FILE
echo "# Set PATH to include /usr/local/bin for python3" >> $CRON_FILE
echo "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin" >> $CRON_FILE
echo "" >> $CRON_FILE

# Add Facebook scraper job if enabled
if [ "$FACEBOOK_SCRAPER_ENABLED" = "true" ]; then
    # For intervals > 59 minutes, use hourly cron with specific minute
    if [ "$FB_AVG" -gt 59 ]; then
        # Run once per hour at minute :02
        echo "2 * * * * cd /app && python3 relay_agent.py >> /app/logs/facebook_cron.log 2>&1" >> $CRON_FILE
        echo "✓ Facebook scraper scheduled: hourly at minute :02 (interval $FB_AVG exceeds 59)"
    else
        # Use standard */X syntax for intervals <= 59 minutes
        echo "*/$FB_AVG * * * * cd /app && python3 relay_agent.py >> /app/logs/facebook_cron.log 2>&1" >> $CRON_FILE
        echo "✓ Facebook scraper scheduled: every $FB_AVG minutes"
    fi
else
    echo "✗ Facebook scraper disabled"
fi

# Add Twitter poster job if enabled
if [ "$TWITTER_POSTER_ENABLED" = "true" ]; then
    # For intervals > 59 minutes, use hourly cron
    if [ "$TW_AVG" -gt 59 ]; then
        echo "5 * * * * cd /app && python3 extract_twitter_profile.py >> /app/logs/twitter_cron.log 2>&1" >> $CRON_FILE
        echo "✓ Twitter poster scheduled: hourly at minute :05 (interval $TW_AVG exceeds 59)"
    else
        echo "*/$TW_AVG * * * * cd /app && python3 extract_twitter_profile.py >> /app/logs/twitter_cron.log 2>&1" >> $CRON_FILE
        echo "✓ Twitter poster scheduled: every $TW_AVG minutes"
    fi
else
    echo "✗ Twitter poster disabled"
fi

# Add Facebook Page Poster job (checks every 30 minutes if it's time to post)
echo "*/30 * * * * cd /app && python3 facebook_page_poster.py >> /app/logs/page_poster_cron.log 2>&1" >> $CRON_FILE
echo "✓ Facebook Page Poster scheduled: checks every 30 minutes"

# Note: Image generation is triggered automatically by the Twitter flow
# No separate cron job needed

# Add empty line at end (required by cron)
echo "" >> $CRON_FILE

# Install crontab
crontab $CRON_FILE
echo "✓ Crontab installed"

# Show installed crontab
echo ""
echo "Installed cron jobs:"
crontab -l

# Start cron daemon
echo ""
echo "Starting cron daemon..."
cron
sleep 1

# Verify cron is running
if pgrep cron > /dev/null; then
    echo "✓ Cron daemon started successfully"
else
    echo "✗ Cron daemon failed to start"
fi

echo ""
echo "========================================="
echo "Scraper Container Ready!"
echo "========================================="
echo "Xvfb Display: $DISPLAY"
echo "Database: /app/data/scraper.db"
echo "Logs: /app/logs/"
echo ""
echo "Container will now monitor cron jobs..."
echo "========================================="

# Create a log file to tail
touch /app/logs/container.log
echo "$(date): Container started" >> /app/logs/container.log

# Keep container running and show logs
tail -f /app/logs/*.log 2>/dev/null || sleep infinity


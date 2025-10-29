#!/bin/bash
# Facebook Page Poster Flow
# Posts approved images to Facebook page
# CRITICAL: Uses xvfb-run to prevent VPS crashes

cd /app

# Load environment variables
if [ -f .env ]; then
    while IFS= read -r line; do
        [[ ! "$line" =~ ^[[:space:]]*# && -n "$line" ]] && export "$line"
    done < <(grep -v '^[[:space:]]*#' .env | grep -v '^[[:space:]]*$')
fi

# Helper function to get settings from database
get_setting() {
    local key=$1
    local db_path="/var/www/html/database/database.sqlite"
    
    if [ ! -f "$db_path" ]; then
        echo ""
        return
    fi
    
    sqlite3 "$db_path" "SELECT $key FROM posting_settings LIMIT 1" 2>/dev/null
}

# Check if page posting is enabled
ENABLED=$(get_setting "enabled")
if [ "$ENABLED" != "1" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Page posting disabled - skipping" >> logs/cron_execution.log
    exit 0
fi

# Get interval settings
INTERVAL_MIN=$(get_setting "interval_min")
INTERVAL_MAX=$(get_setting "interval_max")

# Default values if not set
INTERVAL_MIN=${INTERVAL_MIN:-60}
INTERVAL_MAX=${INTERVAL_MAX:-120}

# Check if we should post based on random interval
# Store last post time in a file
LAST_POST_FILE="/tmp/last_page_post_time"
CURRENT_TIME=$(date +%s)

if [ -f "$LAST_POST_FILE" ]; then
    LAST_POST_TIME=$(cat "$LAST_POST_FILE")
    TIME_DIFF=$(( (CURRENT_TIME - LAST_POST_TIME) / 60 ))  # Convert to minutes
    
    # Calculate random interval for this run
    RANDOM_INTERVAL=$(( RANDOM % (INTERVAL_MAX - INTERVAL_MIN + 1) + INTERVAL_MIN ))
    
    if [ $TIME_DIFF -lt $RANDOM_INTERVAL ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Too soon to post - only $TIME_DIFF minutes since last post (need $RANDOM_INTERVAL)" >> logs/cron_execution.log
        exit 0
    fi
fi

# Time to post!
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Facebook page posting (interval: ${INTERVAL_MIN}-${INTERVAL_MAX} min)" >> logs/cron_execution.log

# Activate virtual environment if it exists
if [ -d "venv" ]; then
    source venv/bin/activate
fi

# Run the page poster with Xvfb (virtual display)
xvfb-run -a python3 facebook_page_poster.py

EXIT_CODE=$?

# If successful, update last post time
if [ $EXIT_CODE -eq 0 ]; then
    echo "$CURRENT_TIME" > "$LAST_POST_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Page posting completed successfully" >> logs/cron_execution.log
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Page posting failed with exit code $EXIT_CODE" >> logs/cron_execution.log
fi

exit $EXIT_CODE


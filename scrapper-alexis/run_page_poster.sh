#!/bin/bash
# Facebook Page Poster Flow
# Posts approved images to Facebook page
# CRITICAL: Uses xvfb-run to prevent VPS crashes

cd /var/www/alexis-scrapper-docker/scrapper-alexis

# Process lock to prevent duplicate execution
LOCKFILE="/var/lock/page_poster.lock"
exec 9>"$LOCKFILE"
if ! flock -n 9; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Page poster already running - skipping" >> logs/cron_execution.log
    exit 0
fi

# BUGFIX: Cleanup function to kill Firefox even on script failure
cleanup_firefox() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleaning up Firefox processes..." >> logs/cron_execution.log
    # Kill any Firefox processes started by this script
    pkill -P $$ firefox 2>/dev/null || true
    # Also kill any orphaned Firefox processes from this user
    ps aux | grep -E "firefox.*playwright" | grep -v grep | awk '{print $2}' | xargs -r kill -9 2>/dev/null || true
}

# Set trap to cleanup on exit (success or failure)
trap cleanup_firefox EXIT INT TERM

# Activate virtual environment
source venv/bin/activate

# Load environment variables
if [ -f .env ]; then
    while IFS= read -r line; do
        [[ ! "$line" =~ ^[[:space:]]*# && -n "$line" ]] && export "$line"
    done < <(grep -v '^[[:space:]]*#' .env | grep -v '^[[:space:]]*$')
fi

# Helper function to get settings from database
get_setting() {
    local key=$1
    
    # Try multiple possible database paths
    local possible_paths=(
        "/var/www/scrapper-alexis/data/scraper.db"
        "/var/www/alexis-scrapper-docker/scrapper-alexis/data/scraper.db"
        "data/scraper.db"
    )
    
    local db_path=""
    for path in "${possible_paths[@]}"; do
        if [ -f "$path" ]; then
            db_path="$path"
            break
        fi
    done
    
    if [ -z "$db_path" ]; then
        echo "[ERROR] Database not found in any of: ${possible_paths[*]}" >> logs/page_poster_$(date +%Y%m%d).log
        echo ""
        return
    fi
    
    sqlite3 "$db_path" "SELECT $key FROM posting_settings LIMIT 1" 2>/dev/null
}

# Check if page posting is enabled (only for automatic cron runs, not manual runs)
if [ -z "$SKIP_DELAY" ]; then
    # This is a cron run, check if enabled
    ENABLED=$(get_setting "enabled")
    if [ "$ENABLED" != "1" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Page posting disabled - skipping" >> logs/cron_execution.log
        exit 0
    fi
else
    # This is a manual run, always proceed
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Manual page posting triggered - bypassing enabled check" >> logs/page_poster_$(date +%Y%m%d).log
fi

# Get interval settings
INTERVAL_MIN=$(get_setting "interval_min")
INTERVAL_MAX=$(get_setting "interval_max")

# Default values if not set
INTERVAL_MIN=${INTERVAL_MIN:-60}
INTERVAL_MAX=${INTERVAL_MAX:-120}

# Define last post file path (used by both manual and scheduled runs)
LAST_POST_FILE="/tmp/last_page_post_time"

# Check if we should post based on random interval (only for cron runs, not manual)
if [ -z "$SKIP_DELAY" ]; then
    # This is a cron run, check interval timing
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
else
    # Manual run - no interval check, post immediately
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Manual run - skipping interval check" >> logs/page_poster_$(date +%Y%m%d).log
    CURRENT_TIME=$(date +%s)
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


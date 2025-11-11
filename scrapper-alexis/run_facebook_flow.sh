#!/bin/bash
# Facebook Scraping Flow
# Scrapes messages from configured Facebook profiles
# CRITICAL: Uses xvfb-run to prevent VPS crashes (see VPS_CRASH_SOLUTION.md)

cd /var/www/alexis-scrapper-docker/scrapper-alexis

# Process lock to prevent duplicate execution
LOCKFILE="/var/lock/facebook_scraper.lock"
exec 9>"$LOCKFILE"
if ! flock -n 9; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Facebook scraper already running - skipping" >> logs/cron_execution.log
    exit 0
fi

# Bugfix: Store Python PID to track our own Firefox processes
PYTHON_PID=""

# Bugfix: Cleanup function - ONLY kill Firefox processes from THIS script's Python process
cleanup_firefox() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleaning up Firefox processes..." >> logs/cron_execution.log
    
    # Kill only Firefox processes that are children of our Python process
    if [ -n "$PYTHON_PID" ]; then
        # Get all child processes of our Python process (including Firefox)
        pkill -P "$PYTHON_PID" 2>/dev/null || true
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Killed Firefox children of Python PID $PYTHON_PID" >> logs/cron_execution.log
    fi
    
    # Kill any direct Firefox children of this shell
    pkill -P $$ firefox 2>/dev/null || true
}

# Set trap to cleanup on exit (success or failure)
trap cleanup_firefox EXIT INT TERM

# Activate virtual environment
source venv/bin/activate

# Load environment variables (supports values with spaces)
if [ -f .env ]; then
    while IFS= read -r line; do
        [[ ! "$line" =~ ^[[:space:]]*# && -n "$line" ]] && export "$line"
    done < <(grep -v '^[[:space:]]*#' .env | grep -v '^[[:space:]]*$')
fi

# Only apply random delay if running from cron (not manual execution)
# Check if stdin is a terminal - if yes, it's manual; if no, it's cron
if [ ! -t 0 ] && [ -z "$SKIP_DELAY" ]; then
    # Calculate random delay based on configured interval (±20% of average interval)
    INTERVAL_MIN=${FACEBOOK_INTERVAL_MIN:-45}
    INTERVAL_MAX=${FACEBOOK_INTERVAL_MAX:-80}
    INTERVAL_AVG=$(( (INTERVAL_MIN + INTERVAL_MAX) / 2 ))
    INTERVAL_SECONDS=$(( INTERVAL_AVG * 60 ))
    MAX_DELAY=$(( INTERVAL_SECONDS * 20 / 100 ))  # 20% of average interval

    RANDOM_DELAY=$(( RANDOM % (MAX_DELAY + 1) ))
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Facebook cron: Interval ${INTERVAL_MIN}-${INTERVAL_MAX}min, adding random delay of ${RANDOM_DELAY}s ($(($RANDOM_DELAY / 60))m $(($RANDOM_DELAY % 60))s) for natural timing" >> logs/cron_execution.log
    sleep $RANDOM_DELAY
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Facebook manual run: Skipping random delay" >> logs/cron_execution.log
fi

# Run the Facebook scraper with Xvfb (virtual display)
# Bugfix: Run in background and capture PID so cleanup only kills OUR Firefox
xvfb-run -a python3 relay_agent.py &
PYTHON_PID=$!

# Wait for Python to finish
wait $PYTHON_PID
SCRAPER_EXIT_CODE=$?

# Log execution
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Facebook scraping completed with exit code $SCRAPER_EXIT_CODE" >> logs/cron_execution.log


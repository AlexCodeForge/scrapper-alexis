#!/bin/bash
# Twitter Posting Flow
# Posts one unposted message to Twitter
# CRITICAL: Uses xvfb-run to prevent VPS crashes (see VPS_CRASH_SOLUTION.md)

cd /app

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
    INTERVAL_MIN=${TWITTER_INTERVAL_MIN:-8}
    INTERVAL_MAX=${TWITTER_INTERVAL_MAX:-15}
    INTERVAL_AVG=$(( (INTERVAL_MIN + INTERVAL_MAX) / 2 ))
    INTERVAL_SECONDS=$(( INTERVAL_AVG * 60 ))
    MAX_DELAY=$(( INTERVAL_SECONDS * 20 / 100 ))  # 20% of average interval

    RANDOM_DELAY=$(( RANDOM % (MAX_DELAY + 1) ))
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Twitter cron: Interval ${INTERVAL_MIN}-${INTERVAL_MAX}min, adding random delay of ${RANDOM_DELAY}s ($(($RANDOM_DELAY / 60))m $(($RANDOM_DELAY % 60))s) for natural timing" >> logs/cron_execution.log
    sleep $RANDOM_DELAY
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Twitter manual run: Skipping random delay" >> logs/cron_execution.log
fi

# Activate virtual environment if it exists
if [ -d "venv" ]; then
    source venv/bin/activate
fi

# Run the Twitter poster with Xvfb (virtual display)
xvfb-run -a python3 -m twitter.twitter_post

# Log execution
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Twitter posting completed" >> logs/cron_execution.log

# Run image generation after posting
sleep 2
/app/run_image_generation.sh

# Update posting log after everything
python3 generate_posting_log.py > /dev/null 2>&1


#!/bin/bash
# =============================================================================
# ⚠️  MODIFIED - Twitter Posting DISABLED ⚠️
# =============================================================================
# This script originally handled Twitter posting + image generation.
# Twitter posting has been disabled. Now only runs image generation.
# Profile info comes from web interface instead of Twitter extraction.
# =============================================================================
#
# Image Generation Flow
# Generates images for posted messages using user-provided profile info
# CRITICAL: Uses xvfb-run to prevent VPS crashes (see VPS_CRASH_SOLUTION.md)

# Auto-detect script location (works anywhere)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

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

# =============================================================================
# TWITTER POSTING STEPS - COMMENTED OUT (NO LONGER USED)
# =============================================================================
# Profile info now comes from web interface settings, not Twitter extraction
# Users configure display name, username, and upload avatar in settings page
# =============================================================================
#
# # STEP 1: Extract Twitter profile info (username, display name, avatar)
# # This ensures .env files are always up-to-date for image generation
# echo "[$(date '+%Y-%m-%d %H:%M:%S')] Extracting Twitter profile info..." >> logs/cron_execution.log
# xvfb-run -a python3 extract_twitter_profile.py >> logs/profile_extraction.log 2>&1
# PROFILE_EXIT_CODE=$?
#
# if [ $PROFILE_EXIT_CODE -ne 0 ]; then
#     echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: Profile extraction failed, using existing .env values" >> logs/cron_execution.log
# fi
#
# # STEP 2: Run the Twitter poster with Xvfb (virtual display)
# xvfb-run -a python3 -m twitter.twitter_post
#
# # Log execution
# echo "[$(date '+%Y-%m-%d %H:%M:%S')] Twitter posting completed" >> logs/cron_execution.log
#
# =============================================================================

# STEP 1: Run image generation (uses profile info from web interface settings)
sleep 2
./run_image_generation.sh

# STEP 2: Update posting log after image generation
python3 generate_posting_log.py > /dev/null 2>&1


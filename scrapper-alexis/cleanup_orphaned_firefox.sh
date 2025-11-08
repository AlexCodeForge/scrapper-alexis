#!/bin/bash
# Cleanup script for orphaned Firefox/Playwright processes
# Kills Firefox processes that have been running for more than 15 minutes
# This prevents memory leaks from stuck browser instances

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running Firefox cleanup check..."

# Find Firefox processes related to playwright that are older than 15 minutes
ORPHANED=$(ps -eo pid,etimes,cmd | grep -E "firefox.*playwright" | grep -v grep | awk '$2 > 900 {print $1}')

if [ -n "$ORPHANED" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Found orphaned Firefox processes (running >15 min): $ORPHANED"
    echo "$ORPHANED" | xargs -r kill -9 2>/dev/null
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Killed orphaned Firefox processes"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] No orphaned Firefox processes found"
fi

# Also check total Firefox count - warn if more than 3
FIREFOX_COUNT=$(ps aux | grep -E "firefox.*playwright" | grep -v grep | wc -l)
if [ "$FIREFOX_COUNT" -gt 3 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: Found $FIREFOX_COUNT Firefox instances (max should be 3)"
fi


#!/bin/bash
# Image Generation Flow
# Generates images for posted messages
# CRITICAL: Uses xvfb-run to prevent VPS crashes (see VPS_CRASH_SOLUTION.md)

cd /app

# Activate virtual environment if it exists
if [ -d "venv" ]; then
    source venv/bin/activate
fi

# Run the image generator with Xvfb (virtual display)
xvfb-run -a python3 generate_message_images.py

# Log execution
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Image generation completed" >> logs/cron_execution.log


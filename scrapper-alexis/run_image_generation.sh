#!/bin/bash
# Image Generation Flow
# Generates images for posted messages
# CRITICAL: Uses xvfb-run to prevent VPS crashes (see VPS_CRASH_SOLUTION.md)

cd /var/www/alexis-scrapper-docker/scrapper-alexis

# Activate virtual environment if it exists
if [ -d "venv" ]; then
    source venv/bin/activate
fi

# Set up log file with date
LOG_FILE="logs/image_generator_$(date '+%Y%m%d').log"

# Log start
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting image generator" >> "$LOG_FILE"

# Run the image generator with Xvfb (virtual display) and redirect output
xvfb-run -a python3 generate_message_images.py 2>&1 | tee -a "$LOG_FILE"

# Log completion
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Image generation completed" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Image generation completed" >> logs/cron_execution.log


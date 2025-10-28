#!/bin/bash
# Setup script for cronjobs
# This script makes the workflow scripts executable and installs the crontab

echo "=== Social Media Relay Agent - Cron Setup ==="
echo ""

# Make scripts executable
echo "Making scripts executable..."
chmod +x /var/www/scrapper-alexis/run_facebook_flow.sh
chmod +x /var/www/scrapper-alexis/run_twitter_flow.sh
chmod +x /var/www/scrapper-alexis/run_image_generation.sh

# Create logs directory if it doesn't exist
echo "Ensuring logs directory exists..."
mkdir -p /var/www/scrapper-alexis/logs

# Backup existing crontab
echo "Backing up existing crontab..."
crontab -l > /var/www/scrapper-alexis/crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null || echo "No existing crontab found"

# Show the new crontab configuration
echo ""
echo "=== New Crontab Configuration ==="
cat /var/www/scrapper-alexis/crontab_config.txt
echo ""

# Ask for confirmation
read -p "Do you want to install this crontab? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]
then
    # Install the crontab
    crontab /var/www/scrapper-alexis/crontab_config.txt
    echo ""
    echo "✓ Crontab installed successfully!"
    echo ""
    echo "Current crontab:"
    crontab -l
    echo ""
    echo "=== Testing Setup ==="
    echo "Schedule:"
    echo "  - Facebook scraper: Every 1 hour (at minute 0)"
    echo "  - Twitter poster: Every 8 minutes"
    echo "  - Image generator: After each Twitter post"
    echo ""
    echo "Log files:"
    echo "  - logs/cron_facebook.log"
    echo "  - logs/cron_twitter.log"
    echo "  - logs/cron_execution.log"
    echo ""
    echo "To monitor logs in real-time:"
    echo "  tail -f logs/cron_*.log"
    echo ""
    echo "To stop cronjobs:"
    echo "  crontab -r"
    echo ""
else
    echo "Setup cancelled. No changes made."
fi





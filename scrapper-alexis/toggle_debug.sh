#!/bin/bash
# Script to easily toggle debug output on/off and check current status

ENV_FILE=".env"
SETTING="DEBUG_OUTPUT_ENABLED"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to show current status
show_status() {
    if grep -q "^${SETTING}=true" "$ENV_FILE" 2>/dev/null; then
        echo -e "${GREEN}✓ Debug Output: ENABLED${NC}"
        echo "  - Screenshots will be saved to debug_output/"
        echo "  - Session logs will be created"
        echo "  - Disk space will be consumed"
    elif grep -q "^${SETTING}=false" "$ENV_FILE" 2>/dev/null; then
        echo -e "${RED}✗ Debug Output: DISABLED${NC}"
        echo "  - No debug screenshots"
        echo "  - No debug folders created"
        echo "  - Disk space saved"
    else
        echo -e "${YELLOW}⚠ Debug Output: NOT CONFIGURED${NC}"
        echo "  Setting not found in $ENV_FILE"
    fi
}

# Function to enable debug
enable_debug() {
    if grep -q "^${SETTING}=" "$ENV_FILE" 2>/dev/null; then
        sed -i "s/^${SETTING}=.*/${SETTING}=true/" "$ENV_FILE"
    else
        echo "" >> "$ENV_FILE"
        echo "# Debug Output Control" >> "$ENV_FILE"
        echo "${SETTING}=true" >> "$ENV_FILE"
    fi
    echo -e "${GREEN}✓ Debug output ENABLED${NC}"
    show_status
}

# Function to disable debug
disable_debug() {
    if grep -q "^${SETTING}=" "$ENV_FILE" 2>/dev/null; then
        sed -i "s/^${SETTING}=.*/${SETTING}=false/" "$ENV_FILE"
    else
        echo "" >> "$ENV_FILE"
        echo "# Debug Output Control" >> "$ENV_FILE"
        echo "${SETTING}=false" >> "$ENV_FILE"
    fi
    echo -e "${RED}✓ Debug output DISABLED${NC}"
    show_status
}

# Show disk usage of debug folder
show_disk_usage() {
    if [ -d "debug_output" ]; then
        SIZE=$(du -sh debug_output 2>/dev/null | cut -f1)
        COUNT=$(find debug_output -type d -name "run_*" 2>/dev/null | wc -l)
        echo ""
        echo "Debug Output Folder:"
        echo "  - Size: $SIZE"
        echo "  - Run folders: $COUNT"
        
        if [ "$COUNT" -gt 0 ]; then
            echo ""
            echo "To clean up old debug output:"
            echo "  tar -czf debug_output_backup.tar.gz debug_output/  # Backup first"
            echo "  rm -rf debug_output/run_*                          # Remove all run folders"
        fi
    else
        echo ""
        echo "Debug output folder does not exist (no debug data)"
    fi
}

# Main menu
case "$1" in
    status)
        echo "Current Status:"
        show_status
        show_disk_usage
        ;;
    enable|on)
        enable_debug
        ;;
    disable|off)
        disable_debug
        ;;
    toggle)
        if grep -q "^${SETTING}=true" "$ENV_FILE" 2>/dev/null; then
            disable_debug
        else
            enable_debug
        fi
        ;;
    cleanup)
        echo "Cleaning up debug output folder..."
        if [ -d "debug_output" ]; then
            SIZE_BEFORE=$(du -sh debug_output 2>/dev/null | cut -f1)
            echo "Current size: $SIZE_BEFORE"
            echo ""
            read -p "Create backup first? (y/n): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                BACKUP_FILE="debug_output_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
                echo "Creating backup: $BACKUP_FILE"
                tar -czf "$BACKUP_FILE" debug_output/
                echo "✓ Backup created"
            fi
            echo ""
            read -p "Remove all run folders from debug_output/? (y/n): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                rm -rf debug_output/run_*
                echo "✓ Debug output cleaned"
                if [ -d "debug_output" ]; then
                    SIZE_AFTER=$(du -sh debug_output 2>/dev/null | cut -f1)
                    echo "New size: $SIZE_AFTER"
                else
                    echo "Debug output folder removed"
                fi
            else
                echo "Cleanup cancelled"
            fi
        else
            echo "No debug_output folder found"
        fi
        ;;
    *)
        echo "Debug Output Control Script"
        echo ""
        echo "Usage: $0 {status|enable|disable|toggle|cleanup}"
        echo ""
        echo "Commands:"
        echo "  status   - Show current debug output status and disk usage"
        echo "  enable   - Enable debug output (creates screenshots and logs)"
        echo "  disable  - Disable debug output (saves disk space)"
        echo "  toggle   - Toggle between enabled and disabled"
        echo "  cleanup  - Remove old debug output folders (with backup option)"
        echo ""
        echo "Examples:"
        echo "  $0 status      # Check current status"
        echo "  $0 disable     # Disable debug to save space"
        echo "  $0 enable      # Enable debug for troubleshooting"
        echo "  $0 cleanup     # Clean up old debug files"
        echo ""
        echo "Current Status:"
        show_status
        ;;
esac


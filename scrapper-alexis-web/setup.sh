#!/bin/bash

# ============================================================================
# Scraper Admin Panel - Permission Setup Script
# ============================================================================
# This script fixes file permissions for the Laravel app and Python scraper.
# DYNAMIC PATH DETECTION - No hardcoded paths!
# ============================================================================

echo "=== Scraper Admin Panel Setup ==="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# ============================================================================
# Detect installation directory dynamically
# ============================================================================

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
WEB_DIR="$SCRIPT_DIR"
INSTALL_DIR="$(dirname "$SCRIPT_DIR")"
PYTHON_DIR="$INSTALL_DIR/scrapper-alexis"

echo -e "Web directory:    ${GREEN}$WEB_DIR${NC}"
echo -e "Python directory: ${GREEN}$PYTHON_DIR${NC}"
echo ""

# ============================================================================
# Set Laravel permissions
# ============================================================================

echo "Setting Laravel permissions..."
cd "$WEB_DIR"

chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
echo -e "${GREEN}✓ Laravel permissions set${NC}"

# ============================================================================
# Set Laravel database permissions
# ============================================================================

echo "Setting Laravel database permissions..."
chmod 775 database/ 2>/dev/null || true

if [ -f "database/database.sqlite" ]; then
    chmod 664 database/database.sqlite
else
    # Create database file if it doesn't exist
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

chown -R www-data:www-data database/
echo -e "${GREEN}✓ Laravel database permissions set${NC}"

# ============================================================================
# Create storage symlink
# ============================================================================

echo "Creating storage symlink..."
php artisan storage:link 2>/dev/null || true
echo -e "${GREEN}✓ Storage symlink created${NC}"

# ============================================================================
# Set Python scraper permissions
# ============================================================================

echo "Setting Python scraper permissions..."
if [ -d "$PYTHON_DIR" ]; then
    # Data directory and subdirectories
    chmod 775 "$PYTHON_DIR/data" 2>/dev/null || true
    chown www-data:www-data "$PYTHON_DIR/data" 2>/dev/null || true
    
    # Message images directory (CRITICAL for deletion from web interface)
    if [ -d "$PYTHON_DIR/data/message_images" ]; then
        chmod 775 "$PYTHON_DIR/data/message_images"
        chown -R www-data:www-data "$PYTHON_DIR/data/message_images"
        
        # Ensure all existing images are owned by www-data (critical for deletion!)
        find "$PYTHON_DIR/data/message_images" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" \) \
            -exec chown www-data:www-data {} \; \
            -exec chmod 664 {} \; 2>/dev/null || true
        
        echo -e "${GREEN}✓ Message images ownership fixed (deletion will work)${NC}"
    fi
    
    # Auth states directory
    if [ -d "$PYTHON_DIR/data/auth_states" ]; then
        chmod 775 "$PYTHON_DIR/data/auth_states"
        chown -R www-data:www-data "$PYTHON_DIR/data/auth_states"
    fi
    
    # Logs directory
    if [ -d "$PYTHON_DIR/logs" ]; then
        chmod 775 "$PYTHON_DIR/logs"
        chown -R www-data:www-data "$PYTHON_DIR/logs"
        
        # Fix ownership of existing log files
        find "$PYTHON_DIR/logs" -type f -name "*.log" \
            -exec chown www-data:www-data {} \; \
            -exec chmod 664 {} \; 2>/dev/null || true
    fi
    
    # Debug output directory (if exists)
    if [ -d "$PYTHON_DIR/debug_output" ]; then
        chmod 775 "$PYTHON_DIR/debug_output"
        chown -R www-data:www-data "$PYTHON_DIR/debug_output"
    fi
    
    # Avatar cache directory (if exists)
    if [ -d "$PYTHON_DIR/avatar_cache" ]; then
        chmod 775 "$PYTHON_DIR/avatar_cache"
        chown -R www-data:www-data "$PYTHON_DIR/avatar_cache"
    fi
    
    # Make scripts executable and owned by www-data
    if ls "$PYTHON_DIR/run_"*.sh 1> /dev/null 2>&1; then
        chmod 755 "$PYTHON_DIR/run_"*.sh
        chown www-data:www-data "$PYTHON_DIR/run_"*.sh
        echo -e "${GREEN}✓ Scripts are executable and owned by www-data${NC}"
    fi
    
    echo -e "${GREEN}✓ Python scraper permissions set${NC}"
else
    echo -e "${YELLOW}⚠ Python directory not found: $PYTHON_DIR${NC}"
fi

# ============================================================================
# Verify permissions
# ============================================================================

echo ""
echo "Verifying critical permissions..."

# Check database
if [ -f "$WEB_DIR/database/database.sqlite" ]; then
    DB_PERMS=$(stat -c "%a" "$WEB_DIR/database/database.sqlite")
    DB_OWNER=$(stat -c "%U:%G" "$WEB_DIR/database/database.sqlite")
    
    if [ "$DB_PERMS" == "664" ] && [ "$DB_OWNER" == "www-data:www-data" ]; then
        echo -e "${GREEN}✓ Database: $DB_PERMS $DB_OWNER${NC}"
    else
        echo -e "${YELLOW}⚠ Database: $DB_PERMS $DB_OWNER (expected: 664 www-data:www-data)${NC}"
    fi
fi

# Check storage
if [ -d "$WEB_DIR/storage" ]; then
    STORAGE_OWNER=$(stat -c "%U:%G" "$WEB_DIR/storage")
    if [ "$STORAGE_OWNER" == "www-data:www-data" ]; then
        echo -e "${GREEN}✓ Storage: $STORAGE_OWNER${NC}"
    else
        echo -e "${YELLOW}⚠ Storage: $STORAGE_OWNER (expected: www-data:www-data)${NC}"
    fi
fi

# Check message images directory
if [ -d "$PYTHON_DIR/data/message_images" ]; then
    IMAGES_OWNER=$(stat -c "%U:%G" "$PYTHON_DIR/data/message_images")
    if [ "$IMAGES_OWNER" == "www-data:www-data" ]; then
        echo -e "${GREEN}✓ Message images: $IMAGES_OWNER${NC}"
    else
        echo -e "${YELLOW}⚠ Message images: $IMAGES_OWNER (expected: www-data:www-data)${NC}"
    fi
fi

echo ""
echo -e "${GREEN}Setup complete!${NC}"
echo ""
echo "If you're having permission issues, you may need to run this script with sudo:"
echo "  sudo $0"
echo ""

#!/bin/bash

echo "=== Scraper Admin Panel Setup ==="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Detect installation directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
INSTALL_DIR="$(dirname "$SCRIPT_DIR")"
PYTHON_DIR="$INSTALL_DIR/scrapper-alexis"

echo "Installation directory: $INSTALL_DIR"
echo ""

# Set permissions for Laravel
echo "Setting Laravel permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
echo -e "${GREEN}✓ Laravel permissions set${NC}"

# Set permissions for Laravel database
echo "Setting Laravel database permissions..."
chmod 775 database/
chmod 664 database/database.sqlite 2>/dev/null || touch database/database.sqlite && chmod 664 database/database.sqlite
chown -R www-data:www-data database/
echo -e "${GREEN}✓ Laravel database permissions set${NC}"

# Set permissions for Python scraper directories
echo "Setting Python scraper permissions..."
if [ -d "$PYTHON_DIR" ]; then
    # Data directory and subdirectories
    chmod 775 "$PYTHON_DIR/data"
    chown www-data:www-data "$PYTHON_DIR/data"
    
    # Message images directory (CRITICAL for deletion)
    if [ -d "$PYTHON_DIR/data/message_images" ]; then
        chmod 775 "$PYTHON_DIR/data/message_images"
        chown -R www-data:www-data "$PYTHON_DIR/data/message_images"
        # Ensure all existing images are owned by www-data (critical for deletion!)
        find "$PYTHON_DIR/data/message_images" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" \) -exec chown www-data:www-data {} \; -exec chmod 664 {} \; 2>/dev/null || true
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
        find "$PYTHON_DIR/logs" -type f -name "*.log" -exec chown www-data:www-data {} \; -exec chmod 664 {} \; 2>/dev/null || true
    fi
    
    # Debug output directory (if exists)
    if [ -d "$PYTHON_DIR/debug_output" ]; then
        chmod 775 "$PYTHON_DIR/debug_output"
        chown -R www-data:www-data "$PYTHON_DIR/debug_output"
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

# Check if Nginx config exists
echo ""
echo "Nginx configuration file: nginx.conf"
echo -e "${YELLOW}To enable Nginx:${NC}"
echo "  sudo cp nginx.conf /etc/nginx/sites-available/scraper-admin"
echo "  sudo ln -s /etc/nginx/sites-available/scraper-admin /etc/nginx/sites-enabled/"
echo "  sudo nginx -t"
echo "  sudo systemctl reload nginx"

echo ""
echo -e "${GREEN}Setup complete!${NC}"
echo ""
echo "Default admin credentials:"
echo "  Email: admin@scraper.local"
echo "  Password: password"
echo ""
echo "To start development server:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "Access at: http://localhost:8000"








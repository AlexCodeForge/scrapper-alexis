#!/bin/bash

echo "=== Scraper Admin Panel Setup ==="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Set permissions for Laravel
echo "Setting Laravel permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
echo -e "${GREEN}✓ Laravel permissions set${NC}"

# Set permissions for scraper database
echo "Setting scraper database permissions..."
chmod 664 /var/www/scrapper-alexis/data/scraper.db
chmod 775 /var/www/scrapper-alexis/data
chmod 775 /var/www/scrapper-alexis/data/message_images
chown www-data:www-data /var/www/scrapper-alexis/data/scraper.db
echo -e "${GREEN}✓ Database permissions set${NC}"

# Set permissions for copy.env
echo "Setting permissions for copy.env..."
chmod 664 /var/www/scrapper-alexis/copy.env
chown www-data:www-data /var/www/scrapper-alexis/copy.env
echo -e "${GREEN}✓ Environment file permissions set${NC}"

# Make scraper scripts executable
echo "Making scraper scripts executable..."
chmod +x /var/www/scrapper-alexis/run_*.sh
echo -e "${GREEN}✓ Scripts are executable${NC}"

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








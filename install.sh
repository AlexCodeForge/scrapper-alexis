#!/bin/bash

# ============================================================================
# Alexis Scraper - Fresh Installation Script
# ============================================================================
# This script automates the installation of the Alexis Scraper application
# on a fresh VPS. It handles all dependencies, permissions, and configuration.
# ============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/var/www/alexis-scrapper-docker"
WEBUSER="www-data"
PHP_VERSION="8.2"

# ============================================================================
# Helper Functions
# ============================================================================

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# ============================================================================
# Installation Steps
# ============================================================================

install_system_packages() {
    print_header "Installing System Packages"
    
    apt update
    apt upgrade -y
    
    apt install -y \
        git \
        curl \
        wget \
        unzip \
        software-properties-common \
        sqlite3 \
        xvfb
    
    print_success "System packages installed"
}

configure_timezone() {
    print_header "Configuring Timezone to Mexico"
    
    # Set timezone to Mexico (America/Mexico_City)
    timedatectl set-timezone America/Mexico_City
    
    # Verify
    current_tz=$(timedatectl | grep "Time zone" | awk '{print $3}')
    
    if [[ "$current_tz" == "America/Mexico_City" ]]; then
        print_success "Timezone set to Mexico (America/Mexico_City)"
        timedatectl | grep "Time zone"
    else
        print_warning "Timezone may not be set correctly. Current: $current_tz"
    fi
}

install_php() {
    print_header "Installing PHP $PHP_VERSION"
    
    # Check if PHP is already installed
    if command -v php &> /dev/null; then
        PHP_CURRENT=$(php -r 'echo PHP_VERSION;')
        print_warning "PHP $PHP_CURRENT is already installed"
        read -p "Do you want to install PHP $PHP_VERSION anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return
        fi
    fi
    
    # Add PHP repository
    add-apt-repository ppa:ondrej/php -y
    apt update
    
    # Install PHP and extensions
    apt install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath
    
    # Verify installation
    php -v
    
    print_success "PHP $PHP_VERSION installed"
}

install_python() {
    print_header "Installing Python 3"
    
    apt install -y \
        python3 \
        python3-pip \
        python3-venv
    
    python3 --version
    pip3 --version
    
    print_success "Python 3 installed"
}

install_composer() {
    print_header "Installing Composer"
    
    if command -v composer &> /dev/null; then
        print_warning "Composer is already installed"
        composer --version
        return
    fi
    
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    composer --version
    
    print_success "Composer installed"
}

install_nodejs() {
    print_header "Installing Node.js 18.x"
    
    if command -v node &> /dev/null; then
        NODE_CURRENT=$(node -v)
        print_warning "Node.js $NODE_CURRENT is already installed"
        read -p "Do you want to install Node.js 18.x anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return
        fi
    fi
    
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt install -y nodejs
    
    node -v
    npm -v
    
    print_success "Node.js installed"
}

install_nginx() {
    print_header "Installing Nginx"
    
    if command -v nginx &> /dev/null; then
        print_warning "Nginx is already installed"
        nginx -v
        return
    fi
    
    apt install -y nginx
    
    systemctl enable nginx
    systemctl start nginx
    
    nginx -v
    
    print_success "Nginx installed"
}

setup_application() {
    print_header "Setting Up Application"
    
    # Check if we're already in the install directory
    if [ "$PWD" = "$INSTALL_DIR" ]; then
        print_warning "Already in installation directory"
    else
        print_error "This script should be run from: $INSTALL_DIR"
        print_error "Current directory: $PWD"
        exit 1
    fi
    
    print_success "Application directory verified"
}

install_python_dependencies() {
    print_header "Installing Python Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis"
    
    # Create virtual environment
    if [ ! -d "venv" ]; then
        python3 -m venv venv
        print_success "Virtual environment created"
    else
        print_warning "Virtual environment already exists"
    fi
    
    # Activate and install
    source venv/bin/activate
    pip install --upgrade pip
    pip install -r requirements.txt
    
    # Install Playwright
    playwright install firefox
    playwright install-deps firefox
    
    deactivate
    
    print_success "Python dependencies installed"
}

install_php_dependencies() {
    print_header "Installing PHP Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Install Composer dependencies
    composer install --no-dev --optimize-autoloader
    
    print_success "PHP dependencies installed"
}

install_node_dependencies() {
    print_header "Installing Node.js Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Install npm dependencies
    npm install
    
    # Build assets
    npm run build
    
    print_success "Node.js dependencies installed"
}

setup_environment() {
    print_header "Setting Up Environment Files"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Laravel .env
    if [ ! -f ".env" ]; then
        cp .env.example .env
        php artisan key:generate
        print_success "Laravel .env created"
    else
        print_warning "Laravel .env already exists"
    fi
    
    print_success "Environment files configured"
}

setup_database() {
    print_header "Setting Up Database"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Create database file
    if [ ! -f "database/database.sqlite" ]; then
        touch database/database.sqlite
        print_success "Database file created"
    else
        print_warning "Database file already exists"
        read -p "Do you want to recreate the database? This will delete all data! (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rm -f database/database.sqlite
            touch database/database.sqlite
            print_success "Database recreated"
        fi
    fi
    
    # Run migrations
    php artisan migrate --force
    
    # Seed admin user
    php artisan db:seed --class=DatabaseSeeder
    
    print_success "Database initialized"
    print_warning "Default admin credentials:"
    echo "  Email: admin@scraper.local"
    echo "  Password: password"
    echo ""
    echo -e "${YELLOW}⚠ IMPORTANT: Change this password after first login!${NC}"
}

setup_directories() {
    print_header "Creating Required Directories"
    
    # Python scraper directories
    mkdir -p "$INSTALL_DIR/scrapper-alexis/data/message_images"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/data/auth_states"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/logs"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/debug_output"
    
    # Backup directory
    mkdir -p "$INSTALL_DIR/backups"
    
    print_success "Directories created"
}

setup_permissions() {
    print_header "Setting File Permissions"
    
    # Set ownership for entire directory
    chown -R $WEBUSER:$WEBUSER "$INSTALL_DIR"
    
    # Laravel directories
    cd "$INSTALL_DIR/scrapper-alexis-web"
    chmod -R 775 storage bootstrap/cache
    chmod 775 database/
    chmod 664 database/database.sqlite
    
    # Python scraper directories
    cd "$INSTALL_DIR/scrapper-alexis"
    chmod 755 run_*.sh
    chmod 775 data/ logs/ debug_output/
    chmod 775 data/message_images data/auth_states
    
    # Keep venv owned by current user for development
    if [ -d "venv" ]; then
        chown -R $SUDO_USER:$SUDO_USER venv/
    fi
    
    print_success "Permissions set"
}

setup_nginx() {
    print_header "Configuring Nginx"
    
    NGINX_CONF="$INSTALL_DIR/scrapper-alexis-web/nginx.conf"
    NGINX_AVAILABLE="/etc/nginx/sites-available/scraper-admin"
    NGINX_ENABLED="/etc/nginx/sites-enabled/scraper-admin"
    
    if [ ! -f "$NGINX_CONF" ]; then
        print_error "Nginx configuration file not found: $NGINX_CONF"
        return
    fi
    
    # Copy configuration
    cp "$NGINX_CONF" "$NGINX_AVAILABLE"
    
    # Create symlink if not exists
    if [ ! -L "$NGINX_ENABLED" ]; then
        ln -s "$NGINX_AVAILABLE" "$NGINX_ENABLED"
        print_success "Nginx site enabled"
    else
        print_warning "Nginx site already enabled"
    fi
    
    # Test configuration
    nginx -t
    
    # Reload Nginx
    systemctl reload nginx
    
    print_success "Nginx configured"
}

setup_cron() {
    print_header "Configuring Cron Jobs"
    
    # Check if cron entry already exists
    if crontab -u $WEBUSER -l 2>/dev/null | grep -q "schedule:run"; then
        print_warning "Cron job already exists"
        return
    fi
    
    # Add cron entry for Laravel scheduler
    (crontab -u $WEBUSER -l 2>/dev/null; echo "* * * * * cd $INSTALL_DIR/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1") | crontab -u $WEBUSER -
    
    print_success "Cron job configured"
    
    # Verify
    echo ""
    echo "Crontab for $WEBUSER:"
    crontab -u $WEBUSER -l
    echo ""
}

restart_services() {
    print_header "Restarting Services"
    
    systemctl restart php${PHP_VERSION}-fpm
    systemctl restart nginx
    
    print_success "Services restarted"
}

print_final_info() {
    print_header "Installation Complete!"
    
    echo -e "${GREEN}✓ All components installed successfully!${NC}"
    echo ""
    echo "================================================================"
    echo ""
    echo "🎉 Next Steps:"
    echo ""
    echo "1. Access the admin panel:"
    echo "   http://$(hostname -I | awk '{print $1}')"
    echo ""
    echo "2. Login with default credentials:"
    echo "   Email: admin@scraper.local"
    echo "   Password: password"
    echo ""
    echo "3. ⚠️  IMPORTANT: Change the default password!"
    echo ""
    echo "4. Configure scraper settings:"
    echo "   - Go to Settings page"
    echo "   - Add Facebook/Twitter credentials"
    echo "   - Add Facebook profiles to scrape"
    echo "   - Configure intervals"
    echo ""
    echo "5. Test the scraper:"
    echo "   - Go to Dashboard"
    echo "   - Click 'Run Facebook Scraper Now'"
    echo "   - Check logs"
    echo ""
    echo "================================================================"
    echo ""
    echo "📚 Documentation:"
    echo "   - Migration Guide: $INSTALL_DIR/MIGRATION_GUIDE.md"
    echo "   - Quick Start: $INSTALL_DIR/scrapper-alexis-web/QUICKSTART.md"
    echo ""
    echo "📝 Useful Commands:"
    echo "   - View logs: tail -f $INSTALL_DIR/scrapper-alexis/logs/manual_run.log"
    echo "   - Test scheduler: cd $INSTALL_DIR/scrapper-alexis-web && php artisan schedule:run"
    echo "   - Restart services: sudo systemctl restart nginx php${PHP_VERSION}-fpm"
    echo ""
    echo "================================================================"
    echo ""
}

# ============================================================================
# Main Installation Flow
# ============================================================================

main() {
    clear
    
    print_header "Alexis Scraper - Fresh Installation"
    
    echo "This script will install all dependencies and configure the application."
    echo ""
    echo "Installation directory: $INSTALL_DIR"
    echo ""
    read -p "Do you want to continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Installation cancelled."
        exit 0
    fi
    
    # Check if running as root
    check_root
    
    # Installation steps
    install_system_packages
    configure_timezone
    install_php
    install_python
    install_composer
    install_nodejs
    install_nginx
    setup_application
    setup_directories
    install_python_dependencies
    install_php_dependencies
    install_node_dependencies
    setup_environment
    setup_database
    setup_permissions
    setup_nginx
    setup_cron
    restart_services
    
    # Final info
    print_final_info
}

# Run main installation
main


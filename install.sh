#!/bin/bash

# ============================================================================
# Alexis Scraper - Automated Installation Script
# ============================================================================
# This script automatically installs all dependencies and configures the
# Alexis Scraper application on a fresh VPS with ZERO hardcoded paths.
# ============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# Detect Installation Directory (NO HARDCODED PATHS!)
# ============================================================================

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
INSTALL_DIR="$SCRIPT_DIR"

# Configuration
WEBUSER="www-data"
PHP_VERSION="8.2"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Alexis Scraper - Installation${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Installation directory: ${GREEN}${INSTALL_DIR}${NC}"
echo ""

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
        xvfb \
        cron
    
    # Ensure cron is enabled and running
    systemctl enable cron
    systemctl start cron
    
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
        PHP_CURRENT=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "unknown")
        print_warning "PHP $PHP_CURRENT is already installed"
        
        # Check if it's the right version
        if [[ "$PHP_CURRENT" == "$PHP_VERSION"* ]]; then
            print_success "PHP $PHP_VERSION already installed, skipping..."
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
    
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        print_error "Composer installer corrupt"
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php
    rm composer-setup.php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    composer --version
    
    print_success "Composer installed"
}

install_nodejs() {
    print_header "Installing Node.js 18.x"
    
    if command -v node &> /dev/null; then
        NODE_CURRENT=$(node -v 2>/dev/null || echo "unknown")
        print_warning "Node.js $NODE_CURRENT is already installed"
        
        # Check if it's version 18 or higher
        NODE_MAJOR=$(echo "$NODE_CURRENT" | cut -d'v' -f2 | cut -d'.' -f1)
        if [ "$NODE_MAJOR" -ge 18 ] 2>/dev/null; then
            print_success "Node.js 18+ already installed, skipping..."
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
        nginx -v 2>&1
    else
        apt install -y nginx
        print_success "Nginx installed"
    fi
    
    systemctl enable nginx
    systemctl start nginx
}

setup_application() {
    print_header "Verifying Application Directory"
    
    if [ ! -d "$INSTALL_DIR/scrapper-alexis" ] || [ ! -d "$INSTALL_DIR/scrapper-alexis-web" ]; then
        print_error "Application directories not found!"
        print_error "Expected: $INSTALL_DIR/scrapper-alexis and $INSTALL_DIR/scrapper-alexis-web"
        exit 1
    fi
    
    print_success "Application directories verified"
}

setup_directories() {
    print_header "Creating Required Directories"
    
    # Python scraper directories
    mkdir -p "$INSTALL_DIR/scrapper-alexis/data/message_images"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/data/auth_states"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/logs"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/debug_output"
    mkdir -p "$INSTALL_DIR/scrapper-alexis/avatar_cache"
    
    # Laravel directories
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/app/avatars"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/app/public/avatars"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/framework/cache"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/framework/sessions"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/framework/views"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/storage/logs"
    mkdir -p "$INSTALL_DIR/scrapper-alexis-web/bootstrap/cache"
    
    # Backup directory
    mkdir -p "$INSTALL_DIR/backups"
    
    print_success "Directories created"
}

install_python_dependencies() {
    print_header "Installing Python Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis"
    
    # Remove broken symlink if it exists
    if [ -L "venv" ] && [ ! -e "venv" ]; then
        rm venv
        print_warning "Removed broken venv symlink"
    fi
    
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
    
    # Install requirements
    if [ -f "requirements.txt" ]; then
        pip install -r requirements.txt
        print_success "Python packages installed"
    else
        print_error "requirements.txt not found!"
        deactivate
        exit 1
    fi
    
    # Install Playwright and browsers
    playwright install firefox
    playwright install-deps firefox
    
    deactivate
    
    print_success "Python dependencies installed"
}

install_php_dependencies() {
    print_header "Installing PHP Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    if [ ! -f "composer.json" ]; then
        print_error "composer.json not found!"
        exit 1
    fi
    
    # Install Composer dependencies
    composer install --no-dev --optimize-autoloader --no-interaction
    
    print_success "PHP dependencies installed"
}

install_node_dependencies() {
    print_header "Installing Node.js Dependencies"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    if [ ! -f "package.json" ]; then
        print_error "package.json not found!"
        exit 1
    fi
    
    # Install npm dependencies
    npm install
    
    # Build assets
    npm run build
    
    print_success "Node.js dependencies installed and assets built"
}

setup_environment() {
    print_header "Setting Up Environment Files"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Laravel .env
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
            print_success ".env created from .env.example"
        else
            print_error ".env.example not found!"
            exit 1
        fi
        
        # Generate application key
        php artisan key:generate --force
        print_success "Application key generated"
        
        # Update database path in .env to use absolute path
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=$INSTALL_DIR/scrapper-alexis-web/database/database.sqlite|g" .env
        print_success "Database path configured in .env"
    else
        print_warning ".env already exists, skipping..."
    fi
}

setup_database() {
    print_header "Setting Up Database"
    
    cd "$INSTALL_DIR/scrapper-alexis-web"
    
    # Create database file
    if [ ! -f "database/database.sqlite" ]; then
        touch database/database.sqlite
        chmod 664 database/database.sqlite
        print_success "Database file created"
        
        # Run migrations
        php artisan migrate --force
        print_success "Database migrations completed"
        
        # Seed admin user
        php artisan db:seed --class=DatabaseSeeder --force
        print_success "Database seeded with admin user"
        
        echo ""
        print_warning "Default admin credentials:"
        echo "  Email: admin@scraper.local"
        echo "  Password: password"
        echo ""
        echo -e "${YELLOW}⚠ IMPORTANT: Change this password after first login!${NC}"
    else
        print_warning "Database already exists"
        read -p "Do you want to reset the database? This will delete all data! (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rm -f database/database.sqlite
            touch database/database.sqlite
            chmod 664 database/database.sqlite
            php artisan migrate --force
            php artisan db:seed --class=DatabaseSeeder --force
            print_success "Database reset and seeded"
        else
            print_warning "Keeping existing database"
        fi
    fi
}

setup_permissions() {
    print_header "Setting File Permissions"
    
    cd "$INSTALL_DIR"
    
    # Set ownership for entire directory
    chown -R $WEBUSER:$WEBUSER "$INSTALL_DIR"
    
    # Laravel specific permissions
    cd "$INSTALL_DIR/scrapper-alexis-web"
    chmod -R 775 storage bootstrap/cache
    chmod 775 database/
    if [ -f "database/database.sqlite" ]; then
        chmod 664 database/database.sqlite
    fi
    
    # Create storage link
    php artisan storage:link 2>/dev/null || true
    
    # Python scraper permissions
    cd "$INSTALL_DIR/scrapper-alexis"
    
    # Make shell scripts executable
    if ls run_*.sh 1> /dev/null 2>&1; then
        chmod 755 run_*.sh
    fi
    
    # Data directories
    chmod 775 data/ logs/ debug_output/ avatar_cache/ 2>/dev/null || true
    chmod 775 data/message_images data/auth_states 2>/dev/null || true
    
    print_success "Permissions set for www-data user"
}

setup_nginx() {
    print_header "Configuring Nginx"
    
    NGINX_TEMPLATE="$INSTALL_DIR/scrapper-alexis-web/nginx.conf"
    NGINX_AVAILABLE="/etc/nginx/sites-available/scraper-admin"
    NGINX_ENABLED="/etc/nginx/sites-enabled/scraper-admin"
    
    if [ ! -f "$NGINX_TEMPLATE" ]; then
        print_error "Nginx configuration template not found: $NGINX_TEMPLATE"
        return
    fi
    
    # Create Nginx config with dynamic path
    cat > "$NGINX_AVAILABLE" << EOF
server {
    listen 80;
    server_name scraper-admin.local localhost _;
    root $INSTALL_DIR/scrapper-alexis-web/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
    
    print_success "Nginx configuration created with dynamic paths"
    
    # Remove default site if it exists
    if [ -f "/etc/nginx/sites-enabled/default" ]; then
        rm -f /etc/nginx/sites-enabled/default
        print_success "Removed default Nginx site"
    fi
    
    # Create symlink
    if [ ! -L "$NGINX_ENABLED" ]; then
        ln -s "$NGINX_AVAILABLE" "$NGINX_ENABLED"
        print_success "Nginx site enabled"
    else
        print_warning "Nginx site already enabled"
    fi
    
    # Test configuration
    if nginx -t 2>&1; then
        print_success "Nginx configuration test passed"
        systemctl reload nginx
        print_success "Nginx reloaded"
    else
        print_error "Nginx configuration test failed!"
        exit 1
    fi
}

setup_cron() {
    print_header "Configuring Cron Jobs"
    
    # Check if cron entry already exists
    if crontab -u $WEBUSER -l 2>/dev/null | grep -q "schedule:run"; then
        print_warning "Cron job already exists for $WEBUSER"
        return
    fi
    
    # Add cron entry for Laravel scheduler with dynamic path
    (crontab -u $WEBUSER -l 2>/dev/null; echo "* * * * * cd $INSTALL_DIR/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1") | crontab -u $WEBUSER -
    
    print_success "Cron job configured for $WEBUSER"
    
    # Verify
    echo ""
    echo "Crontab for $WEBUSER:"
    crontab -u $WEBUSER -l
    echo ""
}

setup_sudoers() {
    print_header "Configuring Sudoers for www-data"
    
    # Check if sudoers file already exists
    if [ -f "/etc/sudoers.d/scraper-web" ]; then
        print_warning "Sudoers configuration already exists"
        return
    fi
    
    # Create sudoers file to allow www-data to run bash as root
    # This is required for Python scripts that need elevated privileges
    cat > /etc/sudoers.d/scraper-web << 'EOF'
# Allow www-data to run bash as root (for Python scripts)
www-data ALL=(root) NOPASSWD: /bin/bash
EOF
    
    chmod 0440 /etc/sudoers.d/scraper-web
    
    # Verify syntax
    if visudo -c -f /etc/sudoers.d/scraper-web; then
        print_success "Sudoers configuration created and verified"
    else
        print_error "Sudoers configuration has syntax errors!"
        rm -f /etc/sudoers.d/scraper-web
        exit 1
    fi
}

restart_services() {
    print_header "Restarting Services"
    
    systemctl restart php${PHP_VERSION}-fpm
    systemctl restart nginx
    systemctl restart cron
    
    print_success "All services restarted"
}

run_health_check() {
    print_header "Running Post-Installation Health Check"
    
    cd "$INSTALL_DIR"
    
    # Check 1: Timezone
    echo -n "1. Checking timezone... "
    current_tz=$(timedatectl | grep "Time zone" | awk '{print $3}')
    if [[ "$current_tz" == "America/Mexico_City" ]]; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗ (Current: $current_tz)${NC}"
    fi
    
    # Check 2: Cron
    echo -n "2. Checking cron job... "
    if crontab -u $WEBUSER -l 2>/dev/null | grep -q "schedule:run"; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    # Check 3: Database
    echo -n "3. Checking database... "
    if [ -f "$INSTALL_DIR/scrapper-alexis-web/database/database.sqlite" ]; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    # Check 4: Python venv
    echo -n "4. Checking Python venv... "
    if [ -d "$INSTALL_DIR/scrapper-alexis/venv" ]; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    # Check 5: Storage symlink
    echo -n "5. Checking storage symlink... "
    if [ -L "$INSTALL_DIR/scrapper-alexis-web/public/storage" ]; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    # Check 6: Nginx config
    echo -n "6. Checking Nginx config... "
    if nginx -t 2>&1 | grep -q "successful"; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    # Check 7: Services
    echo -n "7. Checking services... "
    if systemctl is-active --quiet nginx && systemctl is-active --quiet php${PHP_VERSION}-fpm; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
    
    echo ""
}

print_final_info() {
    print_header "Installation Complete!"
    
    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')
    
    echo -e "${GREEN}✓ All components installed successfully!${NC}"
    echo ""
    echo "================================================================"
    echo ""
    echo "🎉 Your Alexis Scraper is ready!"
    echo ""
    echo "📍 Installation Location:"
    echo "   $INSTALL_DIR"
    echo ""
    echo "🌐 Access the Admin Panel:"
    echo "   http://$SERVER_IP"
    echo ""
    echo "🔐 Default Login Credentials:"
    echo "   Email:    admin@scraper.local"
    echo "   Password: password"
    echo ""
    echo -e "${YELLOW}⚠️  CRITICAL: Change the default password immediately!${NC}"
    echo ""
    echo "================================================================"
    echo ""
    echo "📝 Next Steps:"
    echo ""
    echo "1. Open your browser and go to: http://$SERVER_IP"
    echo "2. Login with the default credentials above"
    echo "3. Change your password (Profile → Change Password)"
    echo "4. Configure scraper settings (Settings page):"
    echo "   - Facebook credentials"
    echo "   - Twitter credentials"
    echo "   - Twitter profile info (display name, avatar, verified)"
    echo "   - Facebook profiles to monitor"
    echo "   - Scraping intervals"
    echo "   - Proxy settings (optional)"
    echo "5. Test the scraper (Dashboard):"
    echo "   - Click 'Run Facebook Scraper Now'"
    echo "   - Click 'Run Twitter Flow Now'"
    echo "6. Monitor logs:"
    echo "   - tail -f $INSTALL_DIR/scrapper-alexis/logs/manual_run.log"
    echo ""
    echo "================================================================"
    echo ""
    echo "📚 Documentation:"
    echo "   $INSTALL_DIR/INSTALLATION.md"
    echo ""
    echo "🔧 Useful Commands:"
    echo "   - Fix permissions:  cd $INSTALL_DIR/scrapper-alexis-web && sudo ./setup.sh"
    echo "   - Test scheduler:   cd $INSTALL_DIR/scrapper-alexis-web && php artisan schedule:run"
    echo "   - View logs:        tail -f $INSTALL_DIR/scrapper-alexis/logs/manual_run.log"
    echo "   - Restart services: sudo systemctl restart nginx php${PHP_VERSION}-fpm"
    echo ""
    echo "================================================================"
    echo ""
    echo -e "${GREEN}Happy scraping! 🚀${NC}"
    echo ""
}

# ============================================================================
# Main Installation Flow
# ============================================================================

main() {
    clear
    
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║   Alexis Scraper - Installation       ║${NC}"
    echo -e "${BLUE}║   Dynamic Path Detection Enabled       ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""
    echo "This script will automatically install all dependencies"
    echo "and configure the application."
    echo ""
    echo -e "Installation directory: ${GREEN}${INSTALL_DIR}${NC}"
    echo ""
    echo "The installation will take approximately 10-15 minutes."
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
    setup_sudoers
    restart_services
    
    # Post-installation checks
    run_health_check
    
    # Final info
    print_final_info
}

# Run main installation
main "$@"

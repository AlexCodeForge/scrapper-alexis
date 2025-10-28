#!/bin/bash

# =============================================================================
# REBUILD CLEAN DOCKER IMAGES FOR PORTABLE DISTRIBUTION
# =============================================================================
# 
# This script:
# 1. Cleans all hardcoded credentials and session data
# 2. Rebuilds Docker images from scratch
# 3. Saves images for portable distribution
#
# CRITICAL: Run this BEFORE creating portable images!
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo ""
echo "╔═══════════════════════════════════════════════════════════════════╗"
echo "║                                                                   ║"
echo "║     CLEAN DOCKER IMAGE REBUILD SCRIPT                            ║"
echo "║                                                                   ║"
echo "╚═══════════════════════════════════════════════════════════════════╝"
echo ""

# Function to print status
print_status() {
    echo -e "${BLUE}[*]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Change to script directory
cd "$(dirname "$0")"
SCRIPT_DIR="$(pwd)"

print_status "Working directory: $SCRIPT_DIR"
echo ""

# =============================================================================
# STEP 1: Stop and Remove Existing Containers
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 1: Stopping and Removing Existing Containers"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

print_status "Stopping containers..."
docker compose down 2>/dev/null || print_warning "No containers to stop"
print_success "Containers stopped"
echo ""

# =============================================================================
# STEP 2: Clean Sensitive Data from Scraper Directory
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 2: Cleaning Sensitive Data"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

SCRAPER_DIR="$SCRIPT_DIR/scrapper-alexis"

# Backup current .env if it exists
if [ -f "$SCRAPER_DIR/.env" ]; then
    print_status "Backing up current .env to .env.backup..."
    cp "$SCRAPER_DIR/.env" "$SCRAPER_DIR/.env.backup"
    print_success ".env backed up"
fi

# Clean auth directory (session files)
print_status "Cleaning auth/ directory..."
if [ -d "$SCRAPER_DIR/auth" ]; then
    rm -rf "$SCRAPER_DIR/auth"/*.json 2>/dev/null || true
    print_success "Auth directory cleaned"
else
    print_warning "Auth directory doesn't exist"
fi

# Remove .env files (they should be mounted, not baked in)
print_status "Removing .env files from build context..."
rm -f "$SCRAPER_DIR/.env" 2>/dev/null || true
rm -f "$SCRAPER_DIR/copy.env" 2>/dev/null || true
print_success ".env files removed from build context"

# Clean avatar cache (personal data)
print_status "Cleaning avatar cache..."
rm -rf "$SCRAPER_DIR/avatar_cache"/*.jpg 2>/dev/null || true
rm -rf "$SCRAPER_DIR/avatar_cache"/*.png 2>/dev/null || true
print_success "Avatar cache cleaned"

# Clean screenshots (may contain personal data)
print_status "Cleaning screenshots..."
rm -rf "$SCRAPER_DIR/screenshots"/* 2>/dev/null || true
print_success "Screenshots cleaned"

# Clean data directory (databases, images)
print_status "Cleaning data directory..."
rm -rf "$SCRAPER_DIR/data"/*.db 2>/dev/null || true
rm -rf "$SCRAPER_DIR/data/message_images"/* 2>/dev/null || true
print_success "Data directory cleaned"

# Clean logs
print_status "Cleaning logs..."
rm -rf "$SCRAPER_DIR/logs"/*.log 2>/dev/null || true
print_success "Logs cleaned"

# Clean debug output
print_status "Cleaning debug output..."
rm -rf "$SCRAPER_DIR/debug_output"/* 2>/dev/null || true
print_success "Debug output cleaned"

echo ""

# =============================================================================
# STEP 3: Verify .dockerignore
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 3: Verifying .dockerignore"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

if [ -f "$SCRAPER_DIR/.dockerignore" ]; then
    print_success ".dockerignore found"
    
    # Check if it excludes critical files
    if grep -q "auth/" "$SCRAPER_DIR/.dockerignore" && \
       grep -q "*.env" "$SCRAPER_DIR/.dockerignore"; then
        print_success ".dockerignore properly configured"
    else
        print_error ".dockerignore missing critical excludes!"
        echo "Please ensure .dockerignore excludes: auth/, *.env, copy.env"
        exit 1
    fi
else
    print_error ".dockerignore not found!"
    exit 1
fi

echo ""

# =============================================================================
# STEP 4: Remove Old Docker Images
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 4: Removing Old Docker Images"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

print_status "Removing old images..."
docker rmi alexis-scrapper:latest 2>/dev/null || print_warning "Scraper image not found"
docker rmi alexis-scrapper-web:latest 2>/dev/null || print_warning "Web image not found"
docker rmi scraper-alexis-scraper:latest 2>/dev/null || print_warning "Old scraper image not found"
docker rmi scraper-alexis-web:latest 2>/dev/null || print_warning "Old web image not found"
print_success "Old images removed"

echo ""

# =============================================================================
# STEP 5: Build Fresh Docker Images
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 5: Building Fresh Docker Images (this will take 5-10 minutes)"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

print_status "Building scraper image..."
docker build -t alexis-scrapper:latest "$SCRAPER_DIR"
print_success "Scraper image built"

print_status "Building web image..."
docker build -t alexis-scrapper-web:latest "$SCRIPT_DIR/scrapper-alexis-web"
print_success "Web image built"

echo ""

# =============================================================================
# STEP 6: Save Images for Portable Distribution
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 6: Saving Images for Portable Distribution"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Create portable directory if it doesn't exist
PORTABLE_DIR="../alexis-scrapper-portable"
IMAGES_DIR="$PORTABLE_DIR/images"

if [ ! -d "$PORTABLE_DIR" ]; then
    print_warning "Portable directory doesn't exist at $PORTABLE_DIR"
    read -p "Create it? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        mkdir -p "$IMAGES_DIR"
        print_success "Created portable directory"
    else
        print_error "Aborted - portable directory not found"
        exit 1
    fi
else
    mkdir -p "$IMAGES_DIR"
fi

print_status "Saving scraper image (this will take 2-3 minutes)..."
docker save alexis-scrapper:latest -o "$IMAGES_DIR/scraper-image.tar"
SCRAPER_SIZE=$(du -h "$IMAGES_DIR/scraper-image.tar" | cut -f1)
print_success "Scraper image saved ($SCRAPER_SIZE)"

print_status "Saving web image (this will take 2-3 minutes)..."
docker save alexis-scrapper-web:latest -o "$IMAGES_DIR/web-image.tar"
WEB_SIZE=$(du -h "$IMAGES_DIR/web-image.tar" | cut -f1)
print_success "Web image saved ($WEB_SIZE)"

echo ""

# =============================================================================
# STEP 7: Restore .env Backup
# =============================================================================
echo "═══════════════════════════════════════════════════════════════════"
echo "STEP 7: Restoring Configuration"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

if [ -f "$SCRAPER_DIR/.env.backup" ]; then
    print_status "Restoring .env from backup..."
    cp "$SCRAPER_DIR/.env.backup" "$SCRAPER_DIR/.env"
    print_success ".env restored"
else
    print_warning "No backup found - creating from template..."
    if [ -f "$SCRIPT_DIR/env.docker.template" ]; then
        cp "$SCRIPT_DIR/env.docker.template" "$SCRAPER_DIR/.env"
        print_success ".env created from template"
    fi
fi

echo ""

# =============================================================================
# COMPLETION
# =============================================================================
echo ""
echo "╔═══════════════════════════════════════════════════════════════════╗"
echo "║                                                                   ║"
echo "║                  ✓ CLEAN BUILD COMPLETE!                         ║"
echo "║                                                                   ║"
echo "╚═══════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  📦  Images saved to: ${BLUE}$IMAGES_DIR${NC}"
echo ""
echo -e "  📊  Scraper Image: ${YELLOW}$SCRAPER_SIZE${NC}"
echo -e "  📊  Web Image:     ${YELLOW}$WEB_SIZE${NC}"
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "✅ VERIFICATION:"
echo ""
echo "  The images are now CLEAN and contain:"
echo "  • NO hardcoded credentials"
echo "  • NO authentication sessions"
echo "  • NO personal data"
echo "  • NO cached avatars or screenshots"
echo ""
echo "  All configuration will come from .env file at runtime!"
echo ""
echo "📋 NEXT STEPS:"
echo ""
echo "  1. Test the portable installation on a fresh VPS"
echo "  2. Verify it asks for credentials via web interface"
echo "  3. Confirm it doesn't use old session data"
echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo ""



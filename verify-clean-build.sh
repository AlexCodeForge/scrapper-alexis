#!/bin/bash

# =============================================================================
# VERIFY CLEAN BUILD - Check for hardcoded data in Docker images
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "╔═══════════════════════════════════════════════════════════════════╗"
echo "║           VERIFY CLEAN BUILD - Security Check                     ║"
echo "╚═══════════════════════════════════════════════════════════════════╝"
echo ""

ISSUES=0

# Check if scraper image exists
if ! docker image inspect alexis-scrapper:latest &>/dev/null; then
    echo -e "${RED}[✗]${NC} Scraper image not found!"
    exit 1
fi

echo -e "${GREEN}[✓]${NC} Scraper image found"
echo ""
echo "Checking for hardcoded data..."
echo ""

# Create temporary container to inspect
TEMP_CONTAINER=$(docker create alexis-scrapper:latest)

# Check for .env files
echo -n "Checking for .env files in image... "
if docker export $TEMP_CONTAINER | tar -t | grep -q "\.env$"; then
    echo -e "${RED}FOUND${NC} ⚠️"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Check for copy.env
echo -n "Checking for copy.env in image... "
if docker export $TEMP_CONTAINER | tar -t | grep -q "copy\.env$"; then
    echo -e "${RED}FOUND${NC} ⚠️"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Check for auth sessions
echo -n "Checking for auth session files... "
if docker export $TEMP_CONTAINER | tar -t | grep -q "auth.*\.json$"; then
    echo -e "${RED}FOUND${NC} ⚠️"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Check for database files
echo -n "Checking for database files... "
if docker export $TEMP_CONTAINER | tar -t | grep -q "\.db$"; then
    echo -e "${YELLOW}FOUND${NC} ℹ️ (may be okay if empty)"
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Check for hardcoded profile info in generate_message_images.py
echo -n "Checking for hardcoded 'El Emiliano Zapata'... "
if docker export $TEMP_CONTAINER | tar -xO app/generate_message_images.py 2>/dev/null | grep -q "El Emiliano Zapata"; then
    echo -e "${RED}FOUND${NC} ⚠️"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Check for hardcoded @soyemizapata
echo -n "Checking for hardcoded '@soyemizapata'... "
if docker export $TEMP_CONTAINER | tar -xO app/generate_message_images.py 2>/dev/null | grep -q "@soyemizapata"; then
    echo -e "${RED}FOUND${NC} ⚠️"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}CLEAN${NC} ✓"
fi

# Cleanup
docker rm $TEMP_CONTAINER >/dev/null

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo ""

if [ $ISSUES -eq 0 ]; then
    echo -e "${GREEN}✓ BUILD IS CLEAN!${NC}"
    echo ""
    echo "No hardcoded credentials or personal data found."
    echo "Image is safe for distribution."
    echo ""
    exit 0
else
    echo -e "${RED}✗ BUILD HAS ISSUES!${NC}"
    echo ""
    echo "Found $ISSUES potential issue(s) with hardcoded data."
    echo "Please review and rebuild with clean data."
    echo ""
    exit 1
fi



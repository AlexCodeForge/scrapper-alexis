#!/bin/bash

# Clean install script for Docker setup
# WARNING: This will DELETE all existing data!

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║        CLEAN INSTALL - THIS WILL DELETE ALL DATA!             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "This script will:"
echo "  1. Stop all containers"
echo "  2. Remove all volumes (DATABASE AND IMAGES WILL BE DELETED!)"
echo "  3. Rebuild containers from scratch"
echo "  4. Start with fresh database"
echo ""
read -p "Are you sure? Type 'yes' to continue: " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

cd "$(dirname "$0")"

echo ""
echo "================================================"
echo "Step 1: Stopping containers..."
echo "================================================"
docker compose down

echo ""
echo "================================================"
echo "Step 2: Removing volumes and cleaning up..."
echo "================================================"
docker compose down -v

# Remove any orphaned volumes from previous installations
echo "Cleaning up old volumes..."
docker volume ls -q | grep -E "(scraper_|www_)" | xargs -r docker volume rm 2>/dev/null || true
echo "✓ Old volumes cleaned"

echo ""
echo "================================================"
echo "Step 3: Removing old images..."
echo "================================================"
docker compose down --rmi local

echo ""
echo "================================================"
echo "Step 4: Rebuilding containers..."
echo "================================================"
docker compose build --no-cache

echo ""
echo "================================================"
echo "Step 5: Starting fresh containers..."
echo "================================================"
docker compose up -d

echo ""
echo "================================================"
echo "Step 6: Waiting for containers to initialize..."
echo "================================================"
sleep 10

echo ""
echo "================================================"
echo "Checking container status..."
echo "================================================"
docker compose ps

echo ""
echo "================================================"
echo "Checking web container logs..."
echo "================================================"
docker compose logs web | tail -20

echo ""
echo "================================================"
echo "Step 7: Initializing fresh database..."
echo "================================================"
docker compose exec -T web php artisan db:seed --force

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    CLEAN INSTALL COMPLETE!                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "🌐 Web interface: http://localhost:8080"
echo "👤 Admin login: admin@scraper.local / password"
echo ""
echo "The database has been initialized with:"
echo "  ✓ Fresh empty SQLite database"
echo "  ✓ Python scraper schema created"
echo "  ✓ Laravel migrations applied"
echo "  ✓ Admin user seeded"
echo "  ✓ 0 messages, 0 images (clean start)"
echo ""
echo "Next steps:"
echo "  • Access the web interface"
echo "  • Configure Facebook/Twitter credentials in Settings"
echo "  • Run manual scraper test or wait for cron"
echo ""


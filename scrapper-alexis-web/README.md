# Scraper Admin Panel

A Laravel-based web application for managing Facebook/Twitter scraping and automated posting.

## Features

- Facebook profile scraping
- Automated image generation from scraped messages
- Facebook page posting with scheduling
- Web-based management interface
- Real-time logs viewing
- Portable installation (no hardcoded paths)

## Directory Structure

This application requires two sibling directories:
```
parent-directory/
├── scrapper-alexis-web/  (this Laravel app)
└── scrapper-alexis/      (Python scraper scripts)
```

## Quick Start

1. Install dependencies:
```bash
composer install
npm install && npm run build
```

2. Setup environment:
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

3. Configure the scheduler (add to crontab):
```bash
* * * * * cd /path/to/scrapper-alexis-web && php artisan schedule:run >> /dev/null 2>&1
```

4. Access the application and configure:
- Default URL: http://localhost:8000
- Login with configured credentials
- Go to Settings to configure scrapers

## Configuration

### Custom Paths

If your directory structure differs, set these in `.env`:

```env
# Parent directory containing both web and Python directories
SCRAPER_PYTHON_PATH=/custom/path

# Python logs directory (relative to SCRAPER_PYTHON_PATH)
SCRAPER_LOGS_DIR=scrapper-alexis/logs

# Python data directory (relative to SCRAPER_PYTHON_PATH)
SCRAPER_DATA_DIR=scrapper-alexis/data
```

### Scheduled Jobs

The application runs these scheduled jobs:
- **Facebook Scraper**: Hourly (when enabled)
- **Image Generator**: Every 5 minutes (when enabled)
- **Page Poster**: Every 30 minutes (when enabled)
- **Cleanup**: Daily at 2 AM (when enabled)

## Portability

✅ **Fully Portable**: No hardcoded paths!  
✅ **Auto-Detection**: Automatically finds sibling directories  
✅ **Customizable**: Override paths via environment variables

See [INSTALLATION.md](../INSTALLATION.md) for detailed setup instructions.

## Tech Stack

- Laravel 12
- Livewire 3.6
- Tailwind CSS 4.0
- Alpine.js 3.15
- SQLite database
- Python 3 (for scraper scripts)

## License

MIT

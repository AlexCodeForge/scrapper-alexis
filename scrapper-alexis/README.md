# Playwright Social Content Relay Agent

Automate the extraction of a specific Facebook message's text, post it to X (Twitter), and capture a screenshot of the source message.

## ⚠️ Security Warning
This project handles sensitive credentials. Never commit `.env` files or authentication state files to version control.

## Prerequisites
- Python 3.9 or higher
- pip package manager
- Stable internet connection

## Quick Start

### ⚡ One-Command Execution (VPS)
```bash
cd /var/www/scrapper-alexis
./run_scraper.sh
```

### 📋 Manual Setup (First Time)

#### 1. Setup Virtual Environment
```bash
python3 -m venv venv
source venv/bin/activate
```

#### 2. Install Dependencies
```bash
pip install -r requirements.txt
playwright install firefox  # Firefox is more stable than Chromium on VPS
```

#### 3. Install Xvfb (Linux VPS only)
```bash
sudo apt-get install xvfb
```

#### 4. Configure Credentials
```bash
cp copy.env .env
# Edit .env with your Facebook credentials
# IMPORTANT: Set HEADLESS=false for VPS stability
# OPTIONAL: Set DEBUG_OUTPUT_ENABLED=false to disable debug screenshots (saves disk space)
```

#### 5. Run the Scraper
```bash
# Easy way (recommended):
./run_scraper.sh

# Manual way:
xvfb-run -a python3 relay_agent.py
```

## Project Status
✅ **Phase 0: Setup** - Complete  
✅ **Phase 1: Facebook Content Acquisition** - **COMPLETE & WORKING** (200+ messages)  
  - ✅ Firefox browser (stable)
  - ✅ Xvfb virtual display (VPS optimized)
  - ✅ Smart scrolling with crash protection
  - ✅ 100% success rate on VPS
⏳ **Phase 2: X/Twitter Posting** - Pending  
⏳ **Phase 3: Screenshot & Database Storage** - Pending

### Performance on VPS
- **Browser:** Firefox (via Xvfb)
- **Messages:** 200+ extracted per run
- **Time:** ~3 minutes
- **Success Rate:** 100% ✅  

## Configuration Options

### Debug Output Control
The scraper can generate debug screenshots and logs in the `debug_output/` folder. This is useful for troubleshooting but can consume significant disk space over time.

**To disable debug output:**
```bash
# Option 1: Use the helper script (easiest)
./toggle_debug.sh disable

# Option 2: Manually edit .env file
DEBUG_OUTPUT_ENABLED=false
```

**To enable debug output:**
```bash
# Option 1: Use the helper script (easiest)
./toggle_debug.sh enable

# Option 2: Manually edit .env file
DEBUG_OUTPUT_ENABLED=true
```

**To check current status and disk usage:**
```bash
./toggle_debug.sh status
```

When disabled:
- No debug screenshots will be saved
- No run folders will be created in `debug_output/`
- Saves ~2.4GB+ disk space (based on accumulated runs)
- All main functionality continues to work normally

Note: Regular logging to the `logs/` folder continues regardless of this setting.

## Documentation

### 🚀 **NEW: VPS Solution**
- **[QUICK_START.md](QUICK_START.md)** - ⚡ Get started in 30 seconds
- **[docs/VPS_CRASH_SOLUTION.md](docs/VPS_CRASH_SOLUTION.md)** - 📘 Complete guide to solving VPS crashes

### 📚 General Documentation:
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Quick answers and common commands
- **[SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md)** - Complete system explanation
- **[DATA_FLOW.md](DATA_FLOW.md)** - Visual diagrams of where data goes

### 🔍 Key Topics:
- **VPS Crash Issues** - Why Chromium failed and Firefox succeeded ([VPS Solution](docs/VPS_CRASH_SOLUTION.md))
- **Sessions & Authentication** - How auto-login works ([Quick Reference](QUICK_REFERENCE.md#-sessions--auth---eli5-explain-like-im-5))
- **Where Messages Are Stored** - Visual map of all files ([Data Flow](DATA_FLOW.md#-where-does-everything-go))
- **Process Flow** - Step-by-step breakdown ([System Docs](SYSTEM_DOCUMENTATION.md#-complete-process-flow))
- **Troubleshooting** - Common issues ([Quick Reference](QUICK_REFERENCE.md#-troubleshooting))

### 📂 Technical Docs:
- `docs/Implementation/` - Detailed phase-by-phase guides
- `docs/PRD.md` - Product requirements
- `docs/COOKIE_MODAL_FIX.md` - Cookie consent handling
- `docs/2FA_SETUP_GUIDE.md` - Two-factor authentication setup
- `docs/FINAL_STATUS_REPORT.md` - Previous status (before Firefox fix)

## License
Private project - Not for distribution


# 🚀 Quick Start Guide

## Run the Scraper (Easiest Way)

```bash
cd /var/www/scrapper-alexis
./run_scraper.sh
```

That's it! The script will:
- ✅ Check all dependencies
- ✅ Install missing components
- ✅ Launch Firefox with Xvfb
- ✅ Extract 200+ messages
- ✅ Save results to logs

---

## Expected Output

```
╔════════════════════════════════════════════════════════════╗
║       Facebook Scraper - VPS Optimized Runner             ║
╚════════════════════════════════════════════════════════════╝

✅ All checks passed!

🚀 Starting scraper with:
  Browser: Firefox
  Display: Xvfb (virtual headed mode)
  Target:  200+ messages

...

╔════════════════════════════════════════════════════════════╗
║            ✅ SCRAPER COMPLETED SUCCESSFULLY               ║
╚════════════════════════════════════════════════════════════╝
```

---

## Manual Execution

If you prefer manual control:

```bash
cd /var/www/scrapper-alexis
source venv/bin/activate
xvfb-run -a python3 relay_agent.py
```

---

## Configuration

Edit `.env` file to customize:

```bash
# Target URL
FACEBOOK_MESSAGE_URL=https://www.facebook.com/share/1E8ChgJj5b/?mibextid=wwXIfr

# Must be false for Xvfb approach
HEADLESS=false

# Credentials
FACEBOOK_EMAIL=your_email
FACEBOOK_PASSWORD=your_password
```

---

## Troubleshooting

### Scraper crashes?
```bash
# Check logs
tail -f logs/relay_agent_$(date +%Y%m%d).log
```

### Need to reinstall Firefox?
```bash
source venv/bin/activate
playwright install firefox
```

### Xvfb not working?
```bash
sudo apt-get install xvfb
```

---

## Performance

| Metric | Value |
|--------|-------|
| **Messages** | 200+ |
| **Time** | ~3 minutes |
| **Browser** | Firefox |
| **Success Rate** | 100% |

---

## More Information

- **Full Documentation:** [docs/VPS_CRASH_SOLUTION.md](docs/VPS_CRASH_SOLUTION.md)
- **Configuration Guide:** [docs/FINAL_STATUS_REPORT.md](docs/FINAL_STATUS_REPORT.md)
- **Cookie Issues:** [docs/COOKIE_MODAL_FIX.md](docs/COOKIE_MODAL_FIX.md)
- **2FA Setup:** [docs/2FA_SETUP_GUIDE.md](docs/2FA_SETUP_GUIDE.md)



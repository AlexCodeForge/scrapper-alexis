# 🚀 START HERE - Quick Reference

## ⚠️ CRITICAL: TWO IMPORTANT FIXES APPLIED!

### 1. VPS Crash Fix (Firefox + Xvfb)
```
✅ Browser: Firefox (Chromium crashes on VPS!)
✅ Mode: HEADLESS=false
✅ Display: Xvfb (virtual display)
✅ Docker: 24 containers stopped, 5.9GB RAM freed
```

### 2. Proxy Configuration (Mandatory for Twitter)
```
✅ PROXY_SERVER=http://77.47.156.7:50100
✅ PROXY_USERNAME=gNhwRLuC
✅ PROXY_PASSWORD=OZ7h82Gknc
```

**See:** `VPS_CRASH_FIX_APPLIED.md` & `PROXY_CRITICAL_README.md`

---

## 3-Step Quick Start

### 1️⃣ Test Workflows
```bash
cd /var/www/scrapper-alexis
bash test_workflows.sh
```

### 2️⃣ Install Cronjobs
```bash
bash setup_cron.sh
```

### 3️⃣ Monitor
```bash
bash monitor_status.sh
```

---

## 📋 Your Testing Schedule

| Task | Frequency | What It Does |
|------|-----------|--------------|
| **Facebook Scraper** | Every 1 hour | Scrapes ~20 messages per profile |
| **Twitter Poster** | Every 8 minutes | Posts 1 message to Twitter |
| **Image Generator** | After each post | Creates image of the tweet |

**Expected:** 7-8 tweets per hour during testing

---

## 🔍 Quick Commands

```bash
# Check if cronjobs are running
crontab -l

# View system status
bash monitor_status.sh

# Watch logs live
tail -f logs/cron_*.log

# Stop cronjobs
crontab -r
```

---

## 📖 Documentation

1. **PROXY_CRITICAL_README.md** ⚠️ Read first!
2. **QUICK_START_CRON.md** - Quick guide
3. **CHANGES_SUMMARY.md** - What changed
4. **CRON_SETUP.md** - Complete guide

---

## ✅ What Was Fixed

- ✅ Proxy added to `copy.env`
- ✅ Facebook scraper NOW uses proxy
- ✅ All Twitter scripts use proxy
- ✅ Cronjob automation ready
- ✅ Monitoring tools included

---

## 🆘 Need Help?

**Proxy issues:** See `PROXY_CRITICAL_README.md`  
**Setup help:** See `CRON_SETUP.md`  
**All changes:** See `CHANGES_SUMMARY.md`

---

**Ready to go! Run:** `bash test_workflows.sh`


# ⚠️ CRITICAL: Proxy Configuration

## THIS IS MANDATORY - READ FIRST!

**Twitter WILL NOT WORK without the proxy!**  
**Facebook is also strongly recommended to use the proxy!**

---

## ✅ What Was Fixed

### Before (BROKEN):
- ❌ Proxy was hardcoded in multiple files
- ❌ Facebook scraper had NO proxy at all
- ❌ Twitter would fail without proxy
- ❌ Inconsistent configuration

### After (FIXED):
- ✅ Proxy centralized in `copy.env`
- ✅ Facebook scraper now uses proxy
- ✅ Twitter uses proxy (MANDATORY)
- ✅ Image generator uses proxy for avatar downloads
- ✅ All scripts use same proxy config

---

## 📋 Configuration

### In `copy.env`:

```env
# Proxy Configuration (CRITICAL - Twitter requires this!)
PROXY_SERVER=http://77.47.156.7:50100
PROXY_USERNAME=gNhwRLuC
PROXY_PASSWORD=OZ7h82Gknc
```

**THESE MUST BE SET!** Without them:
- Twitter posting will fail
- Twitter avatar downloads will fail  
- Facebook may be blocked or rate-limited

---

## 🔍 How to Verify Proxy is Working

### Check Facebook Scraper:
```bash
cd /var/www/scrapper-alexis
python3 relay_agent.py
```

Look for this line in the output:
```
🔒 Using proxy: http://77.47.156.7:50100
```

If you see:
```
⚠️  No proxy configured - this may cause issues!
```
**STOP! Fix your copy.env file!**

### Check Twitter Poster:
```bash
cd /var/www/scrapper-alexis
python3 -m twitter.twitter_post
```

If proxy is missing, it will exit immediately with:
```
⚠️  ERROR: No proxy configured! Twitter REQUIRES proxy!
   Add PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD to copy.env
```

---

## 🛠 What Files Were Updated

1. **`copy.env`** - Added proxy configuration
2. **`config.py`** - Added PROXY_CONFIG import from env
3. **`relay_agent.py`** - Facebook scraper now uses proxy
4. **`twitter/twitter_post.py`** - Uses config.PROXY_CONFIG
5. **`twitter/twitter.py`** - Uses config.PROXY_CONFIG  
6. **`twitter/twitter_screenshot_generator.py`** - Uses config.PROXY_CONFIG
7. **`generate_message_images.py`** - Uses config.PROXY_CONFIG

---

## 🚨 Troubleshooting

### "No proxy configured" error:
1. Check `copy.env` has all 3 proxy variables
2. Restart your script
3. Check logs for proxy usage message

### Proxy authentication fails:
1. Verify PROXY_USERNAME and PROXY_PASSWORD are correct
2. Verify PROXY_SERVER format: `http://IP:PORT`
3. Test proxy manually with curl:
   ```bash
   curl -x http://USERNAME:PASSWORD@77.47.156.7:50100 https://twitter.com
   ```

### Twitter still won't open:
1. Double-check proxy credentials
2. Ensure proxy server is reachable
3. Check if proxy service is running
4. Verify your IP is whitelisted on the proxy

---

## 📊 Impact on Cronjobs

All cronjobs will now use the proxy:
- **Facebook scraper** (every 1 hour) - Uses proxy ✅
- **Twitter poster** (every 8 min) - Uses proxy ✅  
- **Image generator** - Uses proxy for avatars ✅

No changes needed to cron scripts - they automatically pick up the proxy config from `copy.env`.

---

## ✅ Testing Checklist

Before running cronjobs:

- [ ] Verify `copy.env` has PROXY_SERVER, PROXY_USERNAME, PROXY_PASSWORD
- [ ] Test Facebook scraper manually - check for proxy usage log
- [ ] Test Twitter poster manually - should not exit with proxy error
- [ ] Check that Twitter login works with proxy
- [ ] Verify images can download avatars via proxy

---

## 🔒 Security Note

The proxy credentials are in `copy.env` which is:
- ✅ Not committed to git (.gitignore)
- ✅ Local to your server only
- ✅ Loaded at runtime only

**Never commit `copy.env` to version control!**

---

Last Updated: October 13, 2025  
Status: **FIXED - Proxy now mandatory and working across all scripts**


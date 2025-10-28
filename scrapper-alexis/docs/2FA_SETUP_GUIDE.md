# Facebook 2FA Setup Guide for Automated Scraping

## Problem
Your Facebook account has **Two-Factor Authentication (2FA)** enabled, which requires manual code entry during login. The scraper detected this at:
```
https://www.facebook.com/two_step_verification/authentication/
```

## Solutions

### Option 1: Complete 2FA Once with Non-Headless Browser (RECOMMENDED)

This allows you to complete 2FA manually once, save the session, and reuse it for automated runs.

#### Steps:

1. **Edit `.env` file** to enable visible browser:
   ```bash
   nano /var/www/scrapper-alexis/.env
   ```
   
   Change:
   ```
   HEADLESS=True
   ```
   To:
   ```
   HEADLESS=False
   ```

2. **Install X11 forwarding** (if on VPS via SSH):
   ```bash
   # On your LOCAL machine, reconnect with X11:
   ssh -X user@your-vps-ip
   
   # Or use VNC/RDP to access the VPS desktop
   ```

3. **Run the scraper**:
   ```bash
   cd /var/www/scrapper-alexis
   source venv/bin/activate
   python3 relay_agent.py
   ```

4. **Complete 2FA manually**:
   - Browser window will open
   - Enter your 2FA code when prompted
   - Wait for redirect to home.php
   - Session will be saved to `auth_facebook.json`

5. **Switch back to headless mode**:
   ```bash
   nano /var/www/scrapper-alexis/.env
   ```
   Change back to:
   ```
   HEADLESS=True
   ```

6. **Future runs will use the saved session** - no 2FA needed!

---

### Option 2: Use Existing Valid Session

If you have a valid `auth_facebook.json` from another machine:

1. Copy it to `/var/www/scrapper-alexis/auth_facebook.json`
2. Run the scraper - it will use the saved session

---

### Option 3: Temporarily Disable 2FA (NOT RECOMMENDED)

⚠️ **Security Risk** - Only do this if absolutely necessary:

1. Go to Facebook Settings > Security and Login
2. Find "Two-Factor Authentication"
3. Click "Edit" and turn it off
4. Run the scraper to complete login
5. **Re-enable 2FA immediately** after getting a valid session

---

## Current Implementation

The scraper now automatically:

✅ Detects 2FA pages  
✅ Provides clear instructions  
✅ Waits 5 minutes for manual 2FA completion (in non-headless mode)  
✅ Saves the session after successful 2FA  
✅ Reuses saved sessions to avoid repeated 2FA  

### 2FA Detection Log Output:
```
======================================================================
🔐 TWO-FACTOR AUTHENTICATION (2FA) REQUIRED
======================================================================
Facebook account has 2FA enabled.
Current URL: https://www.facebook.com/two_step_verification/...

OPTIONS:
1. Complete 2FA manually within 5 minutes
2. Disable 2FA on your Facebook account (not recommended)
3. Use a valid saved session (auth_facebook.json)

Waiting for 2FA completion...
```

---

## Automated Scraping Workflow

Once you have a valid session:

```bash
# The scraper will:
1. Check for auth_facebook.json ✅
2. Load the saved session ✅
3. Verify login (should redirect to home.php) ✅
4. Extract 100 messages ✅
5. No 2FA required! ✅
```

---

## Troubleshooting

### Session Expires
If `auth_facebook.json` becomes invalid:
- Delete the file: `rm auth_facebook.json`
- Follow Option 1 again to create a fresh session

### Can't Use X11/VNC
Use Option 3 to:
- Complete 2FA on your local machine with headless=False
- Copy the generated `auth_facebook.json` to the VPS

### Still Getting 2FA
- The session might have expired
- Facebook might require periodic re-authentication
- Solution: Re-do Option 1 to refresh the session


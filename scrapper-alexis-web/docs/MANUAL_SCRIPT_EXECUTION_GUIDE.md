# Manual Script Execution from Laravel - Problem & Solution

**Date:** October 17, 2025  
**Issue:** Laravel admin panel unable to execute Python scraper scripts on demand  
**Status:** Solution identified, awaiting implementation

---

## 📋 Problem Statement

### What's Not Working
The Laravel admin panel has buttons to manually trigger scraper scripts, but they fail silently with no feedback or execution.

### Current Implementation
Located in `/var/www/scrapper-alexis-web/app/helpers.php`:

```php
function runScraperScript(string $script): array
{
    // Maps script names to file paths
    $scriptsMap = [
        'facebook' => '/var/www/scrapper-alexis/run_facebook_flow.sh',
        'twitter' => '/var/www/scrapper-alexis/run_twitter_flow.sh',
        'images' => '/var/www/scrapper-alexis/run_image_generation.sh',
    ];
    
    // Runs script in background
    exec("bash {$scriptPath} >> /var/www/scrapper-alexis/logs/manual_run.log 2>&1 &", $output, $returnVar);
    
    return ['success' => true, 'message' => 'Script started in background'];
}
```

---

## 🔍 Root Causes

### 1. **Background Execution Hides All Errors**
- The `&` at the end runs the script in background
- Function always returns success immediately
- No way to know if script actually started
- Errors are invisible

### 2. **Web Server User Permissions**
When PHP runs `exec()`, it executes as the web server user (`www-data` or `nginx`):

**Problems:**
- ❌ No access to Python virtual environment
- ❌ Different user than scripts expect
- ❌ May lack permissions for database/logs
- ❌ Different environment variables (PATH, HOME, etc.)

### 3. **Missing Environment Context**
Scripts require:
- Python virtual environment activation (`source venv/bin/activate`)
- `xvfb-run` for headless browser execution
- Proper PATH to find all commands
- Database file write permissions
- Log directory write permissions

### 4. **Xvfb Display Server Issues**
The scripts use `xvfb-run -a` for virtual displays:
- Requires X11 system libraries
- Needs proper permissions
- Can conflict with simultaneous runs
- May fail for www-data user

---

## ✅ Recommended Solution: Sudo with Restricted Commands

### Why This Approach?
- ✅ **Simple** - One configuration file
- ✅ **Secure** - Only specific scripts allowed
- ✅ **Reliable** - Scripts run as correct user with full environment
- ✅ **Maintainable** - No complex infrastructure needed
- ✅ **Works** - Preserves all permissions and context

### How It Works
1. Configure `sudoers` to allow www-data to run specific scripts as your user
2. Update Laravel helper to use `sudo -u youruser`
3. Scripts execute with your full environment (venv, PATH, permissions)
4. Log files capture output for debugging

---

## 🛠️ Implementation Steps

### Step 1: Create Sudoers Configuration

**File:** `/etc/sudoers.d/scraper-web`

```bash
# Allow www-data (web server) to run scraper scripts as your user
# SECURITY: Only these specific scripts can be run
# IMPORTANT: Replace 'youruser' with your actual Linux username

www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
```

**Commands to create:**
```bash
# Create file (use visudo for safety)
sudo visudo -f /etc/sudoers.d/scraper-web

# Or directly (be careful with syntax!)
sudo tee /etc/sudoers.d/scraper-web > /dev/null << 'EOF'
www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
www-data ALL=(youruser) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
EOF

# Set proper permissions (CRITICAL for security)
sudo chmod 0440 /etc/sudoers.d/scraper-web

# Verify syntax
sudo visudo -c
```

### Step 2: Update Laravel Helper Function

**File:** `/var/www/scrapper-alexis-web/app/helpers.php`

**Replace the entire `runScraperScript()` function with:**

```php
/**
 * Execute a scraper script manually with proper permissions
 * Uses sudo to run as the correct user with full environment
 */
function runScraperScript(string $script): array
{
    $scriptsMap = [
        'facebook' => '/var/www/scrapper-alexis/run_facebook_flow.sh',
        'twitter' => '/var/www/scrapper-alexis/run_twitter_flow.sh',
        'images' => '/var/www/scrapper-alexis/run_image_generation.sh',
    ];

    if (!isset($scriptsMap[$script])) {
        return ['success' => false, 'message' => 'Invalid script name'];
    }

    $scriptPath = $scriptsMap[$script];

    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Script file not found: ' . $scriptPath];
    }

    // IMPORTANT: Replace 'youruser' with your actual Linux username
    // Run: whoami OR ls -l /var/www/scrapper-alexis to find it
    $user = 'youruser'; // CHANGE THIS!
    
    // Create timestamped log file for this manual run
    $timestamp = date('YmdHis');
    $logFile = "/var/www/scrapper-alexis/logs/manual_{$script}_{$timestamp}.log";
    
    // Use sudo to run script as the correct user in background
    // This preserves environment, permissions, and virtual environment
    $command = sprintf(
        'sudo -u %s /usr/bin/bash %s > %s 2>&1 &',
        escapeshellarg($user),
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    // Give script moment to start and check if log was created
    usleep(500000); // 0.5 seconds
    
    if (file_exists($logFile)) {
        return [
            'success' => true,
            'message' => ucfirst($script) . ' script started successfully. Check log: ' . basename($logFile),
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to start script. Check permissions and sudoers configuration.',
        ];
    }
}
```

### Step 3: Find Your Username

**Method 1 - Check file ownership:**
```bash
ls -l /var/www/scrapper-alexis/relay_agent.py
# Output example: -rw-r--r-- 1 USERNAME groupname ... relay_agent.py
#                              ^^^^^^^^ This is your username
```

**Method 2 - Check who owns the project:**
```bash
stat -c '%U' /var/www/scrapper-alexis
```

**Method 3 - Check who runs cron jobs:**
```bash
crontab -l
```

### Step 4: Update the Code

1. Open `/var/www/scrapper-alexis-web/app/helpers.php`
2. Find line with: `$user = 'youruser';`
3. Replace `'youruser'` with your actual username (from Step 3)
4. Save the file

---

## 🧪 Testing Procedure

### Test 1: Verify Sudo Configuration

```bash
# Test if www-data can run scripts via sudo
sudo -u www-data sudo -u youruser /var/www/scrapper-alexis/run_facebook_flow.sh --help

# Expected: No password prompt, script runs or shows help
# If it asks for password: sudoers config is wrong
# If it says "command not found": path is wrong
```

### Test 2: Test from Command Line

```bash
# Simulate what Laravel will do
sudo -u www-data sudo -u youruser /usr/bin/bash /var/www/scrapper-alexis/run_facebook_flow.sh

# Watch logs in another terminal
tail -f /var/www/scrapper-alexis/logs/*.log
```

### Test 3: Test from Web Interface

1. Login to Laravel admin panel
2. Go to Dashboard
3. Click "Ejecutar Scraper Facebook" button
4. Check for success message
5. Look for new log file: `/var/www/scrapper-alexis/logs/manual_facebook_TIMESTAMP.log`

### Test 4: Verify Script Actually Runs

```bash
# Check if new log was created in last few minutes
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -5

# Read the latest log
tail -100 /var/www/scrapper-alexis/logs/manual_facebook_*.log
```

---

## 🚨 Troubleshooting

### Problem: "sudo: no tty present and no askpass program specified"
**Solution:** Add `NOPASSWD:` to sudoers file (already in example above)

### Problem: "sudo: command not found"
**Solution:** Use full path: `/usr/bin/sudo -u youruser ...`

### Problem: Script runs but crashes immediately
**Check:**
1. Virtual environment exists: `ls /var/www/scrapper-alexis/venv`
2. Scripts are executable: `ls -l /var/www/scrapper-alexis/run_*.sh`
3. Log directory is writable: `ls -ld /var/www/scrapper-alexis/logs`

### Problem: "Permission denied" on database
**Solution:**
```bash
# Add www-data to your user's group
sudo usermod -a -G yourgroup www-data

# OR make database accessible
sudo chmod 664 /var/www/scrapper-alexis/data/scraper.db
sudo chmod 775 /var/www/scrapper-alexis/data
```

### Problem: Xvfb errors in logs
**Solution:**
```bash
# Install xvfb if missing
sudo apt-get install xvfb

# Check if www-data (via sudo) can run it
sudo -u www-data sudo -u youruser which xvfb-run
```

---

## 🔐 Security Considerations

### What's Secure ✅
- Only specific scripts can be run (whitelisted in sudoers)
- Scripts run as specific user (not root)
- No password required means no password to steal
- Laravel authentication required to access buttons

### What to Watch ⚠️
- Anyone who can access Laravel admin can trigger scripts
- Scripts have full permissions of your user account
- Background execution means no rate limiting

### Recommendations
1. **Change default admin password** in Laravel
2. **Use HTTPS** for admin panel access
3. **Restrict IP access** via nginx/firewall
4. **Monitor logs** for unusual activity
5. **Consider rate limiting** in Laravel (max 1 run per hour per script)

---

## 🔄 Alternative Solutions (Not Recommended)

### Alternative 1: Laravel Queue System
**Pros:** Full control, real-time feedback, retry logic  
**Cons:** Requires Redis/Database, Supervisor, more complexity  
**When to use:** Production apps with heavy usage

### Alternative 2: Synchronous Execution (No Background)
**Pros:** Get immediate success/failure  
**Cons:** User waits 3-5 minutes, browser may timeout  
**When to use:** Scripts that complete quickly (<30 seconds)

```php
// Example: Wait for completion (no & at end)
$command = sprintf(
    'timeout 300 sudo -u %s /usr/bin/bash %s 2>&1',
    escapeshellarg($user),
    escapeshellarg($scriptPath)
);
exec($command, $output, $returnVar);
```

### Alternative 3: API Endpoint + Cron Check
**Pros:** No sudo needed  
**Cons:** Complex, delayed execution, extra database tables  
**When to use:** When sudo is absolutely not an option

---

## 📊 Implementation Checklist

- [ ] Step 1: Identify your Linux username
- [ ] Step 2: Create `/etc/sudoers.d/scraper-web` file
- [ ] Step 3: Replace `youruser` in sudoers with actual username
- [ ] Step 4: Set sudoers file permissions to 0440
- [ ] Step 5: Verify sudoers syntax with `visudo -c`
- [ ] Step 6: Update `helpers.php` with new function
- [ ] Step 7: Replace `youruser` in PHP code with actual username
- [ ] Step 8: Test sudo from command line as www-data
- [ ] Step 9: Test from web interface
- [ ] Step 10: Verify logs are created and scripts execute
- [ ] Step 11: Monitor for errors over 24 hours

---

## 📝 Quick Reference

### Key Files
- **Sudoers config:** `/etc/sudoers.d/scraper-web`
- **Laravel helper:** `/var/www/scrapper-alexis-web/app/helpers.php`
- **Scripts:** `/var/www/scrapper-alexis/run_*.sh`
- **Logs:** `/var/www/scrapper-alexis/logs/manual_*.log`

### Key Commands
```bash
# Test sudo configuration
sudo -u www-data sudo -u youruser whoami

# Check latest manual run logs
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -3

# Monitor logs in real-time
tail -f /var/www/scrapper-alexis/logs/manual_*.log

# Verify sudoers syntax
sudo visudo -c

# Check who owns scraper files
ls -l /var/www/scrapper-alexis/relay_agent.py
```

---

## 📚 Additional Resources

- **Sudoers documentation:** `man sudoers`
- **Laravel exec() security:** https://laravel.com/docs/11.x/helpers#method-exec
- **PHP escapeshellarg():** https://www.php.net/manual/en/function.escapeshellarg.php

---

## 🎯 Summary

**Problem:** Web server user (www-data) cannot execute Python scripts that require virtual environment, xvfb, and specific permissions.

**Solution:** Use sudo with restricted commands to run scripts as the correct user while maintaining security.

**Result:** Manual script execution from Laravel admin panel works reliably with full environment context.

**Next Steps:** Implement sudoers configuration, update PHP code, test thoroughly.

---

**End of Document**



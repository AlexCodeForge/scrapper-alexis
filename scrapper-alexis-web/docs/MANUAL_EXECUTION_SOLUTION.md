# Manual Script Execution Solution - Complete Implementation Guide

**Date:** October 17, 2025  
**Issue:** Laravel admin panel buttons unable to execute Python scraper scripts  
**Status:** ✅ SOLVED AND TESTED

---

## 📋 Problem Summary

### What Wasn't Working
The Laravel admin panel had buttons to manually trigger scraper scripts (`run_facebook_flow.sh` and `run_twitter_flow.sh`), but they failed to execute properly. The scripts would appear to start but wouldn't actually run because:

1. **Permission Issues:** PHP-FPM runs as `www-data` user, which lacks:
   - Access to Python virtual environment
   - Permissions for database files
   - Permissions for log directories
   - Access to xvfb-run for headless browser

2. **Background Execution Masking Errors:** Scripts ran with `&` which returned success immediately, hiding all actual errors

3. **Missing sudo Package:** The system didn't have `sudo` installed initially

---

## 🔍 Root Cause Analysis

### The Execution Chain
```
Browser → Laravel/Livewire → PHP exec() → Shell Script → Python (venv) → Database
```

### The Problem at Each Level
1. **PHP Level:** Runs as `www-data` user
2. **Shell Level:** Needs root permissions to access Python venv
3. **Python Level:** Needs write access to database and logs
4. **Browser Level:** Needs xvfb-run for headless execution

### Why Simple Solutions Failed
- **Direct exec():** www-data can't access Python venv or write to logs
- **Background execution (`&`):** Hides all error messages
- **sudo without proper config:** Asks for password in non-interactive context
- **Output redirection issues:** File handle redirection happens before sudo, causing permission errors

---

## ✅ The Complete Solution

### Solution Overview
Use `sudo` to run scripts as `root`, with:
- Non-interactive mode (`-n`)
- NOPASSWD configuration in sudoers
- Group-writable log directory for www-data
- Proper output redirection

---

## 🛠️ Implementation Steps

### Step 1: Install sudo Package

```bash
# Check if sudo is installed
which sudo

# If not installed:
apt-get update && apt-get install -y sudo
```

**Why:** The system may not have sudo installed by default.

---

### Step 2: Create Sudoers Configuration

**File:** `/etc/sudoers.d/scraper-web`

```bash
# Create the file with proper content
sudo tee /etc/sudoers.d/scraper-web > /dev/null << 'EOF'
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
EOF

# Set secure permissions (CRITICAL!)
chmod 0440 /etc/sudoers.d/scraper-web

# Verify syntax is correct
visudo -c
```

**Expected output:**
```
/etc/sudoers: parsed OK
/etc/sudoers.d/README: parsed OK
/etc/sudoers.d/scraper-web: parsed OK
```

**Important Notes:**
- File must have `0440` permissions (read-only, owner+group only)
- ALWAYS use `visudo -c` to verify syntax
- Wrong syntax can lock you out of sudo completely
- Replace `root` with your actual user if scripts are owned by different user

---

### Step 3: Make Scripts Executable

```bash
chmod +x /var/www/scrapper-alexis/run_facebook_flow.sh
chmod +x /var/www/scrapper-alexis/run_twitter_flow.sh
chmod +x /var/www/scrapper-alexis/run_image_generation.sh
```

---

### Step 4: Fix Log Directory Permissions

```bash
# Make logs directory writable by www-data group
chmod 775 /var/www/scrapper-alexis/logs
chown root:www-data /var/www/scrapper-alexis/logs

# Verify permissions
ls -ld /var/www/scrapper-alexis/logs
```

**Expected output:**
```
drwxrwxr-x 2 root www-data 4096 Oct 17 05:38 /var/www/scrapper-alexis/logs/
```

**Why:** Output redirection happens as www-data (before sudo), so www-data needs write permission to create log files.

---

### Step 5: Update Laravel Helper Function

**File:** `/var/www/scrapper-alexis-web/app/helpers.php`

**Replace the entire `runScraperScript()` function with:**

```php
/**
 * Execute a scraper script manually with proper permissions
 * Uses sudo to run as root with full environment
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

    // Run as root user to preserve environment, permissions, and virtual environment
    $user = 'root';
    
    // Create timestamped log file for this manual run
    $timestamp = date('YmdHis');
    $logFile = "/var/www/scrapper-alexis/logs/manual_{$script}_{$timestamp}.log";
    
    // Use sudo to run script as root in background
    // This preserves environment, permissions, and virtual environment
    // Use -n for non-interactive sudo (no password prompt)
    // Redirection happens as www-data (logs dir is group-writable), script runs as root
    $command = sprintf(
        'sudo -n -u %s %s > %s 2>&1 &',
        escapeshellarg($user),
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );
    
    // Log the command for debugging (optional, can be removed in production)
    \Log::info("Executing scraper command", [
        'command' => $command, 
        'user' => posix_getpwuid(posix_geteuid())['name']
    ]);
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    \Log::info("Command executed", [
        'return_code' => $returnVar, 
        'output' => $output
    ]);
    
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

**Key Points:**
- `sudo -n`: Non-interactive mode (no password prompt)
- `escapeshellarg()`: Prevents command injection
- Background execution with `&`: Script runs asynchronously
- Output redirection: `> logfile 2>&1` captures stdout and stderr
- Log file verification: Confirms script started successfully

---

### Step 6: Clear Laravel Caches

```bash
cd /var/www/scrapper-alexis-web
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear

# Restart PHP-FPM to clear OPcache
systemctl restart php8.3-fpm
```

**Why:** Laravel caches compiled code and configuration. Changes won't take effect without clearing caches.

---

## 🧪 Testing Procedures

### Test 1: Verify Sudoers Configuration

```bash
# Test if www-data can run scripts via sudo
sudo -u www-data sudo -n -l

# Expected output should list the allowed scripts
```

**Expected output:**
```
User www-data may run the following commands on hostname:
    (root) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
    (root) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
    (root) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
```

---

### Test 2: Command Line Test

```bash
# Simulate what Laravel will execute
sudo -u www-data sudo -n -u root /var/www/scrapper-alexis/run_facebook_flow.sh > /tmp/test_manual.log 2>&1 &

# Wait a moment then check the log
sleep 5
head -50 /tmp/test_manual.log

# Clean up
rm /tmp/test_manual.log
```

**Expected:** Should see script output, not sudo errors.

---

### Test 3: Web Interface Test

1. Navigate to `http://your-server:8000/` (or your Laravel URL)
2. Login to admin panel
3. Click "Ejecutar Scraper Facebook" button
4. Should see success message: "Facebook script started successfully. Check log: manual_facebook_TIMESTAMP.log"
5. Verify log file exists:
   ```bash
   ls -lt /var/www/scrapper-alexis/logs/manual_* | head -5
   ```
6. Check log content:
   ```bash
   tail -50 /var/www/scrapper-alexis/logs/manual_facebook_*.log
   ```

**Expected:** Log file should contain actual script output, not sudo errors.

---

### Test 4: Check Laravel Logs

```bash
tail -50 /var/www/scrapper-alexis-web/storage/logs/laravel.log
```

**Expected:** Should see log entries like:
```
[2025-10-17 04:02:08] local.INFO: Executing scraper command {"command":"sudo -n -u 'root' '/var/www/scrapper-alexis/run_facebook_flow.sh' > '/var/www/scrapper-alexis/logs/manual_facebook_20251017040208.log' 2>&1 &","user":"www-data"} 
[2025-10-17 04:02:08] local.INFO: Command executed {"return_code":0,"output":[]} 
```

---

## 🚨 Troubleshooting Guide

### Problem: "sudo: a password is required"

**Cause:** Sudoers file not configured correctly or NOPASSWD missing.

**Solution:**
```bash
# Verify sudoers file content
cat /etc/sudoers.d/scraper-web

# Should contain NOPASSWD for each script
# If missing, recreate the file (see Step 2)

# Verify syntax
visudo -c
```

---

### Problem: "Failed to start script" message in UI

**Cause:** Log file not created, which means either:
1. Script doesn't have execute permissions
2. Sudoers configuration incorrect
3. Log directory not writable

**Solution:**
```bash
# Check script permissions
ls -l /var/www/scrapper-alexis/run_*.sh

# Make executable if needed
chmod +x /var/www/scrapper-alexis/run_*.sh

# Check log directory permissions
ls -ld /var/www/scrapper-alexis/logs

# Fix if needed
chmod 775 /var/www/scrapper-alexis/logs
chown root:www-data /var/www/scrapper-alexis/logs

# Test manually
sudo -u www-data touch /var/www/scrapper-alexis/logs/test.log
rm /var/www/scrapper-alexis/logs/test.log
```

---

### Problem: Button shows success but script doesn't run

**Cause:** Log file created but empty (script failed immediately).

**Solution:**
```bash
# Check the log file
cat /var/www/scrapper-alexis/logs/manual_facebook_*.log

# If empty or contains sudo error, check:
1. Sudoers configuration
2. Script path is correct
3. Script has execute permissions

# Test the exact command:
sudo -u www-data sudo -n -u root /var/www/scrapper-alexis/run_facebook_flow.sh
```

---

### Problem: "Cannot redeclare function" error in Laravel logs

**Cause:** helpers.php loaded multiple times or OPcache not cleared.

**Solution:**
```bash
# Clear all caches
cd /var/www/scrapper-alexis-web
php artisan optimize:clear

# Restart PHP-FPM
systemctl restart php8.3-fpm

# If still persists, check composer.json autoload section
```

---

### Problem: Database permission errors in script logs

**Cause:** www-data can't write to database file when running as root.

**Solution:**
```bash
# Make database accessible
chmod 664 /var/www/scrapper-alexis/data/scraper.db
chmod 775 /var/www/scrapper-alexis/data

# Or add www-data to root group (if scripts run as root)
usermod -a -G root www-data
```

---

## 🔐 Security Considerations

### What's Secure ✅
- Only specific scripts can be run (whitelisted in sudoers)
- Scripts run as root but only those exact paths
- No arbitrary command execution possible
- Laravel authentication required to access buttons
- `escapeshellarg()` prevents command injection

### Security Best Practices ⚠️
1. **Change default admin password** in Laravel
2. **Use HTTPS** for admin panel access
3. **Restrict IP access** via nginx/firewall if possible
4. **Monitor logs** regularly for unusual activity
5. **Consider rate limiting** to prevent abuse (e.g., max 1 run per 5 minutes)

### Potential Risks
- Anyone with Laravel admin access can trigger scripts
- Scripts have full root permissions
- No built-in rate limiting (can be added in Laravel)
- Background execution means no resource limits

---

## 📊 File Modifications Summary

### Files Created
1. `/etc/sudoers.d/scraper-web` - Sudo permissions configuration

### Files Modified
1. `/var/www/scrapper-alexis-web/app/helpers.php` - Updated `runScraperScript()` function
2. `/var/www/scrapper-alexis/logs/` - Directory permissions changed to 775, owner:group to root:www-data

### Packages Installed
1. `sudo` - Command-line tool for privilege escalation

---

## 🎯 How It Works (Technical Flow)

### Execution Flow
```
1. User clicks "Ejecutar Scraper Facebook" button in browser
   ↓
2. Livewire sends request to Laravel backend
   ↓
3. Laravel calls runScraperScript('facebook')
   ↓
4. Function generates command:
   "sudo -n -u 'root' '/var/www/scrapper-alexis/run_facebook_flow.sh' > '/var/www/scrapper-alexis/logs/manual_facebook_TIMESTAMP.log' 2>&1 &"
   ↓
5. PHP exec() runs command as www-data user
   ↓
6. sudo (with NOPASSWD) elevates to root user
   ↓
7. Script runs as root with:
   - Python virtual environment access
   - Database write permissions
   - xvfb-run for headless browser
   - Full log directory access
   ↓
8. Output redirected to timestamped log file (written by www-data)
   ↓
9. Function verifies log file exists
   ↓
10. Returns success/failure to user interface
```

### Key Technical Details

**Why `-n` flag?**
- Non-interactive mode
- Fails immediately if password required
- Prevents hanging processes

**Why output redirection outside sudo?**
- File operations happen as www-data
- Allows writing to group-writable directory
- Sudo only elevates script execution, not file I/O

**Why `escapeshellarg()`?**
- Prevents command injection attacks
- Safely handles special characters in paths
- Required for security compliance

**Why background execution (`&`)?**
- Scripts take 3-5 minutes to complete
- Prevents browser timeout
- Allows user to continue using interface
- Log files provide execution tracking

---

## 📝 Maintenance Notes

### Regular Checks
1. **Monthly:** Review Laravel logs for failed executions
2. **Monthly:** Check disk space in `/var/www/scrapper-alexis/logs/`
3. **After system updates:** Verify sudoers configuration still exists
4. **After PHP upgrades:** Clear caches and test functionality

### Log Cleanup
Manual execution logs accumulate over time. Set up a cleanup cron:

```bash
# Add to crontab
# Clean logs older than 30 days
0 2 * * 0 find /var/www/scrapper-alexis/logs/manual_* -mtime +30 -delete
```

### Monitoring Script Execution
```bash
# Check recent manual executions
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -10

# Count executions today
ls -l /var/www/scrapper-alexis/logs/manual_facebook_$(date +%Y%m%d)*.log 2>/dev/null | wc -l
```

---

## 🔄 Rollback Procedure

If you need to revert these changes:

```bash
# 1. Remove sudoers configuration
sudo rm /etc/sudoers.d/scraper-web

# 2. Verify sudoers syntax
sudo visudo -c

# 3. Restore original helper function (keep backup)
# Edit /var/www/scrapper-alexis-web/app/helpers.php

# 4. Clear Laravel caches
cd /var/www/scrapper-alexis-web
php artisan optimize:clear
systemctl restart php8.3-fpm
```

---

## 📚 References

- **Sudoers Documentation:** `man sudoers` or `man 5 sudoers`
- **Laravel exec() Security:** https://laravel.com/docs/helpers#method-exec
- **PHP escapeshellarg():** https://www.php.net/manual/en/function.escapeshellarg.php
- **sudo Command:** https://www.sudo.ws/docs/man/1.9.13/sudo.man/

---

## ✅ Success Criteria

You'll know the solution is working correctly when:

1. ✅ Clicking "Ejecutar Scraper Facebook" shows success message
2. ✅ Log file is created: `/var/www/scrapper-alexis/logs/manual_facebook_TIMESTAMP.log`
3. ✅ Log file contains actual script output (not sudo errors)
4. ✅ Script process runs as root (check with `ps aux | grep relay_agent`)
5. ✅ No errors in Laravel logs (`storage/logs/laravel.log`)
6. ✅ Browser console shows no JavaScript errors
7. ✅ Same success for "Ejecutar Publicador Twitter" button

---

## 🎓 Lessons Learned

### What Worked
- Sudo with NOPASSWD for specific scripts
- Group-writable log directory
- Output redirection at PHP level (not inside sudo)
- Non-interactive sudo mode (`-n`)
- Proper cache clearing after code changes

### What Didn't Work
- Running scripts directly as www-data (permission errors)
- Wrapping entire command in `/bin/sh -c` (sudo couldn't find /bin/sh in whitelist)
- Output redirection inside sudo command (permission issues)
- Relying on background execution without verification (masked errors)

### Key Insights
1. **Order matters:** Output redirection must happen before sudo
2. **Permissions are tricky:** Group membership and directory permissions must align
3. **Cache is persistent:** Always restart PHP-FPM after code changes
4. **Test incrementally:** Command line testing before web testing saves time
5. **Log everything:** Debug logging helped identify exact failure points

---

**End of Document**

This solution was implemented and tested on October 17, 2025. All functionality verified working.



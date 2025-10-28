# Deploy Manual Script Execution - New VPS Setup Guide

**Purpose:** Quick setup guide for enabling manual script execution on a fresh VPS deployment  
**Prerequisites:** Laravel app and Python scripts already deployed  
**Time Required:** ~10 minutes

---

## 🚀 Quick Setup Checklist

Use this checklist when deploying to a new VPS:

- [ ] Install sudo package
- [ ] Create sudoers configuration
- [ ] Set correct file permissions
- [ ] Make scripts executable
- [ ] Configure log directory
- [ ] Verify Laravel helper function
- [ ] Clear caches
- [ ] Test functionality

---

## 📋 Step-by-Step Deployment

### Step 1: Install Required Packages

```bash
# Check if sudo is installed
which sudo

# If not found, install it:
apt-get update
apt-get install -y sudo
```

**⚠️ Important:** Some minimal VPS installations don't include sudo by default.

---

### Step 2: Create Sudoers Configuration

**Create:** `/etc/sudoers.d/scraper-web`

```bash
# Use this command to create the file safely:
sudo tee /etc/sudoers.d/scraper-web > /dev/null << 'EOF'
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
EOF

# Set secure permissions (MANDATORY!)
chmod 0440 /etc/sudoers.d/scraper-web

# CRITICAL: Verify syntax is correct
visudo -c
```

**✅ Expected output:**
```
/etc/sudoers: parsed OK
/etc/sudoers.d/README: parsed OK
/etc/sudoers.d/scraper-web: parsed OK
```

**❌ If you see errors:** Fix syntax before proceeding. Wrong syntax can break sudo!

---

### Step 3: Set Script Permissions

```bash
# Make all scraper scripts executable
chmod +x /var/www/scrapper-alexis/run_facebook_flow.sh
chmod +x /var/www/scrapper-alexis/run_twitter_flow.sh
chmod +x /var/www/scrapper-alexis/run_image_generation.sh

# Verify permissions
ls -l /var/www/scrapper-alexis/run_*.sh
```

**✅ Expected:** All scripts should show `-rwxr-xr-x` (executable)

---

### Step 4: Configure Log Directory Permissions

```bash
# Create logs directory if it doesn't exist
mkdir -p /var/www/scrapper-alexis/logs

# Set ownership and permissions
chown root:www-data /var/www/scrapper-alexis/logs
chmod 775 /var/www/scrapper-alexis/logs

# Verify
ls -ld /var/www/scrapper-alexis/logs
```

**✅ Expected output:**
```
drwxrwxr-x 2 root www-data 4096 Oct 17 XX:XX /var/www/scrapper-alexis/logs/
```

**Why this matters:** www-data needs write permission to create log files.

---

### Step 5: Verify Helper Function Code

**Check:** `/var/www/scrapper-alexis-web/app/helpers.php`

The `runScraperScript()` function should look like this:

```php
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

    $user = 'root';
    $timestamp = date('YmdHis');
    $logFile = "/var/www/scrapper-alexis/logs/manual_{$script}_{$timestamp}.log";
    
    // KEY: sudo -n (non-interactive) with output redirection
    $command = sprintf(
        'sudo -n -u %s %s > %s 2>&1 &',
        escapeshellarg($user),
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    usleep(500000); // Wait 0.5 seconds
    
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

**⚠️ Key elements:**
- `sudo -n` (non-interactive)
- `-u root` (run as root)
- Output redirection: `> logfile 2>&1 &`
- Log file verification

**If function is different:** Update it with the code above.

---

### Step 6: Clear All Laravel Caches

```bash
cd /var/www/scrapper-alexis-web

# Clear all caches
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear

# Restart PHP-FPM (adjust version if needed)
systemctl restart php8.3-fpm
```

**Why:** Laravel caches code and config. Changes won't work without clearing.

---

### Step 7: Test Sudo Configuration

```bash
# Test if www-data can run scripts via sudo
sudo -u www-data sudo -n -l
```

**✅ Expected output:**
```
User www-data may run the following commands on hostname:
    (root) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
    (root) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
    (root) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
```

**❌ If you see "sudo: a password is required":**
- Check sudoers file syntax
- Verify NOPASSWD is present
- Ensure file permissions are 0440

---

### Step 8: Test Command Line Execution

```bash
# Test Facebook script
sudo -u www-data sudo -n -u root /var/www/scrapper-alexis/run_facebook_flow.sh > /tmp/test_manual.log 2>&1 &

# Wait a moment
sleep 5

# Check log content
head -50 /tmp/test_manual.log

# Clean up
rm /tmp/test_manual.log
```

**✅ Expected:** Should see script output (session info, browser launch, etc.)  
**❌ If you see:** "sudo: a password is required" → Go back to Step 2

---

### Step 9: Test Web Interface

1. **Navigate to:** `http://your-vps-ip:8000/`
2. **Login** to admin panel
3. **Click:** "Ejecutar Scraper Facebook" button
4. **Expected result:** Success message with log filename

**Verify execution:**
```bash
# List recent manual logs
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -5

# Check log content
tail -50 /var/www/scrapper-alexis/logs/manual_facebook_*.log
```

**✅ Success indicators:**
- Log file exists
- Log contains script output (not sudo errors)
- File owned by www-data
- File size > 0 bytes

---

## 🚨 Common Deployment Issues

### Issue 1: "sudo: command not found"

**Cause:** sudo not installed  
**Fix:**
```bash
apt-get update && apt-get install -y sudo
```

---

### Issue 2: "sudo: a password is required"

**Cause:** Sudoers configuration incorrect or missing  
**Fix:**
```bash
# Verify file exists
cat /etc/sudoers.d/scraper-web

# Should show NOPASSWD for each script
# If missing, recreate file (Step 2)

# Verify syntax
visudo -c
```

---

### Issue 3: "Failed to start script" in web UI

**Cause:** Log file not created (permission or execution issue)  
**Fix:**
```bash
# Check script is executable
ls -l /var/www/scrapper-alexis/run_*.sh

# Make executable if needed
chmod +x /var/www/scrapper-alexis/run_*.sh

# Check log directory is writable
ls -ld /var/www/scrapper-alexis/logs

# Fix permissions if needed
chmod 775 /var/www/scrapper-alexis/logs
chown root:www-data /var/www/scrapper-alexis/logs

# Test manually
sudo -u www-data touch /var/www/scrapper-alexis/logs/test.log
rm /var/www/scrapper-alexis/logs/test.log
```

---

### Issue 4: Scripts show success but don't actually run

**Cause:** Log file created but contains sudo error  
**Fix:**
```bash
# Check log content
cat /var/www/scrapper-alexis/logs/manual_facebook_*.log

# If contains "sudo: a password is required":
# → Sudoers configuration is wrong
# → Go back to Step 2 and recreate sudoers file
```

---

### Issue 5: Database permission errors in logs

**Cause:** Script runs as root but database not accessible  
**Fix:**
```bash
# Make database directory accessible
chmod 775 /var/www/scrapper-alexis/data
chmod 664 /var/www/scrapper-alexis/data/scraper.db

# Verify
ls -l /var/www/scrapper-alexis/data/scraper.db
```

---

## 🔍 Verification Commands

Use these commands to verify everything is set up correctly:

### Check Sudo Installation
```bash
which sudo
sudo --version | head -1
```

### Check Sudoers Configuration
```bash
# View file
cat /etc/sudoers.d/scraper-web

# Check permissions (should be 0440)
ls -l /etc/sudoers.d/scraper-web

# Verify syntax
visudo -c
```

### Check Script Permissions
```bash
# Should all be executable (-rwxr-xr-x)
ls -l /var/www/scrapper-alexis/run_*.sh
```

### Check Log Directory
```bash
# Should be drwxrwxr-x root:www-data
ls -ld /var/www/scrapper-alexis/logs
```

### Check PHP-FPM User
```bash
# Should show www-data
ps aux | grep php-fpm | grep -v root | head -2
```

### Test Sudo Access
```bash
# Should list the three scripts
sudo -u www-data sudo -n -l
```

### Check Recent Logs
```bash
# List manual execution logs
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -10

# View latest log
tail -100 /var/www/scrapper-alexis/logs/manual_facebook_*.log | head -50
```

---

## 📝 Post-Deployment Checklist

After completing all steps, verify:

- [ ] `sudo --version` returns version info
- [ ] `/etc/sudoers.d/scraper-web` exists with 0440 permissions
- [ ] `visudo -c` shows "parsed OK"
- [ ] All `run_*.sh` scripts are executable
- [ ] `/var/www/scrapper-alexis/logs/` is owned by `root:www-data` with 775 permissions
- [ ] `sudo -u www-data sudo -n -l` lists the three scripts
- [ ] Web interface shows success message when clicking buttons
- [ ] Log files are created in `/var/www/scrapper-alexis/logs/`
- [ ] Log files contain actual script output (not sudo errors)
- [ ] No errors in `/var/www/scrapper-alexis-web/storage/logs/laravel.log`

---

## 🔧 Quick Fix Script

Save time with this automated setup script:

```bash
#!/bin/bash
# Quick setup script for manual execution on new VPS

set -e  # Exit on error

echo "=== Setting up manual script execution ==="

# 1. Install sudo if needed
if ! command -v sudo &> /dev/null; then
    echo "Installing sudo..."
    apt-get update && apt-get install -y sudo
fi

# 2. Create sudoers configuration
echo "Creating sudoers configuration..."
tee /etc/sudoers.d/scraper-web > /dev/null << 'EOF'
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_facebook_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_twitter_flow.sh
www-data ALL=(root) NOPASSWD: /var/www/scrapper-alexis/run_image_generation.sh
EOF

chmod 0440 /etc/sudoers.d/scraper-web

# 3. Verify syntax
if ! visudo -c; then
    echo "ERROR: Sudoers syntax error!"
    exit 1
fi

# 4. Make scripts executable
echo "Setting script permissions..."
chmod +x /var/www/scrapper-alexis/run_facebook_flow.sh
chmod +x /var/www/scrapper-alexis/run_twitter_flow.sh
chmod +x /var/www/scrapper-alexis/run_image_generation.sh

# 5. Configure log directory
echo "Configuring log directory..."
mkdir -p /var/www/scrapper-alexis/logs
chown root:www-data /var/www/scrapper-alexis/logs
chmod 775 /var/www/scrapper-alexis/logs

# 6. Clear Laravel caches
echo "Clearing Laravel caches..."
cd /var/www/scrapper-alexis-web
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear

# 7. Restart PHP-FPM
echo "Restarting PHP-FPM..."
systemctl restart php8.3-fpm

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "Next steps:"
echo "1. Test: sudo -u www-data sudo -n -l"
echo "2. Visit web interface and click 'Ejecutar Scraper Facebook'"
echo "3. Check logs: ls -lt /var/www/scrapper-alexis/logs/manual_*"
```

**Usage:**
```bash
# Save script
nano /root/setup_manual_execution.sh

# Make executable
chmod +x /root/setup_manual_execution.sh

# Run it
/root/setup_manual_execution.sh
```

---

## 📞 Support Information

### Files to Check When Troubleshooting

1. **Sudoers config:** `/etc/sudoers.d/scraper-web`
2. **Laravel helper:** `/var/www/scrapper-alexis-web/app/helpers.php`
3. **Laravel logs:** `/var/www/scrapper-alexis-web/storage/logs/laravel.log`
4. **Script logs:** `/var/www/scrapper-alexis/logs/manual_*.log`
5. **PHP-FPM logs:** `/var/log/php8.3-fpm.log`

### Commands for Quick Diagnosis

```bash
# Full diagnostic check
echo "=== DIAGNOSTIC CHECK ==="
echo "1. Sudo installed:"
which sudo

echo -e "\n2. Sudoers file:"
cat /etc/sudoers.d/scraper-web

echo -e "\n3. Sudoers syntax:"
visudo -c

echo -e "\n4. Script permissions:"
ls -l /var/www/scrapper-alexis/run_*.sh

echo -e "\n5. Log directory:"
ls -ld /var/www/scrapper-alexis/logs

echo -e "\n6. www-data sudo access:"
sudo -u www-data sudo -n -l

echo -e "\n7. Recent manual logs:"
ls -lt /var/www/scrapper-alexis/logs/manual_* | head -5

echo -e "\n8. Latest Laravel log:"
tail -20 /var/www/scrapper-alexis-web/storage/logs/laravel.log
```

Save this as `/root/diagnose_manual_execution.sh` for quick troubleshooting.

---

## 🎯 Summary

This setup enables the Laravel admin panel to manually execute Python scraper scripts by:

1. **Installing sudo** - Required for privilege escalation
2. **Configuring sudoers** - Allows www-data to run specific scripts as root
3. **Setting permissions** - Makes scripts executable and logs writable
4. **Using proper command** - `sudo -n -u root script > log 2>&1 &`

**Key success factors:**
- ✅ NOPASSWD in sudoers (no password prompt)
- ✅ Non-interactive mode (`-n`)
- ✅ Group-writable log directory
- ✅ Output redirection outside sudo
- ✅ Cache clearing after changes

**Expected result:** Click button → Script runs → Success message → Log file created

---

## 📚 Additional Resources

- **Detailed solution:** `MANUAL_EXECUTION_SOLUTION.md` (same directory)
- **Sudo man page:** `man sudoers` or `man 5 sudoers`
- **Laravel helpers:** https://laravel.com/docs/helpers

---

**Last Updated:** October 17, 2025  
**Tested On:** Debian 12, PHP 8.3, Laravel 12

---

**Quick Start:**  
Copy the Quick Fix Script (above) and run it on your new VPS. Then test the web interface.



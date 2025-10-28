# Debug Output Configuration Guide

## Overview
The scraper includes a comprehensive debug output system that creates screenshots and detailed logs for troubleshooting. This feature can be controlled via an environment variable to help manage disk space usage.

## Configuration

### Environment Variable
```bash
DEBUG_OUTPUT_ENABLED=false  # Disable debug output (default)
DEBUG_OUTPUT_ENABLED=true   # Enable debug output
```

### Location
Set this variable in your `.env` file (or `copy.env` for testing):

```bash
# Debug Output Control
DEBUG_OUTPUT_ENABLED=false
```

## Behavior

### When Enabled (`DEBUG_OUTPUT_ENABLED=true`)
- ✅ Debug sessions create timestamped folders in `debug_output/`
- ✅ Screenshots are saved for each step of the scraping process
- ✅ Detailed session logs are created
- ✅ Organized by category (login, navigation, extraction, etc.)
- ⚠️  Can consume significant disk space over time (~2.4GB+ for accumulated runs)

**Example folder structure:**
```
debug_output/
├── run_20251020_120534_multi_profile_scraper/
│   ├── session.log
│   ├── login/
│   │   ├── 20251020_120534_001_login_attempt.png
│   │   └── login_20251020_120540.log
│   ├── navigation/
│   ├── extraction/
│   ├── verification/
│   ├── errors/
│   └── other/
```

### When Disabled (`DEBUG_OUTPUT_ENABLED=false`)
- ✅ No debug screenshots are saved
- ✅ No debug folders are created
- ✅ All main functionality continues to work normally
- ✅ Regular logging to `logs/` folder continues
- ✅ Saves significant disk space

## How It Works

### Code Implementation
The debug system uses a factory pattern to create either:
- **DebugSession**: Full debug output with screenshots and logs
- **NoOpDebugSession**: No-operation session that does nothing

```python
from core.debug_helper import DebugSession, is_debug_enabled

# Create a debug session (automatically returns NoOp if disabled)
session = DebugSession("my_scraper")

# Check if debug is enabled
if is_debug_enabled():
    print("Debug output is active")
```

### Files Modified
1. **core/debug_helper.py** - Core debug functionality with environment check
2. **twitter/twitter_post.py** - Conditional screenshot capture
3. **copy.env** - Configuration template
4. **README.md** - User documentation

## Usage Examples

### For Development (Debug ON)
When troubleshooting issues or developing new features:
```bash
# In .env
DEBUG_OUTPUT_ENABLED=true
```

### For Production (Debug OFF)
When running in production to save disk space:
```bash
# In .env
DEBUG_OUTPUT_ENABLED=false
```

### Temporary Override
Override the setting for a single run:
```bash
DEBUG_OUTPUT_ENABLED=true python3 relay_agent.py
```

## Impact on Different Components

### ✅ Continues Working Normally
- Main scraping functionality
- Database operations
- Message extraction
- Twitter posting
- Regular logging to `logs/` folder
- Error handling

### ⚠️ Disabled When DEBUG_OUTPUT_ENABLED=false
- Debug screenshots
- Debug session logs
- Run folders in `debug_output/`
- Category-specific logs
- Page state logging

## Disk Space Savings

Based on actual usage:
- **Before**: ~2.4GB accumulated in `debug_output/` folder
- **After** (with debug disabled): 0 bytes in `debug_output/`
- **Per run**: Varies by number of profiles and steps (~10-50MB per run)

## Recommendations

### Enable Debug Output When:
- 🔍 Troubleshooting login issues
- 🔍 Debugging scraping failures
- 🔍 Developing new features
- 🔍 Testing on new environments

### Disable Debug Output When:
- 🚀 Running in production
- 🚀 Automated scheduled runs (cron jobs)
- 🚀 Limited disk space available
- 🚀 Stable operation confirmed

## Migration Guide

### Existing Installations
If you already have accumulated debug output:

1. **Update your .env file:**
   ```bash
   echo "DEBUG_OUTPUT_ENABLED=false" >> .env
   ```

2. **Clean up existing debug folders** (optional):
   ```bash
   # Check size first
   du -sh debug_output/
   
   # Backup if needed
   tar -czf debug_output_backup.tar.gz debug_output/
   
   # Remove old debug output
   rm -rf debug_output/run_*
   ```

3. **Restart your services:**
   ```bash
   # If using systemd
   sudo systemctl restart scraper-facebook
   sudo systemctl restart scraper-twitter
   
   # Or restart cron jobs
   # (they will pick up the new setting automatically)
   ```

## Testing

To verify the setting is working correctly:

```python
#!/usr/bin/env python3
from dotenv import load_dotenv
load_dotenv()

from core.debug_helper import is_debug_enabled, DebugSession

# Check status
print(f"Debug enabled: {is_debug_enabled()}")

# Create a session
session = DebugSession("test")
print(f"Session type: {type(session).__name__}")
session.close()
```

Expected output when disabled:
```
Debug enabled: False
Session type: NoOpDebugSession
```

## Troubleshooting

### Debug Not Disabling
1. Check `.env` file has the correct setting:
   ```bash
   cat .env | grep DEBUG_OUTPUT_ENABLED
   ```

2. Verify the application is reading the correct `.env` file:
   ```bash
   # In your Python script
   import os
   print(os.getenv('DEBUG_OUTPUT_ENABLED'))
   ```

3. Restart the application after changing the setting

### Debug Folders Still Being Created
- Check for other scripts that might create debug folders
- Verify the `dotenv` package is installed: `pip install python-dotenv`
- Ensure `load_dotenv()` is called before importing debug_helper

## Related Documentation
- [README.md](../README.md) - Main documentation
- [Debug Helper Index](others/DEBUG_HELPER_INDEX.md) - Debug system overview
- [Debug Helper README](others/DEBUG_HELPER_README.md) - Detailed debug features

## Version History
- **v1.0** (2025-10-20): Initial implementation of DEBUG_OUTPUT_ENABLED setting
  - Added environment variable control
  - Created NoOpDebugSession for disabled state
  - Updated all debug functions to check enabled state
  - Updated documentation


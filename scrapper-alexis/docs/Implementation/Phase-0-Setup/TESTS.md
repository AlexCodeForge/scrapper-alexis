# Phase 0: Testing & Validation

## Test Categories

### 1. Environment Validation Tests

#### Test 1.1: Python Version Check
```bash
python --version
# Expected: Python 3.9.x or higher
```

**Pass Criteria:** Python version is 3.9 or higher

---

#### Test 1.2: Virtual Environment Activation
```bash
which python  # Linux/Mac
where python  # Windows
```

**Pass Criteria:** Path points to `venv/bin/python` or `venv\Scripts\python.exe`

---

#### Test 1.3: Dependencies Installation
```bash
pip list | grep playwright
pip list | grep python-dotenv
pip list | grep tabulate
```

**Pass Criteria:** All three packages are installed with correct versions

---

### 2. Configuration Tests

#### Test 2.1: Environment Variables Loading
```python
# test_config.py
import config

def test_config_loads():
    """Test that config module loads without errors."""
    assert config.FACEBOOK_EMAIL is not None
    assert config.X_EMAIL is not None
    assert config.HEADLESS is not None
    assert config.DEFAULT_TIMEOUT > 0
    print("✅ Config loads successfully")

if __name__ == "__main__":
    test_config_loads()
```

**Pass Criteria:** Script runs without errors

---

#### Test 2.2: Configuration Validation
```python
# test_validation.py
import config

def test_validate_config():
    """Test configuration validation."""
    try:
        config.validate_config()
        print("✅ Configuration is valid")
        return True
    except ValueError as e:
        print(f"❌ Configuration validation failed: {e}")
        return False

if __name__ == "__main__":
    test_validate_config()
```

**Pass Criteria:** Validation passes when all required vars are set

---

### 3. Exception Tests

#### Test 3.1: Custom Exceptions Import
```python
# test_exceptions.py
from exceptions import (
    RelayAgentError,
    LoginError,
    ExtractionError,
    PostingError,
    ScreenshotError,
    NavigationError,
    DatabaseError,
    ConfigurationError
)

def test_exceptions():
    """Test that all custom exceptions can be imported and raised."""
    exceptions_to_test = [
        LoginError,
        ExtractionError,
        PostingError,
        ScreenshotError,
        NavigationError,
        DatabaseError,
        ConfigurationError
    ]
    
    for exc_class in exceptions_to_test:
        try:
            raise exc_class("Test error")
        except RelayAgentError as e:
            print(f"✅ {exc_class.__name__} works correctly")
        except Exception as e:
            print(f"❌ {exc_class.__name__} failed: {e}")
            return False
    
    return True

if __name__ == "__main__":
    test_exceptions()
```

**Pass Criteria:** All exceptions can be raised and caught

---

### 4. File Structure Tests

#### Test 4.1: Required Files Exist
```bash
# test_file_structure.sh
#!/bin/bash

files=(
    ".env"
    ".env.example"
    ".gitignore"
    "requirements.txt"
    "config.py"
    "exceptions.py"
    "relay_agent.py"
    "README.md"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file is missing"
        exit 1
    fi
done

echo "✅ All required files exist"
```

**Pass Criteria:** All files exist

---

#### Test 4.2: Required Directories Exist
```bash
# test_directories.sh
#!/bin/bash

dirs=(
    "logs"
    "screenshots"
    "backups"
)

for dir in "${dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir/ exists"
    else
        echo "❌ $dir/ is missing"
        exit 1
    fi
done

echo "✅ All required directories exist"
```

**Pass Criteria:** All directories exist

---

### 5. Git Configuration Tests

#### Test 5.1: .gitignore Validation
```bash
# Check that sensitive files are ignored
git check-ignore .env
git check-ignore auth_facebook.json
git check-ignore logs/
git check-ignore screenshots/
```

**Pass Criteria:** All commands return exit code 0 (files are ignored)

---

#### Test 5.2: No Sensitive Files Committed
```bash
git status --porcelain
# Should NOT show .env or auth_*.json files
```

**Pass Criteria:** No sensitive files appear in git status

---

### 6. Playwright Installation Tests

#### Test 6.1: Playwright CLI Available
```bash
playwright --version
```

**Pass Criteria:** Returns version number (e.g., "Version 1.40.0")

---

#### Test 6.2: Chromium Browser Installed
```bash
playwright show-trace --help
# If this works, Playwright is properly installed
```

**Pass Criteria:** Help text appears

---

### 7. Main Script Tests

#### Test 7.1: Import Test
```python
# test_main_import.py
try:
    import relay_agent
    print("✅ relay_agent.py imports successfully")
except Exception as e:
    print(f"❌ relay_agent.py import failed: {e}")
```

**Pass Criteria:** No import errors

---

#### Test 7.2: Execution Test
```bash
python relay_agent.py
```

**Pass Criteria:** 
- Script runs without crashes
- Log file created in `logs/` directory
- Console output shows "Phase 0: Setup complete"

---

### 8. Logging Tests

#### Test 8.1: Log File Creation
```bash
python relay_agent.py
ls logs/relay_agent_*.log
```

**Pass Criteria:** Log file exists with today's date

---

#### Test 8.2: Log Content Validation
```bash
cat logs/relay_agent_*.log | grep "Configuration validated"
cat logs/relay_agent_*.log | grep "Relay Agent starting"
```

**Pass Criteria:** Both log messages appear in the file

---

## Manual Testing Checklist

### Pre-execution Checks
- [ ] Virtual environment is activated
- [ ] `.env` file contains actual credentials (not placeholders)
- [ ] `.env` is listed in `.gitignore`
- [ ] No sensitive files staged for git commit

### Execution Checks
- [ ] `python relay_agent.py` runs without errors
- [ ] Log file created in `logs/` directory
- [ ] Console shows INFO level messages
- [ ] No Python import errors

### Post-execution Checks
- [ ] Log file contains expected messages
- [ ] No error messages in logs
- [ ] Configuration validation passed

---

## Automated Test Suite

Create `tests/test_phase0.py`:

```python
"""Automated tests for Phase 0."""
import os
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

import config
from exceptions import LoginError, ConfigurationError


def test_environment():
    """Test Python environment."""
    assert sys.version_info >= (3, 9), "Python 3.9+ required"
    print("✅ Python version OK")


def test_config_module():
    """Test config module loads."""
    assert hasattr(config, 'FACEBOOK_EMAIL')
    assert hasattr(config, 'X_EMAIL')
    assert hasattr(config, 'validate_config')
    print("✅ Config module OK")


def test_exceptions_module():
    """Test exceptions module."""
    try:
        raise LoginError("Test")
    except LoginError:
        print("✅ Exceptions module OK")


def test_file_structure():
    """Test required files exist."""
    required_files = [
        'config.py',
        'exceptions.py',
        'relay_agent.py',
        '.gitignore',
        'requirements.txt'
    ]
    
    for filename in required_files:
        assert Path(filename).exists(), f"{filename} not found"
    
    print("✅ File structure OK")


def test_directories():
    """Test required directories exist."""
    required_dirs = ['logs', 'screenshots', 'backups']
    
    for dirname in required_dirs:
        assert Path(dirname).exists(), f"{dirname}/ not found"
    
    print("✅ Directory structure OK")


def run_all_tests():
    """Run all Phase 0 tests."""
    tests = [
        test_environment,
        test_config_module,
        test_exceptions_module,
        test_file_structure,
        test_directories
    ]
    
    print("\n" + "="*50)
    print("Running Phase 0 Tests")
    print("="*50 + "\n")
    
    passed = 0
    failed = 0
    
    for test in tests:
        try:
            test()
            passed += 1
        except AssertionError as e:
            print(f"❌ {test.__name__} failed: {e}")
            failed += 1
        except Exception as e:
            print(f"❌ {test.__name__} error: {e}")
            failed += 1
    
    print("\n" + "="*50)
    print(f"Results: {passed} passed, {failed} failed")
    print("="*50 + "\n")
    
    return failed == 0


if __name__ == "__main__":
    success = run_all_tests()
    sys.exit(0 if success else 1)
```

**Run with:**
```bash
python tests/test_phase0.py
```

---

## Phase 0 Completion Criteria

✅ All automated tests pass  
✅ Manual checklist completed  
✅ Can run `python relay_agent.py` without errors  
✅ Log files are created properly  
✅ Configuration validates successfully  
✅ No sensitive files in git staging area  

**When all criteria are met, Phase 0 is complete!** 🎉


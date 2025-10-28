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
    print("[PASS] Python version OK")


def test_config_module():
    """Test config module loads."""
    assert hasattr(config, 'FACEBOOK_EMAIL')
    assert hasattr(config, 'X_EMAIL')
    assert hasattr(config, 'validate_config')
    print("[PASS] Config module OK")


def test_exceptions_module():
    """Test exceptions module."""
    try:
        raise LoginError("Test")
    except LoginError:
        print("[PASS] Exceptions module OK")


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
    
    print("[PASS] File structure OK")


def test_directories():
    """Test required directories exist."""
    required_dirs = ['logs', 'screenshots', 'backups']
    
    for dirname in required_dirs:
        assert Path(dirname).exists(), f"{dirname}/ not found"
    
    print("[PASS] Directory structure OK")


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
            print(f"[FAIL] {test.__name__} failed: {e}")
            failed += 1
        except Exception as e:
            print(f"[ERROR] {test.__name__} error: {e}")
            failed += 1
    
    print("\n" + "="*50)
    print(f"Results: {passed} passed, {failed} failed")
    print("="*50 + "\n")
    
    return failed == 0


if __name__ == "__main__":
    success = run_all_tests()
    sys.exit(0 if success else 1)


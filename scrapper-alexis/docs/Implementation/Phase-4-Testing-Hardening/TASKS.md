# Phase 4: Testing & Hardening - Tasks

## Task 1: Set Up Test Infrastructure
**Priority:** CRITICAL  
**Estimated Time:** 30 minutes

### Steps:
1. Create test directory structure
2. Install pytest and coverage tools
3. Create pytest configuration
4. Set up test fixtures

### Implementation:
```bash
# Create directories
mkdir -p tests/unit tests/integration tests/e2e

# Update requirements.txt
echo "pytest>=7.0.0" >> requirements.txt
echo "pytest-cov>=4.0.0" >> requirements.txt
echo "pytest-mock>=3.10.0" >> requirements.txt

# Install
pip install -r requirements.txt
```

### pytest.ini:
```ini
[pytest]
testpaths = tests
python_files = test_*.py
python_classes = Test*
python_functions = test_*
addopts = 
    --verbose
    --cov=.
    --cov-report=html
    --cov-report=term-missing
    --ignore=venv
    --ignore=.venv
```

### tests/conftest.py:
```python
# tests/conftest.py
import pytest
from pathlib import Path
import shutil

@pytest.fixture
def test_db():
    """Provide clean test database."""
    db_path = Path('test_relay_agent.db')
    db_path.unlink(missing_ok=True)
    yield str(db_path)
    db_path.unlink(missing_ok=True)

@pytest.fixture
def test_screenshots_dir():
    """Provide clean screenshots directory."""
    dir_path = Path('test_screenshots')
    dir_path.mkdir(exist_ok=True)
    yield dir_path
    shutil.rmtree(dir_path, ignore_errors=True)

@pytest.fixture
def mock_config(monkeypatch):
    """Mock configuration for testing."""
    monkeypatch.setenv('FACEBOOK_EMAIL', 'test@example.com')
    monkeypatch.setenv('FACEBOOK_PASSWORD', 'testpass')
    monkeypatch.setenv('X_EMAIL', 'test@example.com')
    monkeypatch.setenv('X_PASSWORD', 'testpass')
    monkeypatch.setenv('FACEBOOK_MESSAGE_URL', 'https://facebook.com/test')
```

### Verification:
```bash
pytest --collect-only  # Should find all tests
```

---

## Task 2: Create Comprehensive Unit Tests
**Priority:** HIGH  
**Estimated Time:** 60 minutes

### Steps:
1. Create unit tests for all utilities
2. Create unit tests for database operations
3. Create unit tests for text processing
4. Achieve 80%+ coverage for utilities

### tests/unit/test_text_utils.py:
```python
import pytest
from utils.text_utils import truncate_for_x, was_truncated

class TestTextUtils:
    def test_truncate_within_limit(self):
        text = "Short message"
        result = truncate_for_x(text)
        assert result == text
        assert not was_truncated(text, result)
    
    def test_truncate_exceeds_limit(self):
        text = "a" * 300
        result = truncate_for_x(text)
        assert len(result) == 280
        assert result.endswith("...")
        assert was_truncated(text, result)
    
    def test_truncate_exactly_280(self):
        text = "a" * 280
        result = truncate_for_x(text)
        assert result == text
        assert len(result) == 280
    
    @pytest.mark.parametrize("length,expected", [
        (100, 100),
        (280, 280),
        (281, 280),
        (500, 280)
    ])
    def test_truncate_various_lengths(self, length, expected):
        text = "a" * length
        result = truncate_for_x(text)
        assert len(result) == expected
```

### tests/unit/test_database.py:
```python
import pytest
from database import MessageDatabase

class TestDatabase:
    def test_insert_and_retrieve(self, test_db):
        with MessageDatabase(test_db) as db:
            msg_id = db.insert_message(
                message_text="Test",
                message_text_original="Test Original",
                facebook_url="https://fb.com/test",
                screenshot_path="test.png",
                screenshot_size_bytes=1000
            )
            
            msg = db.get_message_by_id(msg_id)
            assert msg is not None
            assert msg['message_text'] == "Test"
    
    def test_duplicate_detection(self, test_db):
        with MessageDatabase(test_db) as db:
            url = "https://fb.com/duplicate"
            
            db.insert_message(
                message_text="Test",
                message_text_original="Test",
                facebook_url=url,
                screenshot_path="test.png",
                screenshot_size_bytes=100
            )
            
            assert db.message_exists(url, hours=24) == True
            assert db.message_exists(url, hours=0) == False
    
    def test_search_functionality(self, test_db):
        with MessageDatabase(test_db) as db:
            db.insert_message(
                message_text="Python programming",
                message_text_original="Python programming",
                facebook_url="https://fb.com/1",
                screenshot_path="test.png",
                screenshot_size_bytes=100
            )
            
            results = db.search_messages("Python")
            assert len(results) == 1
            assert "Python" in results[0]['message_text']
```

### Verification:
```bash
pytest tests/unit/ -v --cov
```

---

## Task 3: Create Integration Tests
**Priority:** HIGH  
**Estimated Time:** 45 minutes

### Steps:
1. Create integration tests for auth flows
2. Create integration tests for extraction
3. Create integration tests for posting
4. Mock external dependencies

### tests/integration/test_flows.py:
```python
import pytest
from unittest.mock import Mock, patch
from playwright.sync_api import sync_playwright

class TestAuthenticationFlows:
    @patch('facebook_auth.config')
    def test_facebook_auth_with_saved_state(self, mock_config, tmp_path):
        """Test Facebook auth loads saved state."""
        # Create mock auth file
        auth_file = tmp_path / "auth_facebook.json"
        auth_file.write_text('{"cookies": []}')
        
        from facebook_auth import check_auth_state
        
        with patch('facebook_auth.Path') as mock_path:
            mock_path.return_value = auth_file
            assert check_auth_state() == True
    
    @pytest.mark.integration
    def test_facebook_login_flow_real(self):
        """Integration test with real browser (requires credentials)."""
        # This test is marked and can be skipped in CI
        pytest.skip("Requires real credentials")

class TestExtractionFlows:
    @patch('facebook_extractor.page')
    def test_message_extraction_mock(self, mock_page):
        """Test extraction with mocked page."""
        mock_locator = Mock()
        mock_locator.text_content.return_value = "Test message content"
        mock_page.locator.return_value = mock_locator
        
        from facebook_extractor import extract_message_text
        
        # Would need to adapt function to accept page parameter
        # result = extract_message_text(mock_page)
        # assert "Test message" in result
```

### Verification:
```bash
pytest tests/integration/ -v -m "not integration"  # Skip real browser tests
pytest tests/integration/ -v  # Run all including real browser
```

---

## Task 4: Create End-to-End Tests
**Priority:** MEDIUM  
**Estimated Time:** 30 minutes

### Steps:
1. Create complete workflow test
2. Create error scenario tests
3. Create edge case tests

### tests/e2e/test_complete_workflow.py:
```python
import pytest
from pathlib import Path

@pytest.mark.e2e
@pytest.mark.slow
class TestCompleteWorkflow:
    def test_full_relay_agent_flow(self):
        """Test complete workflow from FB extraction to X posting."""
        # This would require real credentials and is expensive
        pytest.skip("E2E test - run manually")
    
    def test_workflow_with_errors(self):
        """Test workflow handles errors gracefully."""
        # Test partial failure scenarios
        pytest.skip("E2E test - run manually")
```

### Verification:
```bash
pytest tests/e2e/ -v -m e2e
```

---

## Task 5: Implement Retry Logic with Exponential Backoff
**Priority:** HIGH  
**Estimated Time:** 30 minutes

### Steps:
1. Create `utils/retry_logic.py`
2. Implement exponential backoff
3. Add jitter for distributed systems
4. Integrate into main workflow

### utils/retry_logic.py:
```python
import time
import random
import logging
from typing import Callable, Any, Optional
from functools import wraps

logger = logging.getLogger(__name__)

def exponential_backoff(attempt: int, base_delay: int = 2, max_delay: int = 60) -> float:
    """Calculate delay with exponential backoff and jitter."""
    delay = min(base_delay * (2 ** attempt), max_delay)
    jitter = random.uniform(0, delay * 0.1)
    return delay + jitter

def retry_on_exception(
    max_attempts: int = 3,
    exceptions: tuple = (Exception,),
    base_delay: int = 2,
    max_delay: int = 60
):
    """Decorator for retrying function on exception."""
    def decorator(func: Callable) -> Callable:
        @wraps(func)
        def wrapper(*args, **kwargs) -> Any:
            for attempt in range(max_attempts):
                try:
                    return func(*args, **kwargs)
                except exceptions as e:
                    if attempt < max_attempts - 1:
                        delay = exponential_backoff(attempt, base_delay, max_delay)
                        logger.warning(
                            f"Attempt {attempt + 1}/{max_attempts} failed: {e}. "
                            f"Retrying in {delay:.1f}s..."
                        )
                        time.sleep(delay)
                    else:
                        logger.error(f"All {max_attempts} attempts failed: {e}")
                        raise
            return None
        return wrapper
    return decorator

# Usage example:
# @retry_on_exception(max_attempts=3, exceptions=(NavigationError,))
# def navigate_to_page(page, url):
#     page.goto(url)
```

### Integration:
Update critical functions to use retry decorator:
```python
from utils.retry_logic import retry_on_exception

@retry_on_exception(max_attempts=3, exceptions=(NavigationError,))
def navigate_to_message(page, url):
    # existing implementation
    pass
```

### Verification:
```python
# Test retry logic
def test_retry_logic():
    attempts = []
    
    @retry_on_exception(max_attempts=3)
    def failing_func():
        attempts.append(1)
        if len(attempts) < 3:
            raise ValueError("Test error")
        return "success"
    
    result = failing_func()
    assert result == "success"
    assert len(attempts) == 3
```

---

## Task 6: Implement Monitoring & Metrics
**Priority:** MEDIUM  
**Estimated Time:** 30 minutes

### Steps:
1. Create `utils/monitoring.py`
2. Implement metrics collection
3. Implement performance tracking
4. Add alerting conditions

### utils/monitoring.py:
```python
import json
import time
from pathlib import Path
from datetime import datetime
from typing import Dict, Optional
import logging

logger = logging.getLogger(__name__)

class ExecutionMetrics:
    def __init__(self):
        self.metrics = {}
        self.start_time = None
    
    def start(self, phase: str):
        """Start timing a phase."""
        self.start_time = time.time()
        logger.info(f"Started phase: {phase}")
    
    def end(self, phase: str, status: str = 'success', error: Optional[str] = None):
        """End timing a phase and record metrics."""
        if not self.start_time:
            return
        
        duration = time.time() - self.start_time
        
        self.metrics[phase] = {
            'duration': duration,
            'status': status,
            'error': error,
            'timestamp': datetime.now().isoformat()
        }
        
        logger.info(f"Completed phase: {phase} ({duration:.2f}s) - {status}")
        self.start_time = None
    
    def save(self, filepath: str = 'logs/execution_metrics.jsonl'):
        """Save metrics to file."""
        Path(filepath).parent.mkdir(exist_ok=True)
        
        with open(filepath, 'a') as f:
            f.write(json.dumps(self.metrics) + '\n')
    
    def check_alerts(self) -> list:
        """Check for alert conditions."""
        alerts = []
        
        # Alert if any phase took > 60 seconds
        for phase, data in self.metrics.items():
            if data['duration'] > 60:
                alerts.append(f"Phase {phase} took {data['duration']:.1f}s (> 60s)")
        
        # Alert if any phase failed
        for phase, data in self.metrics.items():
            if data['status'] == 'failed':
                alerts.append(f"Phase {phase} failed: {data['error']}")
        
        return alerts

# Usage in relay_agent.py:
# metrics = ExecutionMetrics()
# metrics.start('facebook_auth')
# ... do work ...
# metrics.end('facebook_auth', 'success')
# alerts = metrics.check_alerts()
# if alerts:
#     for alert in alerts:
#         logger.warning(f"ALERT: {alert}")
```

### Verification:
Test metrics collection in main workflow.

---

## Task 7: Harden Error Handling
**Priority:** CRITICAL  
**Estimated Time:** 45 minutes

### Steps:
1. Review all exception handling
2. Add specific error messages
3. Implement graceful degradation
4. Add error recovery strategies

### Enhanced Error Handling Pattern:
```python
# In relay_agent.py
def main():
    metrics = ExecutionMetrics()
    db = MessageDatabase()
    partial_data = {}
    
    try:
        # Phase 1
        metrics.start('facebook_extraction')
        try:
            # ... Facebook extraction ...
            partial_data['message_text'] = message_text
            metrics.end('facebook_extraction', 'success')
        except (LoginError, ExtractionError) as e:
            metrics.end('facebook_extraction', 'failed', str(e))
            logger.error(f"Facebook extraction failed: {e}")
            # Store partial result
            if 'message_text' in partial_data:
                db.insert_message(
                    message_text=partial_data['message_text'],
                    message_text_original=partial_data['message_text'],
                    facebook_url=config.FACEBOOK_MESSAGE_URL,
                    screenshot_path='',
                    screenshot_size_bytes=0,
                    execution_status='partial_failure',
                    execution_error=str(e)
                )
            raise
        
        # Similar pattern for other phases...
        
    except Exception as e:
        logger.error(f"Critical failure: {e}", exc_info=True)
        metrics.save()
        raise
    finally:
        db.close()
        metrics.save()
```

---

## Task 8: Create Production Checklist
**Priority:** HIGH  
**Estimated Time:** 20 minutes

### Create docs/PRODUCTION_CHECKLIST.md:
```markdown
# Production Deployment Checklist

## Pre-Deployment
- [ ] All tests passing (unit, integration, e2e)
- [ ] Code coverage > 80%
- [ ] No credentials in code
- [ ] .env file properly configured
- [ ] .gitignore includes all sensitive files
- [ ] Error handling reviewed
- [ ] Logging configured for production

## Deployment
- [ ] Virtual environment set up
- [ ] Dependencies installed
- [ ] Playwright browsers installed
- [ ] Database initialized
- [ ] Directories created (logs/, screenshots/, backups/)
- [ ] File permissions set correctly
- [ ] Cron job or scheduler configured

## Post-Deployment
- [ ] Test run completed successfully
- [ ] Logs reviewing properly
- [ ] Screenshots capturing correctly
- [ ] Database storing records
- [ ] Backup system working
- [ ] Monitoring alerts configured

## Maintenance
- [ ] Regular backup verification
- [ ] Log rotation configured
- [ ] Old screenshot cleanup scheduled
- [ ] Database maintenance planned
- [ ] Selector update process defined
```

---

## ✅ Phase 4 Completion Checklist

### Testing
- [ ] Test infrastructure set up (pytest, coverage)
- [ ] Unit tests created (80%+ coverage)
- [ ] Integration tests created
- [ ] End-to-end tests created
- [ ] All tests passing
- [ ] Coverage report generated

### Hardening
- [ ] Retry logic implemented
- [ ] Exponential backoff added
- [ ] Error handling enhanced
- [ ] Monitoring metrics added
- [ ] Alert conditions defined
- [ ] Graceful degradation implemented

### Documentation
- [ ] Production checklist created
- [ ] Troubleshooting guide written
- [ ] Error codes documented
- [ ] All functions have docstrings

### Verification
- [ ] Run full test suite: `pytest -v --cov`
- [ ] Verify coverage: `pytest --cov-report=html`
- [ ] Manual testing completed
- [ ] Production checklist validated

---

## 🚀 Next Steps
Once Phase 4 is complete:
1. Review all documentation
2. Perform final end-to-end test
3. Deploy to production
4. Monitor first few executions
5. Iterate based on feedback


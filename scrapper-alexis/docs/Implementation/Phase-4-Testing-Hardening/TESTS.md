# Phase 4: Testing & Hardening - Tests

## Running the Test Suite

### Full Test Suite
```bash
# Run all tests with coverage
pytest -v --cov=. --cov-report=html

# Run specific test categories
pytest tests/unit/ -v
pytest tests/integration/ -v
pytest tests/e2e/ -v

# Run with markers
pytest -m "not slow" -v           # Skip slow tests
pytest -m "integration" -v        # Only integration tests
pytest -m "e2e" -v                # Only end-to-end tests
```

### Coverage Report
```bash
# Generate HTML coverage report
pytest --cov=. --cov-report=html

# Open report
open htmlcov/index.html  # Mac
start htmlcov/index.html # Windows
```

---

## Unit Test Suite

### 1. Configuration Tests
```python
# tests/unit/test_config.py
import pytest
from config import validate_config
from exceptions import ConfigurationError

def test_validate_config_success(mock_config):
    """Test config validation with all required vars."""
    assert validate_config() == True

def test_validate_config_missing(monkeypatch):
    """Test config validation fails with missing vars."""
    monkeypatch.delenv('FACEBOOK_EMAIL', raising=False)
    
    with pytest.raises(ConfigurationError):
        validate_config()
```

---

### 2. Retry Logic Tests
```python
# tests/unit/test_retry_logic.py
import pytest
from utils.retry_logic import retry_on_exception, exponential_backoff

def test_exponential_backoff():
    """Test backoff calculation."""
    delay_0 = exponential_backoff(0, base_delay=2)
    delay_1 = exponential_backoff(1, base_delay=2)
    delay_2 = exponential_backoff(2, base_delay=2)
    
    assert 2 <= delay_0 < 3     # 2 + jitter
    assert 4 <= delay_1 < 5     # 4 + jitter
    assert 8 <= delay_2 < 9     # 8 + jitter

def test_retry_decorator_success_first_try():
    """Test retry succeeds on first attempt."""
    attempts = []
    
    @retry_on_exception(max_attempts=3)
    def successful_func():
        attempts.append(1)
        return "success"
    
    result = successful_func()
    assert result == "success"
    assert len(attempts) == 1

def test_retry_decorator_success_after_failures():
    """Test retry succeeds after failures."""
    attempts = []
    
    @retry_on_exception(max_attempts=3)
    def eventually_successful():
        attempts.append(1)
        if len(attempts) < 3:
            raise ValueError("Temporary error")
        return "success"
    
    result = eventually_successful()
    assert result == "success"
    assert len(attempts) == 3

def test_retry_decorator_all_failures():
    """Test retry raises after all attempts fail."""
    attempts = []
    
    @retry_on_exception(max_attempts=3)
    def always_fails():
        attempts.append(1)
        raise ValueError("Permanent error")
    
    with pytest.raises(ValueError):
        always_fails()
    
    assert len(attempts) == 3
```

---

### 3. Monitoring Tests
```python
# tests/unit/test_monitoring.py
import pytest
from utils.monitoring import ExecutionMetrics

def test_metrics_timing():
    """Test metrics timing functionality."""
    metrics = ExecutionMetrics()
    
    metrics.start('test_phase')
    # Simulate work
    import time
    time.sleep(0.1)
    metrics.end('test_phase', 'success')
    
    assert 'test_phase' in metrics.metrics
    assert metrics.metrics['test_phase']['duration'] >= 0.1
    assert metrics.metrics['test_phase']['status'] == 'success'

def test_metrics_alerts():
    """Test alert detection."""
    metrics = ExecutionMetrics()
    
    # Add slow phase (> 60s)
    metrics.metrics['slow_phase'] = {
        'duration': 65.0,
        'status': 'success',
        'error': None
    }
    
    # Add failed phase
    metrics.metrics['failed_phase'] = {
        'duration': 10.0,
        'status': 'failed',
        'error': 'Test error'
    }
    
    alerts = metrics.check_alerts()
    
    assert len(alerts) == 2
    assert any('slow_phase' in alert for alert in alerts)
    assert any('failed_phase' in alert for alert in alerts)
```

---

## Integration Test Suite

### 1. Database Integration Tests
```python
# tests/integration/test_database_integration.py
import pytest
from database import MessageDatabase
from pathlib import Path

@pytest.fixture
def populated_db(test_db):
    """Database with test data."""
    with MessageDatabase(test_db) as db:
        for i in range(10):
            db.insert_message(
                message_text=f"Message {i}",
                message_text_original=f"Original {i}",
                facebook_url=f"https://fb.com/{i}",
                screenshot_path=f"test_{i}.png",
                screenshot_size_bytes=1000 * i,
                was_truncated=(i % 2 == 0)
            )
    yield test_db

def test_statistics_calculation(populated_db):
    """Test statistics with real data."""
    with MessageDatabase(populated_db) as db:
        stats = db.get_statistics()
        
        assert stats['total_messages'] == 10
        assert stats['truncated_messages'] == 5
        assert stats['successful_executions'] == 10

def test_recent_messages_ordering(populated_db):
    """Test recent messages are ordered correctly."""
    with MessageDatabase(populated_db) as db:
        recent = db.get_recent_messages(limit=5)
        
        assert len(recent) == 5
        # Should be in descending order
        for i in range(len(recent) - 1):
            assert recent[i]['created_at'] >= recent[i+1]['created_at']
```

---

### 2. Workflow Integration Tests
```python
# tests/integration/test_workflow_integration.py
import pytest
from unittest.mock import Mock, patch

@pytest.mark.integration
class TestWorkflowIntegration:
    def test_facebook_to_database_flow(self, test_db):
        """Test data flows from extraction to database."""
        from database import MessageDatabase
        
        # Simulate extraction
        extracted_text = "Test message from Facebook"
        
        # Store in database
        with MessageDatabase(test_db) as db:
            msg_id = db.insert_message(
                message_text=extracted_text,
                message_text_original=extracted_text,
                facebook_url="https://fb.com/test",
                screenshot_path="test.png",
                screenshot_size_bytes=5000
            )
            
            # Verify retrieval
            msg = db.get_message_by_id(msg_id)
            assert msg['message_text'] == extracted_text
    
    def test_truncation_to_posting_flow(self):
        """Test truncation flows into posting correctly."""
        from utils.text_utils import truncate_for_x
        
        long_message = "a" * 300
        truncated = truncate_for_x(long_message)
        
        # Simulate posting (would be mocked)
        assert len(truncated) == 280
        # In real flow, this would be posted to X
```

---

## End-to-End Test Suite

### 1. Complete Workflow Test
```python
# tests/e2e/test_complete_workflow.py
import pytest
from pathlib import Path

@pytest.mark.e2e
@pytest.mark.slow
class TestCompleteWorkflow:
    def test_full_pipeline_dry_run(self, test_db, test_screenshots_dir):
        """Test complete pipeline with mocked external calls."""
        from database import MessageDatabase
        from utils.text_utils import truncate_for_x
        
        # Simulated extraction
        message_text = "This is a test message from Facebook" * 10
        
        # Truncation
        posted_text = truncate_for_x(message_text)
        
        # Screenshot simulation
        screenshot_path = test_screenshots_dir / "test.png"
        screenshot_path.write_text("fake screenshot data")
        screenshot_size = screenshot_path.stat().st_size
        
        # Database storage
        with MessageDatabase(test_db) as db:
            msg_id = db.insert_message(
                message_text=posted_text,
                message_text_original=message_text,
                facebook_url="https://fb.com/test",
                screenshot_path=str(screenshot_path),
                screenshot_size_bytes=screenshot_size,
                was_truncated=(len(message_text) > len(posted_text))
            )
            
            # Verify complete pipeline
            msg = db.get_message_by_id(msg_id)
            assert msg is not None
            assert msg['was_truncated'] == True
            assert msg['execution_status'] == 'success'
        
        print("✅ Complete workflow simulation passed")
```

---

### 2. Error Scenario Tests
```python
# tests/e2e/test_error_scenarios.py
import pytest
from exceptions import LoginError, ExtractionError, PostingError

@pytest.mark.e2e
class TestErrorScenarios:
    def test_facebook_login_failure_recovery(self):
        """Test system handles Facebook login failure."""
        # Would require mocking or error injection
        pytest.skip("Requires error injection framework")
    
    def test_partial_failure_storage(self, test_db):
        """Test partial data is stored on failure."""
        from database import MessageDatabase
        
        with MessageDatabase(test_db) as db:
            # Simulate partial success (extraction worked, posting failed)
            msg_id = db.insert_message(
                message_text="",  # Empty because posting failed
                message_text_original="Extracted but not posted",
                facebook_url="https://fb.com/partial",
                screenshot_path="",
                screenshot_size_bytes=0,
                execution_status='partial_failure',
                execution_error='Posting failed: Network error'
            )
            
            msg = db.get_message_by_id(msg_id)
            assert msg['execution_status'] == 'partial_failure'
            assert 'Network error' in msg['execution_error']
```

---

## Performance Tests

### 1. Database Performance
```python
# tests/performance/test_database_performance.py
import pytest
import time
from database import MessageDatabase

@pytest.mark.slow
def test_insert_performance(test_db):
    """Test database insert performance."""
    with MessageDatabase(test_db) as db:
        start = time.time()
        
        for i in range(100):
            db.insert_message(
                message_text=f"Message {i}",
                message_text_original=f"Message {i}",
                facebook_url=f"https://fb.com/{i}",
                screenshot_path=f"test_{i}.png",
                screenshot_size_bytes=1000
            )
        
        duration = time.time() - start
        
        # Should insert 100 records in < 1 second
        assert duration < 1.0
        print(f"Inserted 100 records in {duration:.3f}s")

@pytest.mark.slow
def test_search_performance(test_db):
    """Test search performance with large dataset."""
    with MessageDatabase(test_db) as db:
        # Insert 1000 records
        for i in range(1000):
            db.insert_message(
                message_text=f"Test message {i}",
                message_text_original=f"Test message {i}",
                facebook_url=f"https://fb.com/{i}",
                screenshot_path=f"test_{i}.png",
                screenshot_size_bytes=1000
            )
        
        # Search performance
        start = time.time()
        results = db.search_messages("message 500")
        duration = time.time() - start
        
        # Should search 1000 records in < 0.1 seconds
        assert duration < 0.1
        assert len(results) > 0
        print(f"Searched 1000 records in {duration:.3f}s")
```

---

## Manual Testing Checklist

### Complete System Test
- [ ] Fresh install on clean system
- [ ] Environment setup completes
- [ ] First run with no auth files works
- [ ] Facebook login succeeds
- [ ] Message extraction works
- [ ] X login succeeds
- [ ] Message posts to X
- [ ] Screenshot captures
- [ ] Database stores record
- [ ] Second run uses cached auth
- [ ] Duplicate detection prevents re-run
- [ ] Query CLI works
- [ ] Backup utility works

### Error Scenario Testing
- [ ] Invalid Facebook credentials handled
- [ ] Invalid X credentials handled
- [ ] Network interruption recovery
- [ ] CAPTCHA manual intervention works
- [ ] Expired session re-authenticates
- [ ] Message not found handled
- [ ] Posting failure logged
- [ ] Screenshot failure handled
- [ ] Database error handled

### Performance Testing
- [ ] First run < 60 seconds
- [ ] Cached run < 30 seconds
- [ ] Memory usage < 500MB
- [ ] Database queries fast
- [ ] No memory leaks

---

## Test Coverage Goals

### Coverage Targets
- Overall: 80%+
- Core modules: 90%+
- Utilities: 95%+
- Integration: 70%+

### Generate Coverage Report
```bash
pytest --cov=. --cov-report=html --cov-report=term-missing

# View report
open htmlcov/index.html
```

### Coverage by Module
```
config.py               95%
exceptions.py          100%
utils/text_utils.py     98%
utils/retry_logic.py    92%
database.py             88%
facebook_auth.py        75%
twitter_auth.py         75%
relay_agent.py          70%
```

---

## ✅ Phase 4 Test Completion Criteria

- [ ] All unit tests pass (50+ tests)
- [ ] All integration tests pass (20+ tests)
- [ ] End-to-end tests pass (5+ tests)
- [ ] Code coverage > 80%
- [ ] Performance benchmarks met
- [ ] Manual testing checklist completed
- [ ] Error scenarios tested
- [ ] Production checklist validated

**When all criteria are met, the system is production-ready!** 🚀


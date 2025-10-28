# Phase 0: Setup Tasks

## Task 1: Initialize Project Structure
**Priority:** CRITICAL  
**Estimated Time:** 10 minutes

### Steps:
1. Create project directory: `mkdir relay_agent && cd relay_agent`
2. Initialize git repository: `git init`
3. Create virtual environment: `python -m venv venv`
4. Activate virtual environment:
   - Windows: `venv\Scripts\activate`
   - Linux/Mac: `source venv/bin/activate`

### Verification:
```bash
which python  # Should point to venv/bin/python
python --version  # Should be 3.9+
```

---

## Task 2: Install Dependencies
**Priority:** CRITICAL  
**Estimated Time:** 10 minutes

### Steps:
1. Create `requirements.txt` (see FILE_TEMPLATES.md)
2. Install dependencies: `pip install -r requirements.txt`
3. Install Playwright browsers: `playwright install chromium`

### Verification:
```bash
pip list  # Should show playwright, python-dotenv, tabulate
playwright --version  # Should show version info
```

### Troubleshooting:
- If Playwright install fails, ensure you have sufficient disk space (~500MB)
- On Linux, may need system dependencies: `playwright install-deps chromium`

---

## Task 3: Create Configuration Files
**Priority:** HIGH  
**Estimated Time:** 10 minutes

### Steps:
1. Create `.env.example` (see FILE_TEMPLATES.md)
2. Copy to `.env`: `cp .env.example .env`
3. Update `.env` with your actual credentials
4. Create `.gitignore` (see FILE_TEMPLATES.md)
5. Create `config.py` (see FILE_TEMPLATES.md)

### Verification:
```bash
cat .env  # Should contain your credentials (don't commit!)
cat .gitignore  # Should include .env, auth_*.json, logs/, screenshots/
```

### Security Note:
⚠️ Never commit `.env` file to version control!

---

## Task 4: Create Custom Exception Classes
**Priority:** MEDIUM  
**Estimated Time:** 5 minutes

### Steps:
1. Create `exceptions.py` (see FILE_TEMPLATES.md)
2. Define all custom exception classes:
   - `RelayAgentError` (base)
   - `LoginError`
   - `ExtractionError`
   - `PostingError`
   - `ScreenshotError`
   - `NavigationError`

### Verification:
```python
from exceptions import LoginError
raise LoginError("Test")  # Should work
```

---

## Task 5: Create Placeholder Main Script
**Priority:** MEDIUM  
**Estimated Time:** 5 minutes

### Steps:
1. Create `relay_agent.py` with basic structure (see FILE_TEMPLATES.md)
2. Add logging configuration
3. Add main() function placeholder
4. Test import: `python -c "import relay_agent"`

### Verification:
```bash
python relay_agent.py  # Should run without errors and log startup message
```

---

## Task 6: Create Directory Structure
**Priority:** MEDIUM  
**Estimated Time:** 2 minutes

### Steps:
```bash
mkdir -p logs
mkdir -p screenshots
mkdir -p backups
```

### Verification:
```bash
ls -la  # Should show logs/, screenshots/, backups/ directories
```

---

## Task 7: Initialize README
**Priority:** LOW  
**Estimated Time:** 5 minutes

### Steps:
1. Create `README.md` with project description
2. Add setup instructions
3. Add usage instructions (placeholder)
4. Add security warnings

### Verification:
- README contains clear setup instructions
- README warns about credential security

---

## ✅ Phase Completion Checklist

- [ ] Virtual environment created and activated
- [ ] All dependencies installed (`pip list` shows playwright, python-dotenv, tabulate)
- [ ] Playwright Chromium installed (`playwright --version` works)
- [ ] `.env` file created with credentials (NOT committed to git)
- [ ] `.gitignore` properly configured
- [ ] `config.py` created and loads environment variables
- [ ] `exceptions.py` created with all custom exceptions
- [ ] `relay_agent.py` placeholder created
- [ ] Directory structure created (logs/, screenshots/, backups/)
- [ ] Can run `python relay_agent.py` without import errors
- [ ] Git repository initialized
- [ ] README.md created

---

## 🚀 Next Steps
Once Phase 0 is complete, proceed to **Phase 1: Facebook Content Acquisition**


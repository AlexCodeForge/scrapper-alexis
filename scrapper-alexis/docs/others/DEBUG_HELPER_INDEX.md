# Debug Helper - Documentation Index

## 🎯 Quick Answer

**Q: Where do screenshots/images go?**  
**A: ✅ In the respective run folder!**

Each `DebugSession` creates a timestamped run folder, and ALL screenshots for that session go into that folder, organized by category.

---

## 📚 Documentation Guide

### 🚀 Getting Started

1. **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** ⭐ START HERE
   - Quick lookup card
   - Basic usage examples
   - Where screenshots go (answered clearly)
   - FAQ section

### 📖 Detailed Documentation

2. **[DEBUG_HELPER_README.md](DEBUG_HELPER_README.md)**
   - Complete usage guide
   - All functions documented
   - API reference
   - Migration guide

3. **[SCREENSHOT_ORGANIZATION.md](SCREENSHOT_ORGANIZATION.md)**
   - Detailed structure explanation
   - Real-world examples
   - How it works internally
   - Visual folder structure

### 📋 Reference

4. **[IMPROVEMENTS_SUMMARY.md](IMPROVEMENTS_SUMMARY.md)**
   - What changed and why
   - Before/After comparison
   - New features list
   - Benefits overview

5. **[FILES_UPDATED.md](FILES_UPDATED.md)**
   - Complete change list
   - Test results
   - Verification proof
   - File structure

---

## 🧪 Test & Demo Files

### Run These to See It in Action

```bash
# Simple test (no Playwright needed)
python3 test_debug_helper.py

# Visual demonstration
python3 demo_screenshot_organization.py

# Check created folders
ls -la debug_output/run_*/
```

**Test Files:**
- `test_debug_helper.py` - Simple functionality test
- `demo_screenshot_organization.py` - Visual demo
- `debug_helper_example.py` - Full examples (requires Playwright)

---

## 📁 Folder Structure

```
debug_output/                              ← Base folder
└── run_TIMESTAMP_SESSION_NAME/            ← Your run folder
    ├── session.log                        ← Complete session log
    ├── login/                             ← Login screenshots HERE
    ├── navigation/                        ← Navigation screenshots HERE
    ├── extraction/                        ← Extraction screenshots HERE
    ├── verification/                      ← Verification screenshots HERE
    ├── errors/                            ← Error screenshots HERE
    └── other/                             ← Other screenshots HERE
```

**✅ All images go in the respective run folder!**

---

## 🔧 Quick Usage

```python
from debug_helper import DebugSession, take_debug_screenshot

# 1. Create session → Creates run folder
session = DebugSession("my_task")

try:
    # 2. Take screenshots → Saved in run folder
    take_debug_screenshot(page, "step1", category="login")
    # → Saves to: debug_output/run_XXX/login/TIMESTAMP_step1.png
    
finally:
    # 3. Always close session
    session.close()
```

---

## 📊 Categories

| Category | Use For |
|----------|---------|
| `login` | Authentication, login forms |
| `navigation` | Page navigation, URL changes |
| `extraction` | Data scraping, parsing |
| `verification` | Validation, checks |
| `errors` | Error conditions, failures |
| `other` | Miscellaneous |

---

## ✅ Verification

### Code Proof
```python
# From debug_helper.py:
base_dir = _current_run_dir        # ← Uses run folder
category_dir = base_dir / category  # ← Category within run
filepath = category_dir / filename  # ← Final path in run folder
```

### Test Proof
```bash
$ ls debug_output/run_20251010_050555_demo/
drwxr-xr-x 2 root root 4096 Oct 10 05:05 errors       ✅
drwxr-xr-x 2 root root 4096 Oct 10 05:05 extraction   ✅
drwxr-xr-x 2 root root 4096 Oct 10 05:05 login        ✅
drwxr-xr-x 2 root root 4096 Oct 10 05:05 navigation   ✅
drwxr-xr-x 2 root root 4096 Oct 10 05:05 other        ✅
drwxr-xr-x 2 root root 4096 Oct 10 05:05 verification ✅
-rw-r--r-- 1 root root  810 Oct 10 05:05 session.log  ✅
```

**All ready for screenshots in the run folder!** ✅

---

## 🆘 Need Help?

### Want to know where screenshots go?
→ Read **[SCREENSHOT_ORGANIZATION.md](SCREENSHOT_ORGANIZATION.md)**

### Want quick examples?
→ Read **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)**

### Want complete documentation?
→ Read **[DEBUG_HELPER_README.md](DEBUG_HELPER_README.md)**

### Want to see what changed?
→ Read **[IMPROVEMENTS_SUMMARY.md](IMPROVEMENTS_SUMMARY.md)**

### Want to verify it works?
→ Run `python3 test_debug_helper.py`

---

## 🎯 Key Points to Remember

1. **One Session = One Run Folder** ✅
2. **All Screenshots Go There** ✅
3. **Organized by Category** ✅
4. **No Mixing Between Runs** ✅
5. **Easy to Find** ✅

---

## 📞 Summary

### The Answer: Where do images go?

**✅ In the respective run folder!**

```
When you:     create DebugSession("my_task")
Creates:      debug_output/run_20251010_120000_my_task/

When you:     take_debug_screenshot(page, "step", category="login")
Saves to:     debug_output/run_20251010_120000_my_task/login/TIMESTAMP_step.png
              ↑                                         ↑
              Your run folder                           Your category
```

**Each run is completely isolated with all its screenshots organized by category!**

---

**🚀 Ready to use! Check [QUICK_REFERENCE.md](QUICK_REFERENCE.md) to get started.**


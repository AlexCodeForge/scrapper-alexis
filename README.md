# Alexis Scrapper - Docker Edition

**Version:** 2.0  
**Updated:** October 2025

A complete Facebook/Twitter scraper system with web management interface. Fully containerized with Docker.

---

## 📦 What's This?

This is the **source/development version** used to:
1. Develop and test the scraper
2. Build clean Docker images
3. Create portable distributions for deployment

**For deployment on production VPS**, use the `alexis-scrapper-portable` version instead.

---

## 🏗️ Project Structure

```
alexis-scrapper-docker/
├── README.md                      # This file
├── docker-compose.yml             # Container orchestration
├── env.docker.template            # Environment template
│
├── scrapper-alexis/               # Python scraper service
│   ├── Dockerfile
│   ├── generate_message_images.py
│   ├── relay_agent.py
│   └── ...
│
├── scrapper-alexis-web/           # Laravel web interface
│   ├── Dockerfile
│   ├── app/
│   └── ...
│
├── rebuild-clean-images.sh        # Build portable images ⭐
├── verify-clean-build.sh          # Verify image cleanliness
├── docker-clean-install.sh        # Fresh local install
│
└── docs/                          # Documentation
    ├── HARDCODED_DATA_FIX.md     # Security fix details
    ├── MANUAL_TEST_INSTRUCTIONS.md
    └── images/
```

---

## 🚀 HOW IT WORKS - Step by Step

### **DEVELOPMENT (This Folder)**

```
┌─────────────────────────────────────────────────────────┐
│  1. You develop and test here                           │
│     - Make code changes                                 │
│     - Test with docker-compose                          │
│     - Configure with your credentials                   │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│  2. When ready to distribute:                           │
│     Run: ./rebuild-clean-images.sh                      │
│                                                          │
│     This script:                                        │
│     • Removes YOUR credentials                          │
│     • Removes YOUR auth sessions                        │
│     • Removes YOUR database                             │
│     • Removes YOUR cached data                          │
│     • Builds CLEAN Docker images                        │
│     • Saves them to ../alexis-scrapper-portable/images/ │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│  3. Verify images are clean:                            │
│     Run: ./verify-clean-build.sh                        │
│                                                          │
│     Checks that NO hardcoded data remains              │
└─────────────────────────────────────────────────────────┘
```

### **PRODUCTION (alexis-scrapper-portable)**

```
┌─────────────────────────────────────────────────────────┐
│  4. On production VPS:                                  │
│     • Upload alexis-scrapper-portable/ folder           │
│     • Run: ./install.sh                                 │
│                                                          │
│     This loads the clean Docker images you built        │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│  5. User configuration:                                 │
│     • Access http://server:8080                         │
│     • Login with default credentials                    │
│     • Go to /settings                                   │
│     • Enter THEIR credentials                           │
│     • Enter THEIR Twitter profile info                  │
│     • Enter THEIR proxy settings                        │
│     • Save                                              │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│  6. System runs with user's data:                       │
│     • Uses THEIR Facebook/Twitter accounts              │
│     • Uses THEIR profile name/avatar                    │
│     • Creates THEIR session files                       │
│     • Generates images with THEIR profile               │
│                                                          │
│     NO hardcoded data from you!                         │
└─────────────────────────────────────────────────────────┘
```

---

## 🔧 Local Development Setup

### Prerequisites
- Docker & Docker Compose installed
- 2GB+ RAM
- Port 8080 available

### Installation

```bash
# 1. Navigate to project
cd /var/www/alexis-scrapper-docker

# 2. Create environment file
cp env.docker.template scrapper-alexis/.env
nano scrapper-alexis/.env  # Add your credentials

# 3. Start containers
docker compose up -d

# 4. Access web interface
# http://localhost:8080
# Login: admin@scraper.local / password
```

### Development Commands

```bash
# View logs
docker compose logs -f

# Restart services
docker compose restart

# Stop everything
docker compose down

# Fresh install (⚠️ deletes all data!)
./docker-clean-install.sh
```

---

## 📦 Creating Portable Distribution

### Step 1: Clean Build

```bash
./rebuild-clean-images.sh
```

**What it does:**
1. Stops all containers
2. **Removes YOUR credentials** from build context
3. **Removes YOUR auth sessions** (auth/*.json)
4. **Removes YOUR database** and cached data
5. Rebuilds Docker images from scratch (5-10 min)
6. Saves clean images to `../alexis-scrapper-portable/images/`
7. Restores your local .env for continued development

**Result:** Two files created:
- `../alexis-scrapper-portable/images/scraper-image.tar` (~1.9GB)
- `../alexis-scrapper-portable/images/web-image.tar` (~1.4GB)

### Step 2: Verify

```bash
./verify-clean-build.sh
```

**What it checks:**
- ✅ No .env files in image
- ✅ No auth session files
- ✅ No hardcoded profile names
- ✅ No personal databases

**Must pass all checks before distributing!**

### Step 3: Test

1. Copy `alexis-scrapper-portable/` to a fresh VPS
2. Run `./install.sh`
3. Configure with **different credentials**
4. Verify it uses the new credentials, not yours

### Step 4: Distribute

Once verified, you can share the `alexis-scrapper-portable/` folder.

---

## ⚙️ How Configuration Works

### Environment Variables (Critical!)

The system uses these variables from `.env`:

**Facebook:**
```bash
FACEBOOK_EMAIL=user@example.com
FACEBOOK_PASSWORD=password
FACEBOOK_PROFILES=https://facebook.com/profile1,profile2
```

**Twitter/X:**
```bash
X_EMAIL=username
X_PASSWORD=password
X_DISPLAY_NAME=Your Name          # ← Used in generated images
X_USERNAME=@yourusername          # ← Used in generated images
X_AVATAR_URL=https://...jpg       # ← Used in generated images
```

**Proxy:**
```bash
PROXY_SERVER=http://proxy:port
PROXY_USERNAME=user
PROXY_PASSWORD=pass
```

### Where Config Comes From

**In Development (this folder):**
- `.env` file in `scrapper-alexis/` directory
- You manually edit it

**In Production (portable version):**
- `.env` file is created from `env.template`
- User edits via web interface at `/settings`
- Changes are saved to `.env` automatically
- Container is restarted to apply changes

### Why This Matters

**Before (BAD):**
- Profile info was hardcoded: "El Emiliano Zapata", "@soyemizapata"
- Docker images contained YOUR sessions
- Everyone used YOUR accounts

**Now (GOOD):**
- Profile info loaded from `.env` at runtime
- Docker images are clean, no personal data
- Each installation is independent
- Users configure their own accounts

---

## 🔍 Key Scripts Explained

### `rebuild-clean-images.sh`

**Purpose:** Create clean Docker images for distribution

**Process:**
1. Backup your .env
2. Clean all personal data:
   - Auth sessions
   - Database files
   - Cached avatars
   - Screenshots
   - Logs
3. Remove .env from build context
4. Build fresh Docker images
5. Save to portable directory
6. Restore your .env

**When to run:** Before distributing to others

---

### `verify-clean-build.sh`

**Purpose:** Security check - verify no hardcoded data

**Checks:**
- No .env files in image ✓
- No auth sessions in image ✓
- No hardcoded "El Emiliano Zapata" ✓
- No hardcoded "@soyemizapata" ✓

**When to run:** After rebuild, before distributing

---

### `docker-clean-install.sh`

**Purpose:** Fresh local installation (development)

**Process:**
1. Stop containers
2. Remove all volumes (⚠️ deletes data!)
3. Rebuild images
4. Start containers
5. Create admin user

**When to run:** When you need a clean slate locally

---

## 🔒 Security & Privacy

### What's Protected in Clean Images

✅ No credentials baked in  
✅ No authentication sessions  
✅ No personal profile data  
✅ No cached avatars/screenshots  
✅ No database with old messages  
✅ Configuration via external .env only  

### Distribution Safety

✅ Images can be shared publicly  
✅ Each install is independent  
✅ Users configure own accounts  
✅ No data cross-contamination  

---

## 📚 Documentation

- `docs/HARDCODED_DATA_FIX.md` - Complete security fix details
- `docs/MANUAL_TEST_INSTRUCTIONS.md` - Testing procedures
- `docs/DYNAMIC_PROFILE_UPDATE.md` - Profile configuration guide

---

## 🆘 Troubleshooting

### Images Still Have My Data

1. Check build date: `docker image inspect alexis-scrapper:latest | grep Created`
2. Verify .dockerignore includes `auth/` and `*.env`
3. Run `./verify-clean-build.sh` to check
4. If fails, run `./rebuild-clean-images.sh` again

### Old Profile Name Still Appears

1. Check code: `grep "El Emiliano" scrapper-alexis/generate_message_images.py`
2. Should return nothing (except comments)
3. Verify environment is loaded: `grep "config.X_DISPLAY_NAME" scrapper-alexis/generate_message_images.py`
4. Rebuild if needed

### Containers Won't Start

```bash
# Check status
docker compose ps

# View logs
docker compose logs

# Rebuild
./docker-clean-install.sh
```

---

## 🔄 Workflow Summary

```
┌─────────────────┐
│ 1. Develop Here │ ← Make changes, test locally
└────────┬────────┘
         ↓
┌─────────────────┐
│ 2. Clean Build  │ ← ./rebuild-clean-images.sh
└────────┬────────┘
         ↓
┌─────────────────┐
│ 3. Verify Clean │ ← ./verify-clean-build.sh
└────────┬────────┘
         ↓
┌─────────────────┐
│ 4. Distribute   │ ← Share alexis-scrapper-portable/
└────────┬────────┘
         ↓
┌─────────────────┐
│ 5. User Install │ ← On their VPS: ./install.sh
└────────┬────────┘
         ↓
┌─────────────────┐
│ 6. User Config  │ ← Via web at :8080/settings
└────────┬────────┘
         ↓
┌─────────────────┐
│ 7. System Runs  │ ← With THEIR credentials
└─────────────────┘
```

---

## 📞 Support

For detailed information on specific topics, see:
- Security fix details: `docs/HARDCODED_DATA_FIX.md`
- Testing procedures: `docs/MANUAL_TEST_INSTRUCTIONS.md`
- Profile configuration: `docs/DYNAMIC_PROFILE_UPDATE.md`

---

**IMPORTANT:** Always run `verify-clean-build.sh` before distributing images!

---

*Last Updated: October 22, 2025*

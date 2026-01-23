# Youtube-dl WebUI Modernization Plan v2

## Overview
Transform the application to be simpler, more modern, and optimized for homelab use.

---

## Phase 1: Docker Image Optimization (PHP 8.5)
**Goal**: Fast builds with pre-built PHP image

### Changes:
1. **Switch to `serversideup/php:8.5-cli-alpine`**
   - Pre-includes: sockets, zip, mbstring, curl, opcache
   - No extension compilation needed
   - Well-maintained, updated regularly
   - ~100MB smaller than building from scratch

2. **Simplified Dockerfile**:
   ```dockerfile
   FROM serversideup/php:8.5-cli-alpine
   # Install only: python3, ffmpeg, yt-dlp
   # Copy RoadRunner binary
   # Copy composer from official image
   # Install dependencies
   # Done in ~30 seconds
   ```

### Testing Checklist:
- [ ] Build completes in <45 seconds
- [ ] Image size <250MB
- [ ] Container starts successfully
- [ ] PHP version is 8.5.x
- [ ] All PHP extensions available (sockets, zip, mbstring)

**Validation Command**:
```bash
docker build -t yt-dlp:phase1 .
time docker build -t yt-dlp:phase1 .  # Should be <45s
docker run --rm yt-dlp:phase1 php -v  # Should show 8.5.x
docker run --rm yt-dlp:phase1 php -m | grep -E "sockets|zip|mbstring"
```

---

## Phase 2: Update Dependencies to PHP 8.5
**Goal**: Ensure all Composer packages work with PHP 8.5

### Changes:
1. **Update composer.json**:
   ```json
   {
     "require": {
       "php": "^8.5",
       "spiral/roadrunner-http": "^4.0",  // Updated
       "nyholm/psr7": "^1.8",
       "symfony/process": "^7.0"  // Updated for PHP 8.5
     }
   }
   ```

2. **Update code for PHP 8.5 compatibility**:
   - Check for deprecated features
   - Update match expressions if needed
   - Verify strict types work correctly

### Testing Checklist:
- [ ] `composer install` runs without errors
- [ ] No deprecation warnings in logs
- [ ] App starts with RoadRunner
- [ ] HTTP requests work (curl http://localhost:8080)

**Validation Command**:
```bash
docker build -t yt-dlp:phase2 .
docker run --rm -d -p 8080:8080 --name test-phase2 yt-dlp:phase2
sleep 3
curl -I http://localhost:8080  # Should return 200
docker logs test-phase2 | grep -i "error\|warning"  # Should be clean
docker stop test-phase2
```

---

## Phase 3: Remove Authentication System
**Goal**: Simplify for homelab use - no login required

### Changes:
1. **Delete files**:
   - `login.php`
   - `logout.php`
   - `class/Session.php`
   - `config/config.php.TEMPLATE`

2. **Update files** (remove authentication checks):
   - `app.php`: Remove login/logout routes
   - `index.php`: Remove session checks
   - `info.php`: Remove session checks
   - `list.php`: Remove session checks
   - `logs.php`: Remove session checks
   - `views/header.php`: Remove logout button, keep only dark mode toggle

3. **Simplify startup**: No session management, no config files

### Testing Checklist:
- [ ] Home page loads without redirect
- [ ] No login prompt appears
- [ ] All features accessible immediately
- [ ] No PHP errors in logs
- [ ] Dark mode toggle still works

**Validation Command**:
```bash
docker build -t yt-dlp:phase3 .
docker run --rm -d -p 8080:8080 --name test-phase3 yt-dlp:phase3
sleep 3
curl -s http://localhost:8080 | grep -i "login" && echo "FAIL: Login found" || echo "PASS: No login"
curl -s http://localhost:8080 | grep "theme-toggle" && echo "PASS: Dark mode exists"
docker logs test-phase3 | grep -i "error"  # Should be empty
docker stop test-phase3
```

---

## Phase 4: Integrate PicoCSS
**Goal**: Modern, minimal CSS framework

### Changes:
1. **Replace custom.css with PicoCSS**:
   - Download PicoCSS v2.x (~10KB gzipped)
   - Store locally: `css/pico.min.css`
   - Create `css/custom.css` for overrides only

2. **Update HTML structure**:
   - Use PicoCSS semantic HTML
   - Update forms, buttons, cards to PicoCSS patterns
   - Keep dark mode toggle (PicoCSS has built-in dark mode)

3. **PicoCSS Benefits**:
   - Class-less (semantic HTML)
   - Built-in dark mode
   - Modern design out of the box
   - Responsive by default
   - Only ~10KB

### Testing Checklist:
- [ ] PicoCSS loads (check Network tab)
- [ ] Forms look modern and styled
- [ ] Buttons have proper styling
- [ ] Dark mode works (toggle + system preference)
- [ ] Mobile responsive (test at 375px, 768px, 1024px)
- [ ] All 5 pages render correctly

**Validation Command**:
```bash
docker build -t yt-dlp:phase4 .
docker run --rm -d -p 8080:8080 --name test-phase4 yt-dlp:phase4
sleep 3
curl -I http://localhost:8080/css/pico.min.css | grep "200 OK"
curl -s http://localhost:8080 | grep "pico" && echo "PASS: PicoCSS loaded"
# Manual: Open browser, check styling on all pages
docker stop test-phase4
```

---

## Phase 5: Test Video Download Functionality
**Goal**: Verify actual downloads work end-to-end

### Test Video:
- URL: https://youtube.com/shorts/ILl1C0nQZyY?si=AmgYwZJJMJ9x0Zk5
- Type: YouTube Short
- Should download quickly (~1-2MB)

### Testing Checklist:
- [ ] URL submission works
- [ ] Download starts successfully
- [ ] Progress/status visible
- [ ] File appears in downloads directory
- [ ] File is playable (not corrupted)
- [ ] List page shows downloaded file
- [ ] File can be deleted from UI
- [ ] Background downloads counter works

**Validation Commands**:
```bash
docker build -t yt-dlp:phase5 .
docker run --rm -d -p 8080:8080 --name test-phase5 yt-dlp:phase5
sleep 3

# Test download via curl (simulate form submission)
curl -X POST http://localhost:8080/ \
  -d "url=https://youtube.com/shorts/ILl1C0nQZyY" \
  -d "format=best"

# Wait for download
sleep 10

# Check if file exists
docker exec test-phase5 ls -lh /app/downloads/

# Verify file size > 0
docker exec test-phase5 find /app/downloads -type f -size +0

# Check logs for errors
docker logs test-phase5 | grep -i "error"

docker stop test-phase5
```

---

## Phase 6: Integration Testing
**Goal**: Test all features together

### Testing Checklist:
- [ ] **Basic Navigation**: All pages load without errors
- [ ] **Download Video**: Can download YouTube Short
- [ ] **Download Audio**: Audio-only extraction works
- [ ] **File List**: Shows all downloaded files
- [ ] **File Playback**: Browser can stream video
- [ ] **File Delete**: Can remove files from list
- [ ] **Info/Metadata**: JSON info fetch works
- [ ] **Logs**: Log viewer displays correctly
- [ ] **Dark Mode**: Toggle persists across page loads
- [ ] **Background Jobs**: Counter shows active downloads
- [ ] **Error Handling**: Invalid URLs show proper errors

**Full Test Script**:
```bash
#!/bin/bash
set -e

echo "🚀 Building final image..."
docker build -t yt-dlp:final .

echo "🏃 Starting container..."
docker run --rm -d -p 8080:8080 --name test-final yt-dlp:final
sleep 5

echo "✅ Testing home page..."
curl -f http://localhost:8080 > /dev/null

echo "✅ Testing CSS loads..."
curl -f http://localhost:8080/css/pico.min.css > /dev/null

echo "✅ Testing list page..."
curl -f http://localhost:8080/list.php > /dev/null

echo "✅ Testing info page..."
curl -f http://localhost:8080/info.php > /dev/null

echo "✅ Testing logs page..."
curl -f http://localhost:8080/logs.php > /dev/null

echo "📥 Testing video download..."
curl -X POST http://localhost:8080/ \
  -d "url=https://youtube.com/shorts/ILl1C0nQZyY" \
  -d "format=best"

echo "⏳ Waiting for download..."
sleep 15

echo "✅ Checking downloaded file..."
docker exec test-final find /app/downloads -type f -size +100k || {
  echo "❌ Download failed!"
  docker logs test-final
  exit 1
}

echo "✅ All tests passed!"
docker stop test-final
```

---

## Phase 7: Documentation & Release
**Goal**: Update docs and push to production

### Changes:
1. **Update README.md**:
   - PHP 8.5
   - No authentication (homelab-ready)
   - PicoCSS design
   - Faster builds

2. **Update .claude/session-memory.md**:
   - Document PHP 8.5 upgrade
   - Document authentication removal
   - Document PicoCSS integration

3. **Create git commit**:
   ```
   v0.4.0: PHP 8.5, No Auth, PicoCSS, Optimized Builds
   ```

4. **Tag and release**: `v0.4.0`

### Testing Checklist:
- [ ] README accurately describes new features
- [ ] Docker commands in README work
- [ ] GitHub Actions build succeeds
- [ ] Both amd64 and arm64 images work
- [ ] Can pull and run: `ghcr.io/gulasz101/youtube-dl-webui:v0.4.0`

---

## Implementation Order

### Day 1: Foundation
1. ✅ Phase 1: Docker optimization (30 min)
2. ✅ Phase 2: PHP 8.5 dependencies (20 min)
3. ✅ Test Phases 1+2 together

### Day 2: Simplification
4. ✅ Phase 3: Remove auth (45 min)
5. ✅ Test Phase 3

### Day 3: Modernization
6. ✅ Phase 4: PicoCSS (60 min)
7. ✅ Test Phase 4

### Day 4: Validation
8. ✅ Phase 5: Download test (30 min)
9. ✅ Phase 6: Integration test (45 min)
10. ✅ Phase 7: Release (30 min)

---

## Rollback Strategy

Each phase builds on the previous. If any phase fails:

1. **Identify failing phase**
2. **Revert that phase's changes**: `git checkout HEAD -- <files>`
3. **Keep successful phases**
4. **Fix issue**
5. **Re-test before proceeding**

---

## Success Criteria

✅ **Build time**: <45 seconds (cached)
✅ **Image size**: <250MB
✅ **PHP version**: 8.5.x
✅ **No authentication**: Direct access
✅ **Modern UI**: PicoCSS applied
✅ **Downloads work**: Test video downloads successfully
✅ **All features work**: Forms, lists, logs, info
✅ **Dark mode**: Persists across sessions
✅ **Multi-platform**: amd64 + arm64 builds succeed

---

## Risk Mitigation

### Risk 1: PHP 8.5 incompatibility
- **Mitigation**: Test each dependency separately
- **Fallback**: Use PHP 8.4 if blockers found

### Risk 2: PicoCSS breaks layout
- **Mitigation**: Implement page-by-page
- **Fallback**: Keep custom CSS as override

### Risk 3: Download functionality breaks
- **Mitigation**: Test at Phase 5 before proceeding
- **Fallback**: Debug with verbose logging

### Risk 4: serversideup/php image issues
- **Mitigation**: Test thoroughly in Phase 1
- **Fallback**: Use official php:8.5-alpine with install-php-extensions

---

## Notes

- Each phase is independently testable
- No phase should break existing functionality until Phase 3 (auth removal)
- Always build and test locally before pushing
- Docker commands provided for every validation
- Test script automates Phase 6
- Session memory updated at end

---

**Ready for approval?** Please review and let me know if you want any adjustments to the plan.

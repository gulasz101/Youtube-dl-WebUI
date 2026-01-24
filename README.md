# Youtube-dl WebUI

## Description
Youtube-dl WebUI is a small web interface for youtube-dl/yt-dlp. It allows you to host your own video downloader. 
After the download you can stream your videos from your web browser (or VLC or others)
or save it on your computer directly from the list page.

### Why I forked it?
I just wanted to challenge myself a little bit and do small refactoring to some random legacy piece of php code. Also I needed such solution on my home media server so why not to make stuff more complicated and instead of using anything that is already operational way I like, to force some random piece of code to work way I like. ;)

### v0.5.6 Changes (Latest) - Simplified Format Selection
- **🎯 Simple Container Selection**: Choose MP4, WebM, MKV, or Default (any format)
- **🗑️ Removed Complex Format Fetching**: No more "Fetch Formats" button - just select what you want
- **🐛 Fixed JSON Parsing**: Added `--no-warnings` flag to prevent yt-dlp warnings breaking JSON
- **✨ Cleaner UI**: Simple dropdowns for Quality and Container format
- **⚡ Faster Workflow**: No need to fetch formats first - just select and download

### v0.5.5 Changes - Debug Logging Enhancement
- **🔍 Comprehensive Debug Logging**: Added detailed logging at every step of format fetching
- **📝 Request Tracing**: Log request body, parsed data, and all parameters
- **⚙️ Command Execution Logs**: Log yt-dlp command, exit code, and output preview
- **🐛 Detailed Error Messages**: Return full error context in API responses for easier debugging
- **📊 Multi-Level Exception Handling**: Catch and log exceptions at coroutine and main levels

### v0.5.4 Changes - Logging Improvements
- **📋 Application Logs Viewer**: Added Application Logs section in logs tab showing app.log entries
- **🖥️ Docker Console Logging**: All logs now output to stdout for visibility in Docker logs
- **🎨 Color-Coded Levels**: Log levels color-coded (ERROR in red, WARNING in yellow)
- **📊 Enhanced Visibility**: Format fetching and all operations now logged with detailed context

### v0.5.2 Changes - Format Fetching UX
- **📊 Format Fetching Jobs**: Format fetching now appears as a visible job in the jobs dropdown
- **🐛 Better Error Handling**: Improved error messages when format fetching fails
- **🎬 Video Preview Controls**: Added close button to video/audio preview player

### v0.5.1 Changes - Build Optimization
- **⚡ Faster Docker Builds**: Switched to official `phpswoole/swoole` base image (~60% faster builds)
- **📦 Pre-built Swoole**: No longer compiling Swoole from source, reducing build time from ~5-10min to ~2min
- **🔧 Cleaner Builds**: Removed build tool dependencies (autoconf, g++, make) from final image

### v0.5.0 Changes - Swoole Migration
- **🚀 Swoole Server**: Migrated from RoadRunner to Swoole for better async performance and coroutine support
- **⚡ Async Downloads**: Non-blocking downloads with coroutines - download multiple videos simultaneously
- **📊 Real-Time Progress**: Server-Sent Events (SSE) for live download progress updates (no more polling!)
- **🎬 In-Browser Playback**: Watch videos directly in browser with seeking support via HTTP range requests
- **🎯 Format Selection**: Dynamic format/quality dropdowns populated from yt-dlp metadata
- **📝 Structured Logging**: JSON-based logging with Monolog for better debugging
- **💾 Job Management**: Shared memory job tracking across all workers using Swoole\Table
- **🎨 Enhanced UI**: Progress bars, quality selectors, and inline video player
- **⚙️ PHP 8.3**: Using PHP 8.3 for Swoole compatibility (Swoole doesn't support PHP 8.5 yet)

### v0.4.5 Changes
- **Critical Fix**: Resolved RoadRunner worker corruption causing random 404 errors after exceptions
- **Worker Management**: Configure automatic worker restarts (max 50 requests per worker)
- **Multiple Workers**: Run 2 workers for better concurrency and fault tolerance
- **Error Handling**: Catch exceptions in info.php and index.php to prevent worker crashes

### v0.4.4 Changes
- **Bug Fixes**: Fixed Chrome DevTools 404 causing HTTP 500 errors and worker crashes
- **RoadRunner Config**: Updated to version 3 (removes startup warning)
- **Clean Logs**: Suppressed PHP 8.5 deprecation warnings from vendor packages
- **Error Handling**: Proper 404 responses for missing static files

### v0.4.3 Changes
- **Deno JavaScript Runtime**: Added deno support for YouTube video extraction (fixes bot protection)
- **Bug Fixes**: Fixed delete functionality in logs.php and list.php (PSR-7 request handling)

### v0.4.0 Changes
- **PHP 8.5**: Upgraded to latest stable PHP 8.5 with updated dependencies (symfony/process ^7.0, phpstan ^2.0)
- **PicoCSS Framework**: Replaced custom CSS with PicoCSS v2 for modern, semantic, class-less HTML design
- **Authentication Removed**: Simplified for homelab/local network use - no login required
- **Semantic HTML5**: All pages converted to use semantic elements (nav, main, article, figure)
- **Enhanced Dark Mode**: Integrated with PicoCSS data-theme system
- **Simplified Configuration**: Removed authentication-related settings from config
- **Faster Builds**: ~47 second Docker builds with optimized caching

### v0.3.0 Changes
- **Modern UI Design**: Completely redesigned interface with 2024+ aesthetic using design tokens (CSS custom properties)
- **Dark Mode**: Full dark mode support with manual toggle and system preference detection
- **Optimized Docker Builds**: Multi-stage Dockerfile reducing build time from 4 minutes to ~1 minute (85% improvement)
- **Multi-Platform Support**: Docker images now available for both `linux/amd64` and `linux/arm64` architectures
- **Enhanced Performance**: Improved caching strategy and layer optimization in Docker builds
- **Modern Components**: Updated buttons, forms, cards, tables, and navigation with smooth transitions and hover effects

### v0.2.0 Changes
- Removed Bootstrap CSS and JavaScript dependencies
- Switched to vanilla CSS and JavaScript
- Updated PHP to version 8.3
- Updated RoadRunner to latest version
- Added GitHub Actions for automated Docker image building and pushing to GHCR.io
- Updated dependencies

It supports:

* simultaneous async downloads in background with real-time progress
* yt-dlp, youtube-dl (and others)
* structured JSON logging
* fetch video info and available formats before download
* in-browser video/audio playback with seeking
* dynamic format and quality selection

## Requirements
- PHP 8.3 or higher (PHP 8.5 not yet supported by Swoole)
- [Swoole extension](https://www.swoole.co.uk/) (v5.0+)
- composer
- python3 for yt-dlp
- [yt-dlp](https://github.com/yt-dlp/yt-dlp)
- ffmpeg (or avconv) is required for audio extraction:
`-x, --extract-audio convert video files to audio-only files (requires ffmpeg or avconv and ffprobe or avprobe)`

## How to install?
### Recommended way:
I recommend using the pre-built Docker image from GitHub Container Registry:
```bash
docker run --rm -d -p 8080:8080 \
  -v $(pwd)/downloads:/app/downloads \
  -v $(pwd)/logs:/app/logs \
  ghcr.io/gulasz101/youtube-dl-webui:latest
```

Or use a specific version:
```bash
docker run --rm -d -p 8080:8080 \
  -v $(pwd)/downloads:/app/downloads \
  -v $(pwd)/logs:/app/logs \
  ghcr.io/gulasz101/youtube-dl-webui:v0.5.6
```

Then visit [http://localhost:8080](http://localhost:8080)

**Note**: The application is designed for homelab/trusted network use and has no authentication.

### Manual Installation (Advanced):
If you prefer to run without Docker:

1. **Install Swoole extension:**
```bash
pecl install swoole
echo "extension=swoole.so" >> /path/to/php.ini
```

2. **Clone and setup:**
```bash
git clone https://github.com/gulasz101/Youtube-dl-WebUI.git
cd Youtube-dl-WebUI
composer install
```

3. **Configure:**
```bash
cp config/config.php.example config/config.php
# Edit config/config.php with your settings
```

4. **Start the server:**
```bash
php swoole-server.php
```

5. **Visit [http://localhost:8080](http://localhost:8080)**

### Configuration

Edit `config/config.php`:
- `bin`: Path to yt-dlp binary (default: `/usr/local/bin/yt-dlp`)
- `outputFolder`: Download directory (default: `downloads`)
- `logFolder`: Log directory (default: `logs`)
- `max_dl`: Maximum concurrent downloads (default: `3`, `0` = unlimited)

## Libraries & Technologies

Youtube-dl WebUI uses:

- [PicoCSS v2](https://picocss.com/) - Minimal CSS framework for semantic HTML
- Vanilla JavaScript with Server-Sent Events (SSE) for real-time updates
- [PHP 8.3](https://www.php.net/) with [Swoole 5.1](https://www.swoole.co.uk/) coroutine server
- [phpswoole/swoole](https://hub.docker.com/r/phpswoole/swoole) - Official Swoole Docker image
- [Monolog](https://github.com/Seldaek/monolog) - Structured JSON logging
- [yt-dlp](https://github.com/yt-dlp/yt-dlp) or [youtube-dl](https://youtube-dl.org/) (or any compatible fork)
- [FFmpeg](https://ffmpeg.org/) for media manipulation and audio extraction

## Features

### 🎯 Smart Format Selection
- Fetch available formats/qualities before downloading
- Dynamic dropdowns with resolution, codec, and file size info
- Quality presets: Best, 1080p, 720p, 480p, 360p, Worst

### ⚡ Async Downloads
- Non-blocking concurrent downloads using Swoole coroutines
- Configurable max concurrent downloads
- Background job management with shared memory

### 📊 Real-Time Progress
- Live download progress via Server-Sent Events
- Progress bars in job dropdown
- Instant status updates (queued → downloading → completed)

### 🎬 In-Browser Playback
- Click video files to play directly in browser
- HTTP range request support for seeking
- Works with mp4, webm, mkv, mp3, etc.

### 📝 Structured Logging
- JSON-formatted logs with Monolog
- Searchable and parsable log entries
- Job lifecycle tracking


## License

Copyright (c) 2023 gulasz101

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

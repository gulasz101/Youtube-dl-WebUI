# Youtube-dl WebUI

## Description
Youtube-dl WebUI is a small web interface for youtube-dl/yt-dlp. It allows you to host your own video downloader. 
After the download you can stream your videos from your web browser (or VLC or others)
or save it on your computer directly from the list page.

### Why I forked it?
I just wanted to challenge myself a little bit and do small refactoring to some random legacy piece of php code. Also I needed such solution on my home media server so why not to make stuff more complicated and instead of using anything that is already operational way I like, to force some random piece of code to work way I like. ;)

### v0.4.0 Changes (Latest)
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

* simultaneous downloads in background
* yt-dlp, youtube-dl (and others)
* logging
* fetch info without download

## Requirements
- [web server - RoadRunner]( https://roadrunner.dev/ )
- PHP 8.5 (recommended) or PHP >= 8.3
- composer
- python3 for yt-dlp
- [yt-dlp](https://github.com/yt-dlp/yt-dlp)
- ffmpeg (or avconv) is required for audio extraction, from youtube-dl doc:
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
  ghcr.io/gulasz101/youtube-dl-webui:v0.4.0
```

Then visit [http://localhost:8080](http://localhost:8080)

**Note**: The application is designed for homelab/trusted network use and has no authentication.

### Non recommended way:
- clone repo
- go inside, `cd Youtube-dl-WebUI`
- execute `composer install`
- execute `rr serve`
- visit [localhost]( http://localhost:8080 )

## Libraries & Technologies

Youtube-dl WebUI uses:

- [PicoCSS v2](https://picocss.com/) - Minimal CSS framework for semantic HTML
- Vanilla JavaScript for interactivity
- [PHP 8.5](https://www.php.net/) with [RoadRunner](https://roadrunner.dev/) application server
- [yt-dlp](https://github.com/yt-dlp/yt-dlp) or [youtube-dl](https://youtube-dl.org/) (or any compatible fork)
- [FFmpeg](https://ffmpeg.org/) for media manipulation, if present


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

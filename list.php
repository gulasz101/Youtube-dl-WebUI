<?php

declare(strict_types=1);

use App\Utils\FileHandler;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

use function StrictHelpers\ob_get_contents;

$file = new FileHandler();

/**
 * @var ServerRequestInterface $request
 */
$queryParams = $request->getQueryParams();

if (isset($queryParams["delete"])) {
    $file->delete($queryParams["delete"]);
    return new Response(302, ['Location' => 'list.php']);
}

$files = $file->listFiles();
$parts = $file->listParts();

ob_start();
require 'views/header.php';
?>
  <?php
  if (!empty($files)) {
      ?>
    <h2>Downloaded Files</h2>
    <figure>
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Size (KB)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
              foreach ($files as $f) {
                  echo "<tr>";

                  $isMedia = $file->isMediaFile($f["name"]);
                  $streamUrl = "/stream/" . rawurlencode($f["name"]);

                  if ($isMedia) {
                      // Media files: link to stream endpoint (no download attribute)
                      echo "<td><a href=\"" . $streamUrl . "\">" . htmlspecialchars($f["name"]) . "</a></td>";
                  } else {
                      // Non-media files: force download
                      echo "<td><a href=\"" . $streamUrl . "\" download>" . htmlspecialchars($f["name"]) . "</a></td>";
                  }

                  echo "<td>" . $f["size"] . "</td>";
                  echo "<td><a href=\"./list.php?delete=" . sha1($f["name"]) . "\" class=\"contrast\">Delete</a></td>";
                  echo "</tr>";
              }
        ?>
        </tbody>
      </table>
    </figure>
  <?php
  } else {
      echo "<p><mark>No files yet!</mark></p>";
  }
  ?>

  <?php
  if (!empty($parts)) {
      ?>
    <h2>Partial Downloads</h2>
    <figure>
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Size (KB)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
            foreach ($parts as $f) {
                echo "<tr>";
                if ($file->get_relative_downloads_folder()) {
                    echo "<td><a href=\"" . rawurlencode($file->get_relative_downloads_folder()) . '/' . rawurlencode($f["name"]) . "\" download>" . htmlspecialchars($f["name"]) . "</a></td>";
                } else {
                    echo "<td>" . htmlspecialchars($f["name"]) . "</td>";
                }
                echo "<td>" . $f["size"] . "</td>";
                echo "<td><a href=\"./list.php?delete=" . sha1($f["name"]) . "\" role=\"button\" class=\"secondary\">Delete</a></td>";
                echo "</tr>";
            }
      ?>
        </tbody>
      </table>
    </figure>
  <?php
  }
  ?>

  <div id="video-preview" style="display:none; margin-top: 2rem;">
    <h3>Preview</h3>
    <video id="video-player" controls style="width: 100%; max-width: 800px;">
      <source id="video-source" src="" type="video/mp4">
      Your browser does not support video playback.
    </video>
    <audio id="audio-player" controls style="width: 100%; max-width: 800px; display:none;">
      <source id="audio-source" src="" type="audio/mpeg">
      Your browser does not support audio playback.
    </audio>
  </div>

  <script>
    // Handle media file clicks for inline playback
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('a[href^="/stream/"]').forEach(link => {
        if (!link.hasAttribute('download')) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            const mediaUrl = this.href;
            const fileName = this.textContent.trim();
            const ext = fileName.split('.').pop().toLowerCase();

            const previewDiv = document.getElementById('video-preview');
            const videoPlayer = document.getElementById('video-player');
            const audioPlayer = document.getElementById('audio-player');
            const videoSource = document.getElementById('video-source');
            const audioSource = document.getElementById('audio-source');

            // Determine if it's audio or video
            const audioExts = ['mp3', 'ogg', 'wav', 'm4a', 'flac', 'aac'];
            const isAudio = audioExts.includes(ext);

            if (isAudio) {
              // Show audio player
              videoPlayer.style.display = 'none';
              audioPlayer.style.display = 'block';
              audioSource.src = mediaUrl;
              audioPlayer.load();
              audioPlayer.play();
            } else {
              // Show video player
              audioPlayer.style.display = 'none';
              videoPlayer.style.display = 'block';
              videoSource.src = mediaUrl;
              videoPlayer.load();
              videoPlayer.play();
            }

            previewDiv.style.display = 'block';
            previewDiv.scrollIntoView({ behavior: 'smooth' });
          });
        }
      });
    });
  </script>
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

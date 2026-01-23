<?php

declare(strict_types=1);

use App\Utils\Downloader;
use App\Utils\FileHandler;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

use function StrictHelpers\ob_get_contents;

$file = new FileHandler();

/** @var ServerRequestInterface $request */

$postInput = $request->getParsedBody();
$queryParams = $request->getQueryParams();

$errors = [];

if (isset($queryParams['kill']) && !empty($queryParams['kill']) && $queryParams['kill'] === "all") {
    Downloader::kill_them_all();
}

if (isset($postInput['urls']) && !empty($postInput['urls'])) {
    $audio_only = false;
    if (isset($postInput['audio']) && !empty($postInput['audio'])) {
        $audio_only = true;
    }

    $outfilename = false;
    if (isset($postInput['outfilename']) && !empty($postInput['outfilename'])) {
        $outfilename = $postInput['outfilename'];
    }

    $vformat = null;
    if (isset($postInput['vformat']) && !empty($postInput['vformat'])) {
        $vformat = $postInput['vformat'];
    }

    try {
        $downloader = new Downloader($postInput['urls']);
        $downloader->download($audio_only, $outfilename, $vformat);

        // Redirect after download if no errors
        if (empty($errors)) {
            return new Response(302, ['Location' => 'index.php']);
        }
    } catch (\Throwable $e) {
        $errors[] = 'Error starting download: ' . $e->getMessage();
    }
}

ob_start();
require 'views/header.php';
?>
  <h1>Download</h1>
  <?php
  if (!empty($errors)) {
      foreach ($errors as $e) {
          echo "<p><mark>$e</mark></p>";
      }
  }
  ?>
  <form id="download-form" action="index.php" method="post">
    <label for="url">Video URLs (space-separated):
      <input id="url" name="urls" placeholder="https://youtube.com/watch?v=..." type="text" required />
    </label>
    <div class="grid">
      <label>
        <input type="checkbox" id="audioCheck" name="audio" role="switch" />
        Audio Only
      </label>
      <label for="outfilename">Filename:
        <input id="outfilename" name="outfilename" placeholder="%(title)s-%(id)s.%(ext)s" type="text">
      </label>
      <label for="vformat">Format:
        <input id="vformat" name="vformat" placeholder="Format code" type="text" />
      </label>
    </div>
    <button type="submit">Download</button>
  </form>

  <div class="grid">
    <article>
      <header><strong>System Info</strong></header>
      <p><small>Free space: <?php echo $file->free_space(); ?> KB</small></p>
      <p><small>Used space: <?php echo $file->used_space(); ?> KB</small></p>
      <p><small>Download folder: <?php echo $file->get_downloads_folder(); ?></small></p>
      <p><small>yt-dlp version: <?php echo Downloader::get_youtubedl_version(); ?></small></p>
    </article>
    <article>
      <header><strong>Help</strong></header>
      <p><strong>How does it work?</strong><br>
      Simply paste your video link in the field and click "Download"</p>
      <p><strong>Supported sites</strong><br>
      <a href="https://github.com/yt-dlp/yt-dlp/blob/master/supportedsites.md">View list</a></p>
      <p><strong>Filename & Format</strong><br>
      Optional fields. See docs for <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#format-selection">format selection</a> and <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#output-template">output templates</a></p>
    </article>
  </div>
<?php
require 'views/footer.php';

$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

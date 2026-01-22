<?php

declare(strict_types=1);

use App\Utils\Downloader;
use App\Utils\FileHandler;
use App\Utils\Session;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

use function StrictHelpers\ob_get_contents;

$session = Session::getInstance();
$file = new FileHandler();

/** @var ServerRequestInterface $request */

$postInput = $request->getParsedBody();
$queryParams = $request->getQueryParams();

if (!$session->is_logged_in()) {
    return new Response(302, ['Location' => 'login.php']);
} else {
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

        $downloader = new Downloader($postInput['urls']);
        $downloader->download($audio_only, $outfilename, $vformat);

        if (!isset($_SESSION['errors'])) {
            return new Response(302, ['Location' => 'index.php']);
        }
    }
}
ob_start();
require 'views/header.php';
?>
<div class="container my-4">
  <h1>Download</h1>
  <?php

  if (isset($_SESSION['errors']) && $_SESSION['errors'] > 0) {
      foreach ($_SESSION['errors'] as $e) {
          echo "<div class=\"alert alert-warning\" role=\"alert\">$e</div>";
      }
  }

?>
  <form id="download-form" action="index.php" method="post">
    <div class="row my-3">
      <div class="input-group">
        <span class="input-group-text" id="urls-addon">URLs:</span>
        <input class="form-control" id="url" name="urls" placeholder="Link(s) separated by a space" type="text" aria-describedby="urls-addon" required />
      </div>
    </div>
    <div class="row mt-3 align-items-center">
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Download</button>
      </div>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="audioCheck" name="audio" />
          <label class="form-check-label" for="audioCheck">Audio Only</label>
        </div>
      </div>
      <div class="col-auto">
        <div class="input-group">
          <span class="input-group-text" id="outfilename-addon">Filename:</span>
          <input class="form-control" id="outfilename" name="outfilename" placeholder="Output filename template" type="text" aria-describedby="outfilename-addon">
        </div>
      </div>
      <div class="col-auto">
        <div class="input-group">
          <span class="input-group-text" id="vformat-addon">Format:</span>
          <input class="form-control" id="vformat" name="vformat" placeholder="Video format code" type="text" aria-describedby="vformat-addon" />
        </div>
      </div>
    </div>

  </form>
  <br>
  <div class="row">
    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header">Info</div>
        <div class="card-body">
          <p>Free space : <?php echo $file->free_space(); ?></p>
          <p>Used space : <?php echo $file->used_space(); ?></p>
          <p>Download folder : <?php echo $file->get_downloads_folder(); ?></p>
          <p>Youtube-dl version : <?php echo Downloader::get_youtubedl_version(); ?></p>
        </div>
      </div>
    </div>
    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header">Help</div>
        <div class="card-body">
          <p><b>How does it work ?</b></p>
          <p>Simply paste your video link in the field and click "Download"</p>
          <p><b>With which sites does it work?</b></p>
          <p><a href="https://github.com/yt-dlp/yt-dlp/blob/master/supportedsites.md">Here's</a> a list of the supported sites</p>
          <p><b>How can I download the video on my computer?</b></p>
          <p>Go to <a href="./list.php">List of files</a> -> choose one -> right click on the link -> "Save target as ..." </p>
          <p><b>What's Filename or Format field?</b></p>
          <p>They are optional, see the official documentation about <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#format-selection">Format selection</a> or <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#output-template">Output template</a> </p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
unset($_SESSION['errors']);
require 'views/footer.php';

$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

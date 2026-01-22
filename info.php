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
/**
 * @var ServerRequestInterface $request
 */

$postInput = $request->getParsedBody();

ob_start();
require 'views/header.php';

if ($session->is_logged_in() !== true) {
    return new Response(302, ['Location' => 'login.php']);
} else {
    $json = false;

    if (isset($postInput['urls']) && !empty($postInput['urls'])) {
        $downloader = new Downloader($postInput['urls']);
        $json = $downloader->info();
    }
}
?>
<div class="container my-4">
  <h1>JSON Info</h1>
  <?php

  if (isset($_SESSION['errors']) && $_SESSION['errors'] > 0) {
      foreach ($_SESSION['errors'] as $e) {
          echo "<div class=\"alert\">$e</div>";
      }
  }

?>
  <form id="info-form" action="info.php" method="post">
    <div class="row my-3">
      <div class="input-group">
        <span class="input-group-text" id="urls-addon">URLs:</span>
        <input class="form-control" id="url" name="urls" placeholder="Link(s) separated by a space" type="text" aria-describedby="urls-addon" required />
      </div>
    </div>
    <div class="row mt-3 align-items-center">
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Query</button>
      </div>
    </div>

  </form>
  <br>
  <div class="row">
    <?php
  if ($json) {
      ?>
      <div class="card">
        <div class="card-header">
          <h3>Info</h3>
        </div>
        <div class="card-body">
          <textarea rows="50" class="form-control"><?php echo $json ?></textarea>
        </div>
      </div>
    <?php
  }
?>
  </div>
</div>
<?php
unset($_SESSION['errors']);
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

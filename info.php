<?php

declare(strict_types=1);

use App\Utils\Downloader;
use App\Utils\FileHandler;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

use function StrictHelpers\ob_get_contents;

$file = new FileHandler();
/**
 * @var ServerRequestInterface $request
 */

$postInput = $request->getParsedBody();
$errors = [];
$json = false;

if (isset($postInput['urls']) && !empty($postInput['urls'])) {
    try {
        $downloader = new Downloader($postInput['urls']);
        $json = $downloader->info();
    } catch (\Throwable $e) {
        $errors[] = 'Error fetching video info: ' . $e->getMessage();
    }
}

ob_start();
require 'views/header.php';
?>
  <h1>Video Info</h1>
  <?php
  if (!empty($errors)) {
      foreach ($errors as $e) {
          echo "<p><mark>$e</mark></p>";
      }
  }
  ?>
  <form id="info-form" action="info.php" method="post">
    <label for="url">Video URLs (space-separated):
      <input id="url" name="urls" placeholder="https://youtube.com/watch?v=..." type="text" required />
    </label>
    <button type="submit">Query</button>
  </form>

  <?php
  if ($json) {
      ?>
    <article>
      <header><strong>JSON Metadata</strong></header>
      <textarea rows="25" readonly><?php echo htmlspecialchars($json) ?></textarea>
    </article>
  <?php
  }
  ?>
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

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
                  if ($file->get_relative_downloads_folder()) {
                      echo "<td><a href=\"" . rawurlencode($file->get_relative_downloads_folder()) . '/' . rawurlencode($f["name"]) . "\" download>" . htmlspecialchars($f["name"]) . "</a></td>";
                  } else {
                      echo "<td>" . htmlspecialchars($f["name"]) . "</td>";
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
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

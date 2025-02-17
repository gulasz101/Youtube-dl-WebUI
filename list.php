<?php

declare(strict_types=1);

use App\Utils\FileHandler;
use App\Utils\Session;
use Nyholm\Psr7\Response;

use function StrictHelpers\ob_get_contents;

$session = Session::getInstance();
$file = new FileHandler();

if ($session->is_logged_in() !== true) {
    return new Response(302, ['Location' => 'login.php']);
}

if ($session->is_logged_in() === true && isset($_GET["delete"])) {
    $file->delete($_GET["delete"]);
    return new Response(302, ['Location' => 'list.php']);
}

$files = $file->listFiles();
$parts = $file->listParts();

ob_start();
require 'views/header.php';
?>
<div class="container my-4">
  <?php
  if (!empty($files)) {
      ?>
    <h2>List of available files:</h2>
    <table class="table table-striped table-hover ">
      <thead>
        <tr>
          <th>Title</th>
          <th>Size</th>
          <th><span class="pull-right">Delete link</span></th>
        </tr>
      </thead>
      <tbody>
        <?php
            foreach ($files as $f) {
                echo "<tr>";
                if ($file->get_relative_downloads_folder()) {
                    echo "<td><a href=\"" . rawurlencode($file->get_relative_downloads_folder()) . '/' . rawurlencode($f["name"]) . "\" download>" . $f["name"] . "</a></td>";
                } else {
                    echo "<td>" . $f["name"] . "</td>";
                }
                echo "<td>" . $f["size"] . "</td>";
                echo "<td><a href=\"./list.php?delete=" . sha1($f["name"]) . "\" class=\"btn btn-danger btn-sm pull-right\">Delete</a></td>";
                echo "</tr>";
            }
      ?>
      </tbody>
    </table>
  <?php
  } else {
      echo "<br><div class=\"alert alert-warning\" role=\"alert\">No files!</div>";
  }
?>
  <br />
  <?php
if (!empty($parts)) {
    ?>
    <h2>List of part files:</h2>
    <table class="table table-striped table-hover ">
      <thead>
        <tr>
          <th>Title</th>
          <th>Size</th>
          <th><span class="pull-right">Delete link</span></th>
        </tr>
      </thead>
      <tbody>
        <?php
          foreach ($parts as $f) {
              echo "<tr>";
              if ($file->get_relative_downloads_folder()) {
                  echo "<td><a href=\"" . rawurlencode($file->get_relative_downloads_folder()) . '/' . rawurlencode($f["name"]) . "\" download>" . $f["name"] . "</a></td>";
              } else {
                  echo "<td>" . $f["name"] . "</td>";
              }
              echo "<td>" . $f["size"] . "</td>";
              echo "<td><a href=\"./list.php?delete=" . sha1($f["name"]) . "\" class=\"btn btn-danger btn-sm pull-right\">Delete</a></td>";
              echo "</tr>";
          }
    ?>
      </tbody>
    </table>
    <br />
    <br />
  <?php
}
?>
  <br />
</div><!-- End container -->
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

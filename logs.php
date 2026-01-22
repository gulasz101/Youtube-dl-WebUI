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

$files = $file->listLogs();

if ($session->is_logged_in() === true && isset($_GET["delete"])) {
    $file->deleteLog($_GET["delete"]);
    return new Response(302, ['Location' => 'logs.php']);
}

ob_start();

require 'views/header.php';
?>
<div class="container my-4">
  <?php
  if (!empty($files)) {
      ?>
    <h1>List of logs:</h1>
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Ended?</th>
          <th>Ok?</th>
          <th>Size</th>
          <th>Delete link</th>
        </tr>
      </thead>
      <tbody>
        <?php

            foreach ($files as $f) {
                echo "<tr>";
                if ($file->get_relative_log_folder()) {
                    echo "<td><div><a href=\"" . rawurlencode($file->get_relative_log_folder()) . '/' . rawurlencode($f["name"]) . "\" target=\"_blank\">" . $f["name"] . "</a></div><div>" . $f["lastline"] . "</div></td>";
                } else {
                    echo "<td><div>" . $f["name"] . "</div><div>" . $f["lastline"] . "</div></td>";
                }
                echo "<td>" . ($f["ended"] ? '&#10003;' : '') . "</td>";
                echo "<td>" . ($f["100"] ? '&#10003;' : '') . "</td>";
                echo "<td>" . $f["size"] . "</td>";
                echo "<td><a href=\"./logs.php?delete=" . sha1($f["name"]) . "\" class=\"btn btn-danger btn-sm\">Delete</a></td>";
                echo "</tr>";
            }
      ?>
      </tbody>
    </table>
    <br />
    <br />
  <?php
  } else {
      echo "<br><div class=\"alert\">No logs!</div>";
  }
?>
  <br />
</div>
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

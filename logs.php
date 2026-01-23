<?php

declare(strict_types=1);

use App\Utils\FileHandler;
use Nyholm\Psr7\Response;

use function StrictHelpers\ob_get_contents;

$file = new FileHandler();

if (isset($_GET["delete"])) {
    $file->deleteLog($_GET["delete"]);
    return new Response(302, ['Location' => 'logs.php']);
}

$files = $file->listLogs();

ob_start();

require 'views/header.php';
?>
  <?php
  if (!empty($files)) {
      ?>
    <h1>Download Logs</h1>
    <figure>
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Complete</th>
            <th>Success</th>
            <th>Size (KB)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
              foreach ($files as $f) {
                  echo "<tr>";
                  if ($file->get_relative_log_folder()) {
                      echo "<td><a href=\"" . rawurlencode($file->get_relative_log_folder()) . '/' . rawurlencode($f["name"]) . "\" target=\"_blank\">" . htmlspecialchars($f["name"]) . "</a><br><small>" . htmlspecialchars($f["lastline"]) . "</small></td>";
                  } else {
                      echo "<td>" . htmlspecialchars($f["name"]) . "<br><small>" . htmlspecialchars($f["lastline"]) . "</small></td>";
                  }
                  echo "<td>" . ($f["ended"] ? '✓' : '') . "</td>";
                  echo "<td>" . ($f["100"] ? '✓' : '') . "</td>";
                  echo "<td>" . $f["size"] . "</td>";
                  echo "<td><a href=\"./logs.php?delete=" . sha1($f["name"]) . "\" role=\"button\" class=\"secondary\">Delete</a></td>";
                  echo "</tr>";
              }
        ?>
        </tbody>
      </table>
    </figure>
  <?php
  } else {
      echo "<p><mark>No logs yet!</mark></p>";
  }
  ?>
<?php
require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

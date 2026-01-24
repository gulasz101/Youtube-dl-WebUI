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
    $file->deleteLog($queryParams["delete"]);
    return new Response(302, ['Location' => 'logs.php']);
}

$files = $file->listLogs();
$appLogs = $file->getAppLogEntries(50); // Get last 50 app log entries

ob_start();

require 'views/header.php';
?>
  <?php
  // Application Logs Section
  if (!empty($appLogs)) {
      ?>
    <h1>Application Logs</h1>
    <figure>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Level</th>
            <th>Message</th>
            <th>Context</th>
          </tr>
        </thead>
        <tbody>
          <?php
              foreach ($appLogs as $log) {
                  $timestamp = $log['datetime'] ?? 'N/A';
                  $level = $log['level_name'] ?? $log['level'] ?? 'INFO';
                  $message = $log['message'] ?? '';
                  $context = $log['context'] ?? [];

                  // Format level with color
                  $levelClass = match($level) {
                      'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'style="color: var(--pico-del-color);"',
                      'WARNING' => 'style="color: var(--pico-ins-color);"',
                      'INFO' => '',
                      default => ''
                  };

                  echo "<tr>";
                  echo "<td><small>" . htmlspecialchars(substr($timestamp, 11, 8)) . "</small></td>"; // HH:MM:SS
                  echo "<td {$levelClass}><strong>" . htmlspecialchars($level) . "</strong></td>";
                  echo "<td>" . htmlspecialchars($message) . "</td>";
                  echo "<td><small><code>" . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</code></small></td>";
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
                  echo "<td><a href=\"./logs.php?delete=" . sha1($f["name"]) . "\" class=\"contrast\">Delete</a></td>";
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

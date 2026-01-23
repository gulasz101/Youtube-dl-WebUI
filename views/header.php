<!DOCTYPE html>
<html data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Youtube-dl WebUI</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
  <link rel="Shortcut Icon" href="./favicon_144.png" type="image/x-icon">
</head>
<body>
  <header>
    <nav class="container-fluid">
      <ul>
        <li><strong>Youtube-dl WebUI</strong></li>
      </ul>
      <?php
      use App\Utils\Downloader;
      if (isset($file)) {
      ?>
      <ul>
        <li><a href="./">Download</a></li>
        <li><a href="./info.php">Info</a></li>
        <li><a href="./list.php">Files<?php
          $filesCount = count($file->listFiles());
          if ($filesCount > 0) echo " ($filesCount)";
        ?></a></li>
        <?php if ($file->is_log_enabled()) { ?>
        <li><a href="./logs.php">Logs<?php
          $filesCount = $file->countLogs();
          if ($filesCount > 0) echo " ($filesCount)";
        ?></a></li>
        <?php } ?>
        <li><details id="jobs-dropdown">
          <summary>Jobs: <span id="job-count">0</span>/<span id="max-jobs"><?php echo Downloader::max_background_jobs(); ?></span></summary>
          <ul id="job-list">
            <li>Loading...</li>
          </ul>
        </details></li>
        <li><button onclick="toggleTheme()" aria-label="Toggle theme">🌓</button></li>
      </ul>
      <?php } ?>
    </nav>
  </header>
  <main class="container">

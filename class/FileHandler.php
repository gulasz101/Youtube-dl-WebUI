<?php

namespace App\Utils;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

use function StrictHelpers\file_get_contents;
use function StrictHelpers\filesize;
use function StrictHelpers\fopen;
use function StrictHelpers\glob;
use function StrictHelpers\realpath;

class FileHandler
{
  /**
   * @var array<string, mixed>
   */
  private array $config = [];
  private string $re_partial = '/(?:\.part(?:-Frag\d+)?|\.ytdl)$/m';

  public function __construct()
  {
    $this->config = require dirname(__DIR__) . '/config/config.php';
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function listFiles(): array
  {
    $files = [];

    $folder = $this->get_downloads_folder() . '/';

    foreach (glob($folder . '*.*') as $file) {
      $content = [];
      $content["name"] = str_replace($folder, "", $file);
      $content["size"] = $this->to_human_filesize(filesize($file));

      if (preg_match($this->re_partial, $content["name"]) === 0) {
        $files[] = $content;
      }
    }

    return $files;
  }

  /** @return array<int<0,max>, array<string, string>> */
  public function listParts(): array
  {
    $files = [];

    $folder = $this->get_downloads_folder() . '/';

    foreach (glob($folder . '*.*') as $file) {
      $content = [];
      $content["name"] = str_replace($folder, "", $file);
      $content["size"] = $this->to_human_filesize(filesize($file));

      if (preg_match($this->re_partial, $content["name"]) !== 0) {
        $files[] = $content;
      }
    }

    return $files;
  }

  public function is_log_enabled(): bool
  {
    return !!($this->config["log"]);
  }

  public function countLogs(): int
  {
    if (!$this->config["log"]) {
      return 0;
    }

    if (!$this->logs_folder_exists()) {
      return 0;
    }

    $folder = $this->get_logs_folder() . '/';
    return count(glob($folder . '*.txt'));
  }

  /**
   * @return array<int<0, max>, array<string|int, mixed>>
   */
  public function listLogs(): array
  {
    $files = [];

    if (!$this->config["log"]) {
      return $files;
    }

    if (!$this->logs_folder_exists()) {
      return $files;
    }

    $folder = $this->get_logs_folder() . '/';

    foreach (glob($folder . '*.txt') as $file) {
      $content = [];
      $content["name"] = str_replace($folder, "", $file);
      $content["size"] = $this->to_human_filesize(filesize($file));

      try {
        $lines = explode("\r", file_get_contents($file));
        $content["lastline"] = array_slice($lines, -1)[0];
        $content["100"] = strpos($lines[count($lines) - 1], ' 100% of ') > 0;
      } catch (Throwable) {
        $content["lastline"] = '';
        $content["100"] = false;
      }
      try {
        $handle = fopen($file, 'r');
        fseek($handle, filesize($file) - 1);
        $lastc = fgets($handle);
        fclose($handle);
        $content["ended"] = ($lastc === "\n");
      } catch (Throwable) {
        $content["ended"] = false;
      }


      $files[] = $content;
    }

    return $files;
  }

  public function delete(string $id): void
  {
    $folder = $this->get_downloads_folder() . '/';

    foreach (glob($folder . '*.*') as $file) {
      if (sha1(str_replace($folder, "", $file)) == $id) {
        unlink($file);
      }
    }
  }

  public function deleteLog(string $id): void
  {
    $folder = $this->get_logs_folder() . '/';

    foreach (glob($folder . '*.txt') as $file) {
      if (sha1(str_replace($folder, "", $file)) == $id) {
        unlink($file);
      }
    }
  }

  public function to_human_filesize(float $bytes, int $decimals = 1): string
  {
    return sprintf("%.{$decimals}f", $bytes / 1024);
  }

  public function free_space(): string
  {
    return $this->to_human_filesize((float)disk_free_space(realpath($this->get_downloads_folder())));
  }

  public function used_space(): string
  {
    $path = realpath($this->get_downloads_folder());
    $bytestotal = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
      $bytestotal += $object->getSize();
    }
    return $this->to_human_filesize($bytestotal);
  }

  public function get_downloads_folder(): string
  {
    $path =  $this->config["outputFolder"];
    if (strpos($path, "/") !== 0) {
      $path = dirname(__DIR__) . '/' . $path;
    }
    return $path;
  }

  public function get_logs_folder(): string
  {
    $path =  $this->config["logFolder"];
    if (strpos($path, "/") !== 0) {
      $path = dirname(__DIR__) . '/' . $path;
    }
    return $path;
  }

  public function get_relative_downloads_folder(): string
  {
    $path =  $this->config["outputFolder"];
    if (strpos($path, "/") !== 0) {
      return $this->config["outputFolder"];
    }

    throw new RuntimeException('$config[outputFolder] not set correctly');
  }

  public function get_relative_log_folder(): string
  {
    $path =  $this->config["logFolder"];
    if (strpos($path, "/") !== 0) {
      return $this->config["logFolder"];;
    }
    throw new RuntimeException('$config[logFolder] not set correctly');
  }

  private function logs_folder_exists(): bool
  {
    if (!is_dir($this->get_logs_folder())) {
      //Folder doesn't exist
      if (!mkdir($this->get_logs_folder(), 0777)) {
        return false; //No folder and creation failed
      }
    }

    return true;
  }
}

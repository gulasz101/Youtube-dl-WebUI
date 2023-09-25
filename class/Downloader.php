<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Process\Process;

use function StrictHelpers\glob;

class Downloader
{
    /** @var array <int, string> */
    private array $urls = [];
    /** @var array<string, mixed> */
    private array $config = [];
    /** @var array<int, string> */
    private array $errors = [];
    private string $download_path = "";
    private string $log_path = "";
    private string $outfilename = "%(title)s-%(id)s.%(ext)s";
    private string $vformat = 'mp4';

    public function __construct(string $post)
    {
        $this->config = require dirname(__DIR__) . '/config/config.php';
        $fh = new FileHandler();
        $this->download_path = $fh->get_downloads_folder();

        if ($this->config["log"]) {
            $this->log_path = $fh->get_logs_folder();
        }

        if ($this->config["outfilename"]) {
            $this->outfilename = $this->config["outfilename"];
        }

        $this->urls = preg_split("/[\s,]+/", trim($post)) ?: [];

        if (!$this->check_requirements()) {
            return;
        }

        foreach ($this->urls as $url) {
            if (!$this->is_valid_url($url)) {
                $this->errors[] = "\"" . $url . "\" is not a valid url !";
            }
        }

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
            return;
        }
    }

    public function download(bool $audio_only, bool $outfilename = false, string|null $vformat = null): void
    {
        if ($audio_only && !$this->check_requirements($audio_only)) {
            return;
        }

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
            return;
        }

        // TODO: check what is that for
        /* if ($outfilename) { */
        /*     $this->outfilename = $outfilename; */
        /* } */
        if ($vformat) {
            $this->vformat = $vformat;
        }

        if ($this->config["max_dl"] == 0) {
            $this->do_download($audio_only);
        } elseif ($this->config["max_dl"] > 0) {
            if ($this->background_jobs() >= 0 && $this->background_jobs() < $this->config["max_dl"]) {
                $this->do_download($audio_only);
            } else {
                $this->errors[] = "Simultaneous downloads limit reached !";
            }
        }

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
            return;
        }
    }

    public function info(): string
    {
        $info = $this->do_info();

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
        }

        return $info;
    }

    public static function background_jobs(): string|false|null
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        return shell_exec("ps aux | grep -v grep | grep -v \"" . $config["bin"] . " -U\" | grep \"" . $config["bin"] . " \" | wc -l");
    }

    public static function max_background_jobs(): int
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        return $config["max_dl"];
    }

    /** @return array<int, array<string, string>> */
    public static function get_current_background_jobs(): array
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        $process = new Process([              "ps",              "-A",    "-o","user,pid,etime,comm",]);
        $process->mustRun();

        $output = explode(PHP_EOL, $process->getOutput());
        $downloaderRealName = explode('/', $config['bin']);
        $downloaderRealName = end($downloaderRealName);
        $output = array_filter($output, fn (string $line): bool => str_contains($line, $downloaderRealName));

        $bjs = [];

        foreach ($output as $line) {
            $line = explode(' ', preg_replace("/ +/", " ", $line) ?: '', 4);
            $bjs[] = array(
              'user' => $line[0],
              'pid' => $line[1],
              'time' => $line[2],
              'cmd' => $line[3]
            );
        }

        return $bjs;
    }

    public static function kill_them_all(): void
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        exec("ps -A -o pid,cmd | grep -v grep | grep -v \"" . $config["bin"] . " -U\" | grep \"" . $config["bin"] . " \" | awk '{print $1}'", $output);

        if (count($output) <= 0) {
            return;
        }

        foreach ($output as $p) {
            shell_exec("kill " . $p);
        }

        $fh = new FileHandler();
        $folder = $fh->get_downloads_folder();

        foreach (glob($folder . '*.part') as $file) {
            unlink($file);
        }
    }

    private function check_requirements(bool $audio_only = false): bool
    {
        if ($this->is_youtubedl_installed() != true) {
            $this->errors[] = "Binary not found in <code>" . $this->config["bin"] . "</code>, see <a href='https://github.com/yt-dlp/yt-dlp'>yt-dlp site</a> !";
        }

        $this->check_outuput_folder();

        if ($audio_only) {
            if ($this->is_extracter_installed() != 0) {
                $this->errors[] = "Install an audio extracter (ex: ffmpeg) !";
            }
        }

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
            return false;
        }

        return true;
    }

    private function is_youtubedl_installed(): bool
    {
        (new Process([$this->config['bin'], '--version']))->mustRun();

        return true;
    }

    public static function get_youtubedl_version(): string
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        $output = ( new Process([$config['bin'], '--version']) )->mustRun()->getOutput();
        return trim($output);
    }

    private function is_extracter_installed(): bool
    {
        return (new Process(['which', $this->config['extracter']]))->run() === 0;
    }

    private function is_valid_url(string $url): mixed
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    private function check_outuput_folder(): void
    {
        if (!is_dir($this->download_path)) {
            //Folder doesn't exist
            if (!mkdir($this->download_path, 0775)) {
                $this->errors[] = "Output folder doesn't exist and creation failed! (" . $this->download_path . ")";
            }
        } else {
            //Exists but can I write ?
            if (!is_writable($this->download_path)) {
                $this->errors[] = "Output folder isn't writable! (" . $this->download_path . ")";
            }
        }

        // LOG folder
        if ($this->config["log"]) {
            if (!is_dir($this->log_path)) {
                //Folder doesn't exist
                if (!mkdir($this->log_path, 0775)) {
                    $this->errors[] = "Log folder doesn't exist and creation failed! (" . $this->log_path . ")";
                }
            } else {
                //Exists but can I write ?
                if (!is_writable($this->log_path)) {
                    $this->errors[] = "Log folder isn't writable! (" . $this->log_path . ")";
                }
            }
        }
    }

    private function do_download(bool $audio_only): void
    {
        $args = [
          $this->config['bin'],
          '--restrict-filenames',
          '--ignore-error',
          '-o',
          $this->download_path . '/'.
          $this->outfilename,
        ];

        if ($this->vformat) {
            $args[] = '--format';
            $args[] = $this->vformat;
        }
        if ($audio_only) {
            $args[] = '-x';
        }
        foreach ($this->urls as $url) {
            $args[] = $url;
        }

        $process = (new Process($args));
        if ($this->config["log"]) {
            $logFilePath = $this->log_path . '/' . date('Y-m-d_H-i-s') . '.txt';
            file_put_contents($logFilePath, implode(' ', $args), FILE_APPEND);
            $process->run(fn ($type, $buffer) => file_put_contents($logFilePath, $buffer, FILE_APPEND));
        } else {
            $process->start();
        }
    }

    private function do_info(): string
    {
        $cmd = new Process([
              $this->config['bin'],
            '-J',
            ...$this->urls,
        ]);
        $cmd->mustRun();

        $output = json_decode($cmd->getOutput());

        if (!$output) {
            $this->errors[] = "No video found";
        }
        return json_encode(value: $output, flags: JSON_PRETTY_PRINT);
    }
}

<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

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
    private string $quality = '';
    private ?JobManager $jobManager = null;
    private ?LoggerInterface $logger = null;

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

    /**
     * Initialize JobManager and Logger (for async operations)
     */
    public function initAsync(): void
    {
        $this->jobManager = new JobManager();
        $this->logger = AppLogger::create();
    }

    /**
     * Set quality preference
     */
    public function setQuality(string $quality): void
    {
        $this->quality = $quality;
    }

    /**
     * Download - creates jobs and launches them asynchronously if JobManager is initialized
     * Falls back to synchronous mode if JobManager is not initialized (for backward compatibility)
     *
     * @return array Array of job IDs if async, empty array if sync
     */
    public function download(bool $audio_only, bool $outfilename = false, string|null $vformat = null): array
    {
        if ($audio_only && !$this->check_requirements($audio_only)) {
            return [];
        }

        if (isset($this->errors) && count($this->errors) > 0) {
            $_SESSION['errors'] = $this->errors;
            return [];
        }

        if ($vformat) {
            $this->vformat = $vformat;
        }

        // Check if async mode is enabled (JobManager initialized)
        if ($this->jobManager !== null) {
            return $this->downloadAsync($audio_only);
        }

        // Fallback to old synchronous behavior
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
        }

        return [];
    }

    /**
     * Async download - creates jobs and launches coroutines
     */
    private function downloadAsync(bool $audio_only): array
    {
        $jobIds = [];

        // Check max concurrent downloads
        if ($this->config['max_dl'] > 0) {
            $activeCount = $this->jobManager->getActiveJobCount();
            if ($activeCount >= $this->config['max_dl']) {
                $this->errors[] = "Simultaneous downloads limit reached ({$this->config['max_dl']})!";
                $_SESSION['errors'] = $this->errors;
                return [];
            }
        }

        // Create a job for each URL
        foreach ($this->urls as $url) {
            $jobId = $this->jobManager->createJob($url, [
                'format' => $this->vformat,
                'quality' => $this->quality,
                'audio_only' => $audio_only ? 1 : 0,
                'status' => 'queued'
            ]);

            $jobIds[] = $jobId;

            $this->logger->info('Job created', [
                'job_id' => $jobId,
                'url' => $url,
                'audio_only' => $audio_only
            ]);

            // Launch async download in coroutine
            go(function() use ($jobId, $url, $audio_only) {
                $this->doAsyncDownload($jobId, $url, $audio_only);
            });
        }

        return $jobIds;
    }

    /**
     * Async download execution in coroutine with progress parsing
     */
    private function doAsyncDownload(string $jobId, string $url, bool $audio_only): void
    {
        $this->jobManager->updateJob($jobId, ['status' => 'downloading']);
        $this->logger->info('Download started', ['job_id' => $jobId]);

        try {
            $args = $this->buildYtdlpCommand($url, $audio_only);
            $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';

            // Use proc_open for streaming output
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ];

            $process = proc_open($cmd, $descriptors, $pipes);

            if (!is_resource($process)) {
                throw new \Exception('Failed to start download process');
            }

            // Close stdin
            fclose($pipes[0]);

            // Set stdout to non-blocking
            stream_set_blocking($pipes[1], false);

            $output = '';
            $lastProgress = 0.0;

            // Read output in non-blocking mode
            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);

                if ($line === false) {
                    \Swoole\Coroutine::sleep(0.1);
                    continue;
                }

                $output .= $line;

                // Parse progress from yt-dlp output
                // Format: [download]  45.6% of 123.45MiB at 1.23MiB/s ETA 00:12
                if (preg_match('/\[download\]\s+(\d+\.?\d*)%/', $line, $matches)) {
                    $progress = (float)$matches[1];

                    // Only update if progress changed by at least 1%
                    if (abs($progress - $lastProgress) >= 1.0) {
                        $this->jobManager->updateJob($jobId, ['progress' => $progress]);
                        $lastProgress = $progress;
                    }
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode === 0) {
                $this->jobManager->updateJob($jobId, [
                    'status' => 'completed',
                    'progress' => 100.0,
                    'end_time' => time()
                ]);
                $this->logger->info('Download completed', ['job_id' => $jobId]);
            } else {
                throw new \Exception("yt-dlp exited with code $exitCode");
            }

        } catch (\Throwable $e) {
            $this->jobManager->updateJob($jobId, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'end_time' => time()
            ]);
            $this->logger->error('Download failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Build yt-dlp command arguments
     */
    private function buildYtdlpCommand(string $url, bool $audio_only): array
    {
        $args = [
            $this->config['bin'],
            '--restrict-filenames',
            '--ignore-errors',
            '-o',
            $this->download_path . '/' . $this->outfilename,
        ];

        // Format selection
        if ($this->vformat && $this->vformat !== 'mp4') {
            $args[] = '--format';
            $args[] = $this->vformat;
        } elseif ($this->quality) {
            $args[] = '--format';
            $args[] = $this->buildFormatString($this->quality, $audio_only);
        } else {
            // Default format
            $args[] = '--format';
            $args[] = $audio_only ? 'bestaudio' : 'bestvideo+bestaudio/best';
        }

        if ($audio_only) {
            $args[] = '-x';
        }

        $args[] = $url;

        return $args;
    }

    /**
     * Build format string based on quality preference
     */
    private function buildFormatString(string $quality, bool $audio_only): string
    {
        if ($audio_only) {
            return 'bestaudio';
        }

        return match($quality) {
            '1080p' => 'bestvideo[height<=1080]+bestaudio/best[height<=1080]',
            '720p' => 'bestvideo[height<=720]+bestaudio/best[height<=720]',
            '480p' => 'bestvideo[height<=480]+bestaudio/best[height<=480]',
            '360p' => 'bestvideo[height<=360]+bestaudio/best[height<=360]',
            'worst' => 'worstvideo+worstaudio/worst',
            default => 'bestvideo+bestaudio/best'
        };
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

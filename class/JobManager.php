<?php

declare(strict_types=1);

namespace App\Utils;

use Swoole\Table;

/**
 * JobManager - Manages download jobs using Swoole\Table for shared memory across workers
 */
class JobManager
{
    private static ?Table $table = null;
    private static bool $initialized = false;

    public function __construct()
    {
        if (!self::$initialized) {
            $this->initializeTable();
        }
    }

    /**
     * Initialize the shared memory table
     */
    private function initializeTable(): void
    {
        if (self::$table !== null) {
            return;
        }

        self::$table = new Table(1024);
        self::$table->column('url', Table::TYPE_STRING, 512);
        self::$table->column('status', Table::TYPE_STRING, 20); // queued, downloading, completed, failed
        self::$table->column('progress', Table::TYPE_FLOAT);
        self::$table->column('format', Table::TYPE_STRING, 50);
        self::$table->column('quality', Table::TYPE_STRING, 20);
        self::$table->column('start_time', Table::TYPE_INT);
        self::$table->column('end_time', Table::TYPE_INT);
        self::$table->column('error', Table::TYPE_STRING, 500);
        self::$table->column('audio_only', Table::TYPE_INT); // 0 or 1
        self::$table->create();

        self::$initialized = true;
    }

    /**
     * Create a new job
     *
     * @param string $url The URL to download
     * @param array $options Job options (format, quality, audio_only, etc.)
     * @return string The generated job ID
     */
    public function createJob(string $url, array $options = []): string
    {
        $jobId = $this->generateJobId();

        $data = [
            'url' => $url,
            'status' => $options['status'] ?? 'queued',
            'progress' => 0.0,
            'format' => $options['format'] ?? '',
            'quality' => $options['quality'] ?? '',
            'start_time' => time(),
            'end_time' => 0,
            'error' => '',
            'audio_only' => $options['audio_only'] ?? 0,
        ];

        self::$table->set($jobId, $data);

        return $jobId;
    }

    /**
     * Update an existing job
     *
     * @param string $jobId The job ID
     * @param array $data Data to update
     */
    public function updateJob(string $jobId, array $data): void
    {
        $existing = self::$table->get($jobId);
        if ($existing === false) {
            return;
        }

        $updated = array_merge($existing, $data);
        self::$table->set($jobId, $updated);
    }

    /**
     * Get a job by ID
     *
     * @param string $jobId The job ID
     * @return array|null Job data or null if not found
     */
    public function getJob(string $jobId): ?array
    {
        $job = self::$table->get($jobId);
        if ($job === false) {
            return null;
        }

        $job['id'] = $jobId;
        return $job;
    }

    /**
     * Get all active jobs (queued or downloading)
     *
     * @return array Array of active jobs
     */
    public function getActiveJobs(): array
    {
        $activeJobs = [];

        foreach (self::$table as $jobId => $job) {
            if (in_array($job['status'], ['queued', 'downloading'])) {
                $job['id'] = $jobId;
                $activeJobs[] = $job;
            }
        }

        return $activeJobs;
    }

    /**
     * Get completed jobs (limited to recent ones)
     *
     * @param int $limit Maximum number of jobs to return
     * @return array Array of completed jobs
     */
    public function getCompletedJobs(int $limit = 10): array
    {
        $completedJobs = [];

        foreach (self::$table as $jobId => $job) {
            if ($job['status'] === 'completed') {
                $job['id'] = $jobId;
                $completedJobs[] = $job;
            }
        }

        // Sort by end_time descending
        usort($completedJobs, function($a, $b) {
            return $b['end_time'] <=> $a['end_time'];
        });

        return array_slice($completedJobs, 0, $limit);
    }

    /**
     * Get failed jobs
     *
     * @param int $limit Maximum number of jobs to return
     * @return array Array of failed jobs
     */
    public function getFailedJobs(int $limit = 10): array
    {
        $failedJobs = [];

        foreach (self::$table as $jobId => $job) {
            if ($job['status'] === 'failed') {
                $job['id'] = $jobId;
                $failedJobs[] = $job;
            }
        }

        // Sort by end_time descending
        usort($failedJobs, function($a, $b) {
            return $b['end_time'] <=> $a['end_time'];
        });

        return array_slice($failedJobs, 0, $limit);
    }

    /**
     * Delete a job
     *
     * @param string $jobId The job ID
     */
    public function deleteJob(string $jobId): void
    {
        self::$table->del($jobId);
    }

    /**
     * Clean up old jobs (older than TTL)
     *
     * @param int $ttl Time to live in seconds (default: 1 hour)
     */
    public function cleanupOldJobs(int $ttl = 3600): void
    {
        $now = time();

        foreach (self::$table as $jobId => $job) {
            // Only cleanup completed or failed jobs
            if (in_array($job['status'], ['completed', 'failed'])) {
                if ($job['end_time'] > 0 && ($now - $job['end_time']) > $ttl) {
                    self::$table->del($jobId);
                }
            }
        }
    }

    /**
     * Get count of active jobs
     *
     * @return int Number of active jobs
     */
    public function getActiveJobCount(): int
    {
        return count($this->getActiveJobs());
    }

    /**
     * Generate a unique job ID
     *
     * @return string Job ID
     */
    private function generateJobId(): string
    {
        return uniqid('job_', true);
    }

    /**
     * Get the table instance (for use in server initialization)
     *
     * @return Table|null
     */
    public static function getTable(): ?Table
    {
        return self::$table;
    }
}

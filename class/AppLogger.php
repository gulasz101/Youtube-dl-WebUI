<?php

declare(strict_types=1);

namespace App\Utils;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

/**
 * AppLogger - Structured logging using Monolog
 */
class AppLogger
{
    private static ?Logger $instance = null;

    /**
     * Create or get the logger instance
     *
     * @return Logger
     */
    public static function create(): Logger
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $logger = new Logger('yt-dlp-webui');

        // Ensure logs directory exists
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        // Create stream handler for application logs (JSON format)
        $fileStream = new StreamHandler($logsDir . '/app.log', Logger::INFO);
        $fileStream->setFormatter(new JsonFormatter());
        $logger->pushHandler($fileStream);

        // Add stdout handler for Docker console (plain text format)
        $stdoutStream = new StreamHandler('php://stdout', Logger::INFO);
        $logger->pushHandler($stdoutStream);

        self::$instance = $logger;

        return $logger;
    }

    /**
     * Reset the logger instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

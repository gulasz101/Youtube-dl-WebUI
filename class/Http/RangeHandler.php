<?php

declare(strict_types=1);

namespace App\Utils\Http;

/**
 * RangeHandler - Handles HTTP range requests for video/audio streaming
 */
class RangeHandler
{
    /**
     * Parse Range header
     *
     * @param string $rangeHeader The Range header value (e.g., "bytes=0-1024")
     * @param int $fileSize Total file size
     * @return array|null Array with 'start' and 'end' keys, or null if invalid
     */
    public static function parseRange(string $rangeHeader, int $fileSize): ?array
    {
        // Parse "bytes=start-end"
        if (!preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            return null;
        }

        $start = (int)$matches[1];
        $end = !empty($matches[2]) ? (int)$matches[2] : ($fileSize - 1);

        // Validate range
        if ($start > $end || $start >= $fileSize) {
            return null;
        }

        // Ensure end doesn't exceed file size
        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Read a chunk of file
     *
     * @param string $filePath Path to the file
     * @param int $start Start byte position
     * @param int $end End byte position
     * @return string File chunk data
     */
    public static function readChunk(string $filePath, int $start, int $end): string
    {
        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            throw new \RuntimeException("Failed to open file: {$filePath}");
        }

        fseek($fp, $start);
        $length = $end - $start + 1;
        $data = fread($fp, $length);
        fclose($fp);

        if ($data === false) {
            throw new \RuntimeException("Failed to read file chunk");
        }

        return $data;
    }
}

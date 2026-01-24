<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\HTTP\Server;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

use function StrictHelpers\file_get_contents;

/**
 * Convert PSR-7 Response to Swoole Response
 */
function sendPsr7Response($psrResponse, Response $swooleResponse): void
{
    // Handle SwooleResponse objects
    if ($psrResponse instanceof \App\Utils\Http\SwooleResponse) {
        $psrResponse->send($swooleResponse);
        return;
    }

    // Handle PSR-7 Response objects (Nyholm\Psr7\Response)
    if ($psrResponse instanceof \Psr\Http\Message\ResponseInterface) {
        // Set status code
        $swooleResponse->status($psrResponse->getStatusCode());

        // Set headers
        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        // Send body
        $swooleResponse->end((string)$psrResponse->getBody());
        return;
    }

    // Fallback
    $swooleResponse->status(500);
    $swooleResponse->end('Invalid response type');
}

// MIME type helper function (same as app.php)
function getMimeType(string $path): string {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($extension) {
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        default => 'text/plain',
    };
}

$config = require __DIR__ . '/config/config.php';

$server = new Server("0.0.0.0", 8080);

$server->set([
    'worker_num' => 4,                      // 4 workers for better concurrency
    'daemonize' => false,
    'max_request' => 1000,                  // Restart worker after 1000 requests
    'dispatch_mode' => 2,                   // Fixed dispatch by FD
    'log_level' => SWOOLE_LOG_INFO,
    'enable_coroutine' => true,
    'max_coroutine' => 10000,               // Max concurrent coroutines
    'buffer_output_size' => 32 * 1024 * 1024, // 32MB buffer for large responses
    'package_max_length' => 20 * 1024 * 1024, // 20MB max package size
]);

// Initialize JobManager and Logger in server context
$jobManager = new \App\Utils\JobManager();
$logger = \App\Utils\AppLogger::create();

// Add WorkerError event handler
$server->on('WorkerError', function($server, $workerId, $workerPid, $exitCode, $signal) use ($logger) {
    $logger->error('Worker error', [
        'worker_id' => $workerId,
        'pid' => $workerPid,
        'exit_code' => $exitCode,
        'signal' => $signal
    ]);
});

// Add WorkerStart event handler
$server->on('WorkerStart', function($server, $workerId) use ($logger) {
    $logger->info('Worker started', [
        'worker_id' => $workerId,
        'pid' => $server->worker_pid
    ]);
});

$server->on('Request', function(Request $request, Response $response) use ($config, $jobManager, $logger) {
    try {
        $path = $request->server['request_uri'];

        // Remove query string from path
        $path = parse_url($path, PHP_URL_PATH);

        // API routes (handle before PSR-7 adapter)
        if ($path === '/api/jobs') {
            $response->header('Content-Type', 'application/json');

            $jobs = $jobManager->getActiveJobs();
            $completed = $jobManager->getCompletedJobs(5);

            $response->end(json_encode([
                'active' => $jobs,
                'active_count' => count($jobs),
                'completed' => $completed,
                'max_concurrent' => $config['max_dl']
            ]));
            return;
        }

        // SSE endpoint for real-time job updates
        if ($path === '/api/jobs/stream') {
            $response->header('Content-Type', 'text/event-stream');
            $response->header('Cache-Control', 'no-cache');
            $response->header('Connection', 'keep-alive');
            $response->header('X-Accel-Buffering', 'no');

            // Send initial connection message
            $response->write("data: " . json_encode(['type' => 'connected']) . "\n\n");

            // Keep connection alive and send updates every second
            $count = 0;
            $maxUpdates = 300; // 5 minutes max

            while ($count < $maxUpdates) {
                try {
                    $jobs = $jobManager->getActiveJobs();
                    $data = [
                        'type' => 'update',
                        'active' => $jobs,
                        'active_count' => count($jobs),
                        'timestamp' => time()
                    ];

                    $response->write("data: " . json_encode($data) . "\n\n");

                    // Sleep for 1 second
                    \Swoole\Coroutine::sleep(1);
                    $count++;

                } catch (\Throwable $e) {
                    // Connection closed or error
                    break;
                }
            }

            $response->end();
            return;
        }

        if ($path === '/api/formats' && $request->server['request_method'] === 'POST') {
            $postData = json_decode($request->rawContent(), true);
            $url = $postData['url'] ?? '';

            if (empty($url)) {
                $response->status(400);
                $response->header('Content-Type', 'application/json');
                $response->end(json_encode(['error' => 'URL required']));
                return;
            }

            // Create job for format fetching
            $jobId = $jobManager->createJob($url, [
                'status' => 'fetching_formats',
                'progress' => 0.0,
                'type' => 'format_fetch'
            ]);

            $logger->info('Format fetch job created', ['job_id' => $jobId, 'url' => $url]);

            // Execute yt-dlp -J in coroutine to get formats
            go(function() use ($url, $response, $config, $jobManager, $logger, $jobId) {
                try {
                    $jobManager->updateJob($jobId, ['status' => 'fetching_formats', 'progress' => 50.0]);

                    $cmd = escapeshellarg($config['bin']) . ' -J ' . escapeshellarg($url) . ' 2>&1';
                    $result = \Swoole\Coroutine\System::exec($cmd);

                    if ($result['code'] === 0) {
                        $data = json_decode($result['output'], true);

                        if (!$data) {
                            $jobManager->updateJob($jobId, [
                                'status' => 'failed',
                                'error' => 'Failed to parse video info',
                                'end_time' => time()
                            ]);
                            $logger->error('Format fetch failed - parse error', ['job_id' => $jobId]);

                            $response->status(500);
                            $response->header('Content-Type', 'application/json');
                            $response->end(json_encode(['error' => 'Failed to parse video info']));
                            return;
                        }

                        $formats = $data['formats'] ?? [];

                        // Filter video and audio formats
                        $videoFormats = array_filter($formats, function($f) {
                            return isset($f['vcodec']) && $f['vcodec'] !== 'none';
                        });

                        $audioFormats = array_filter($formats, function($f) {
                            return isset($f['acodec']) && $f['acodec'] !== 'none' &&
                                   (!isset($f['vcodec']) || $f['vcodec'] === 'none');
                        });

                        // Mark job as completed
                        $jobManager->updateJob($jobId, [
                            'status' => 'completed',
                            'progress' => 100.0,
                            'end_time' => time()
                        ]);
                        $logger->info('Format fetch completed', [
                            'job_id' => $jobId,
                            'video_count' => count($videoFormats),
                            'audio_count' => count($audioFormats)
                        ]);

                        $response->header('Content-Type', 'application/json');
                        $response->end(json_encode([
                            'video_formats' => array_values($videoFormats),
                            'audio_formats' => array_values($audioFormats),
                            'title' => $data['title'] ?? 'Unknown',
                            'job_id' => $jobId
                        ]));
                    } else {
                        $jobManager->updateJob($jobId, [
                            'status' => 'failed',
                            'error' => 'Failed to fetch formats: ' . ($result['output'] ?? 'Unknown error'),
                            'end_time' => time()
                        ]);
                        $logger->error('Format fetch failed', [
                            'job_id' => $jobId,
                            'exit_code' => $result['code'],
                            'output' => $result['output'] ?? ''
                        ]);

                        $response->status(500);
                        $response->header('Content-Type', 'application/json');
                        $response->end(json_encode([
                            'error' => 'Failed to fetch formats',
                            'output' => $result['output'] ?? ''
                        ]));
                    }
                } catch (\Throwable $e) {
                    $jobManager->updateJob($jobId, [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'end_time' => time()
                    ]);
                    $logger->error('Format fetch exception', [
                        'job_id' => $jobId,
                        'error' => $e->getMessage()
                    ]);

                    $response->status(500);
                    $response->header('Content-Type', 'application/json');
                    $response->end(json_encode([
                        'error' => 'Exception: ' . $e->getMessage()
                    ]));
                }
            });
            return;
        }

        // Stream route for media playback
        if (preg_match('#^/stream/(.+)$#', $path, $matches)) {
            $filename = urldecode($matches[1]);
            $filePath = __DIR__ . '/' . $config['outputFolder'] . '/' . $filename;

            // Security: prevent directory traversal
            if (strpos($filename, '..') !== false || !file_exists($filePath) || !is_file($filePath)) {
                $response->status(404);
                $response->end('Not Found');
                return;
            }

            $fileSize = filesize($filePath);
            $mimeType = getMimeType($filename);

            // Set headers for browser playback
            $response->header('Accept-Ranges', 'bytes');
            $response->header('Content-Type', $mimeType);
            $response->header('Content-Disposition', 'inline; filename="' . basename($filename) . '"');

            // Handle range request
            $rangeHeader = $request->header['range'] ?? '';

            if ($rangeHeader) {
                $range = \App\Utils\Http\RangeHandler::parseRange($rangeHeader, $fileSize);

                if ($range) {
                    $response->status(206); // Partial Content
                    $response->header('Content-Range', "bytes {$range['start']}-{$range['end']}/{$fileSize}");
                    $response->header('Content-Length', (string)($range['end'] - $range['start'] + 1));

                    try {
                        $data = \App\Utils\Http\RangeHandler::readChunk($filePath, $range['start'], $range['end']);
                        $response->end($data);
                    } catch (\Exception $e) {
                        $response->status(500);
                        $response->end('Error reading file');
                    }
                } else {
                    $response->status(416); // Range Not Satisfiable
                    $response->header('Content-Range', "bytes */{$fileSize}");
                    $response->end();
                }
            } else {
                // Full file
                $response->header('Content-Length', (string)$fileSize);
                $response->sendfile($filePath);
            }

            return;
        }

        // Create request adapter for PSR-7 compatibility
        $psrRequest = new \App\Utils\Http\SwooleRequest($request);

        // Route matching (same as app.php)
        $psrResponse = match ($path) {
            '/', '/index.php' => (function() use ($psrRequest) {
                $request = $psrRequest; // Make available to required file
                return require __DIR__ . '/index.php';
            })(),
            '/info.php' => (function() use ($psrRequest) {
                $request = $psrRequest;
                return require __DIR__ . '/info.php';
            })(),
            '/logs.php' => (function() use ($psrRequest) {
                $request = $psrRequest;
                return require __DIR__ . '/logs.php';
            })(),
            '/list.php' => (function() use ($psrRequest) {
                $request = $psrRequest;
                return require __DIR__ . '/list.php';
            })(),
            '/favicon.ico' => new \App\Utils\Http\SwooleResponse(
                200,
                ['Content-Type' => 'image/x-icon'],
                file_get_contents(__DIR__ . '/favicon_144.png')
            ),
            default => (function() use ($path) {
                $filePath = __DIR__ . $path;
                if (file_exists($filePath) && is_file($filePath)) {
                    return new \App\Utils\Http\SwooleResponse(
                        200,
                        ['Content-Type' => getMimeType($path)],
                        file_get_contents($filePath)
                    );
                }
                return new \App\Utils\Http\SwooleResponse(404, ['Content-Type' => 'text/plain'], 'Not Found');
            })(),
        };

        // Send response
        sendPsr7Response($psrResponse, $response);

    } catch (\Throwable $e) {
        $logger->error('Request error', [
            'path' => $request->server['request_uri'] ?? 'unknown',
            'method' => $request->server['request_method'] ?? 'unknown',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        $response->status(500);
        $response->header('Content-Type', 'text/plain');
        $response->end('Internal Server Error: ' . $e->getMessage());
    }
});

echo "Swoole server starting on http://0.0.0.0:8080\n";
$server->start();

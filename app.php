<?php

require_once __DIR__ . "/vendor/autoload.php";


use Nyholm\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;

use function StrictHelpers\file_get_contents;

// Create new RoadRunner worker from global environment
$worker = Worker::create();

// Create common PSR-17 HTTP factory
$factory = new Psr17Factory();

$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break;
        }
    } catch (\Throwable $e) {
        // Although the PSR-17 specification clearly states that there can be
        // no exceptions when creating a request, however, some implementations
        // may violate this rule. Therefore, it is recommended to process the
        // incoming request for errors.
        //
        // Send "Bad Request" response.
        $psr7->respond(new Response(400));
        continue;
    }

    try {
        // Here is where the call to your application code will be located.
        // For example:
        //  $response = $app->send($request);
        //
        // Reply by the 200 OK response

        $response = match ($request->getUri()->getPath()) {
            '/', '/index.php' => require __DIR__ . '/index.php',
            '/login.php' => require __DIR__ . '/login.php',
            '/logout.php' => require __DIR__ . '/logout.php',
            '/info.php' => require __DIR__ . '/info.php',
            '/logs.php' => require __DIR__ . '/logs.php',
            '/list.php' => require __DIR__ . '/list.php',
            '/favicon.ico' => new Response(body: file_get_contents(__DIR__ . '/favicon_144.png')),
            default => new Response(body: file_get_contents(__DIR__ . $request->getUri()->getPath())),
        };

        $psr7->respond($response);
    } catch (\Throwable $e) {
        // In case of any exceptions in the application code, you should handle
        // them and inform the client about the presence of a server error.
        //
        // Reply by the 500 Internal Server Error response
        /* $psr7->respond(new Response(500, [], 'Something Went Wrong!')); */
        $psr7->respond(new Response(500, [], $e->getMessage() . PHP_EOL . PHP_EOL . $e->getTraceAsString()));

        // Additionally, we can inform the RoadRunner that the processing
        // of the request failed.
        $psr7->getWorker()->error((string)$e);
    }
}

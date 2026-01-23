<?php

declare(strict_types=1);

namespace App\Utils\Http;

/**
 * Response wrapper that can send data to Swoole\HTTP\Response
 * Mimics PSR-7 Response structure for compatibility with existing code
 */
class SwooleResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;

    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Send the response to the Swoole HTTP Response object
     */
    public function send(\Swoole\HTTP\Response $swooleResponse): void
    {
        // Set status code
        $swooleResponse->status($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        // Send body
        $swooleResponse->end($this->body);
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get body
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Create response with different status code
     */
    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        return $new;
    }

    /**
     * Create response with added header
     */
    public function withHeader(string $name, $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    /**
     * Create response with different body
     */
    public function withBody(string $body): self
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}

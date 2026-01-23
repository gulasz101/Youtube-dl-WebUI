<?php

declare(strict_types=1);

namespace App\Utils\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Adapter class to make Swoole\HTTP\Request compatible with PSR-7 ServerRequestInterface
 * This allows existing page handlers to work without modifications
 */
class SwooleRequest implements ServerRequestInterface
{
    private \Swoole\HTTP\Request $swooleRequest;
    private ?array $parsedBody = null;
    private ?SwooleUri $uri = null;

    public function __construct(\Swoole\HTTP\Request $request)
    {
        $this->swooleRequest = $request;
    }

    public function getServerParams(): array
    {
        return $this->swooleRequest->server ?? [];
    }

    public function getCookieParams(): array
    {
        return $this->swooleRequest->cookie ?? [];
    }

    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->swooleRequest->cookie = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->swooleRequest->get ?? [];
    }

    public function withQueryParams(array $query): static
    {
        $new = clone $this;
        $new->swooleRequest->get = $query;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->swooleRequest->files ?? [];
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $new = clone $this;
        $new->swooleRequest->files = $uploadedFiles;
        return $new;
    }

    public function getParsedBody(): mixed
    {
        if ($this->parsedBody !== null) {
            return $this->parsedBody;
        }

        // Get POST data if available
        if (isset($this->swooleRequest->post) && !empty($this->swooleRequest->post)) {
            $this->parsedBody = $this->swooleRequest->post;
            return $this->parsedBody;
        }

        // Try to parse JSON from raw content
        $contentType = $this->swooleRequest->header['content-type'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawContent = $this->swooleRequest->rawContent();
            if ($rawContent) {
                $this->parsedBody = json_decode($rawContent, true);
                return $this->parsedBody;
            }
        }

        return null;
    }

    public function withParsedBody($data): static
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $default;
    }

    public function withAttribute(string $name, $value): static
    {
        return $this;
    }

    public function withoutAttribute(string $name): static
    {
        return $this;
    }

    public function getRequestTarget(): string
    {
        return $this->swooleRequest->server['request_uri'] ?? '/';
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return strtoupper($this->swooleRequest->server['request_method'] ?? 'GET');
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        if ($this->uri === null) {
            $this->uri = new SwooleUri($this->swooleRequest);
        }
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->swooleRequest->header ?? [];
    }

    public function hasHeader(string $name): bool
    {
        $name = strtolower($name);
        return isset($this->swooleRequest->header[$name]);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        $value = $this->swooleRequest->header[$name] ?? null;
        return $value ? [$value] : [];
    }

    public function getHeaderLine(string $name): string
    {
        $name = strtolower($name);
        return $this->swooleRequest->header[$name] ?? '';
    }

    public function withHeader(string $name, $value): static
    {
        return $this;
    }

    public function withAddedHeader(string $name, $value): static
    {
        return $this;
    }

    public function withoutHeader(string $name): static
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        throw new \RuntimeException('Not implemented');
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Utils\Http;

use Psr\Http\Message\UriInterface;

/**
 * Simple URI implementation for Swoole requests
 */
class SwooleUri implements UriInterface
{
    private string $scheme = 'http';
    private string $host = '';
    private ?int $port = null;
    private string $path = '/';
    private string $query = '';
    private string $fragment = '';
    private string $userInfo = '';

    public function __construct(\Swoole\HTTP\Request $request)
    {
        $server = $request->server;

        $this->scheme = ($server['https'] ?? 'off') === 'on' ? 'https' : 'http';
        $this->host = $server['server_name'] ?? $server['http_host'] ?? 'localhost';
        $this->port = $server['server_port'] ?? null;
        $this->path = $server['request_uri'] ?? '/';

        // Parse query string from path
        if (str_contains($this->path, '?')) {
            [$this->path, $this->query] = explode('?', $this->path, 2);
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $new = clone $this;
        $new->scheme = $scheme;
        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $new = clone $this;
        $new->userInfo = $user . ($password ? ':' . $password : '');
        return $new;
    }

    public function withHost(string $host): static
    {
        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    public function withPort(?int $port): static
    {
        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    public function withPath(string $path): static
    {
        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    public function withQuery(string $query): static
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function withFragment(string $fragment): static
    {
        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '' || $this->scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Core;

/** The incoming HTTP request. */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $files = [],
        private readonly array $cookies = [],
        private readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_COOKIE,
            $_SERVER,
        );
    }

    /** True for GET and HEAD, the methods that must not change state. */
    public function isSafe(): bool
    {
        return $this->method === 'GET' || $this->method === 'HEAD';
    }

    /** A query-string value as text; empty when missing or not a plain string. */
    public function query(string $key): string
    {
        $v = $this->query[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    /** A form field as text (untrimmed); empty when missing or not a plain string. */
    public function post(string $key): string
    {
        $v = $this->post[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    /**
     * A repeated form field (name[]), in submission order.
     *
     * @return list<string>
     */
    public function postList(string $key): array
    {
        $v = $this->post[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_map(fn (mixed $x): string => is_string($x) ? $x : '', $v));
    }

    /** @return array<string, mixed> */
    public function postAll(): array
    {
        return $this->post;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function cookie(string $name): string
    {
        $v = $this->cookies[$name] ?? '';
        return is_string($v) ? $v : '';
    }

    /** A request header, looked up case-insensitively ("Sec-Fetch-Site"). Empty when absent. */
    public function header(string $name): string
    {
        $key = strtoupper(str_replace('-', '_', $name));
        if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
            return (string) ($this->server[$key] ?? '');
        }
        return (string) ($this->server['HTTP_' . $key] ?? '');
    }

    public function server(string $key): string
    {
        return (string) ($this->server[$key] ?? '');
    }

    /** The Host header, port included. */
    public function host(): string
    {
        return $this->header('Host');
    }

    /** The caller's address, used for login throttling. */
    public function clientAddress(): string
    {
        return $this->server('REMOTE_ADDR');
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $name): string
    {
        return $this->routeParams[$name] ?? '';
    }

    /** The {id} path parameter as a positive integer; null when it is not one. */
    public function routeId(string $name = 'id'): ?int
    {
        $raw = $this->routeParam($name);
        if (preg_match('/^[+-]?\d{1,18}$/D', $raw) !== 1) {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }
}

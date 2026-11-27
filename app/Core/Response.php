<?php

declare(strict_types=1);

namespace Patylook\Core;

/** The HTTP response a handler produces; nothing is sent until send() is called. */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    private ?string $file = null;

    public function __construct(private int $status = 200, private string $body = '')
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($status, $body))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($status, $body))->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return (new self($status, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** A generated image, sent inline and never cached (it is built on demand). */
    public static function png(string $bytes, string $filename): self
    {
        return (new self(200, $bytes))
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return (new self($status))->withHeader('Location', $location);
    }

    /** Streams a file from disk. */
    public static function file(string $path, string $contentType, int $size): self
    {
        $response = (new self(200))->withHeader('Content-Type', $contentType)->withHeader('Content-Length', (string) $size);
        $response->file = $path;
        return $response;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @param array<string, mixed> $options options accepted by setcookie() */
    public function withCookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    /** @return list<array{name: string, value: string, options: array<string, mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /** Sends status, headers, cookies and body. HEAD requests get no body. */
    public function send(bool $headOnly = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        if ($headOnly || $this->status === 204 || $this->status === 304) {
            return;
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}

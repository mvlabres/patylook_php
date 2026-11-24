<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Core\Response;

/** Builds the cookie that carries the raw session token. */
final class SessionCookie
{
    public const NAME = 'patylook_session';

    public function __construct(private readonly bool $secure)
    {
    }

    public function set(Response $response, string $token): Response
    {
        return $response->withCookie(self::NAME, $token, $this->options(0));
    }

    public function clear(Response $response): Response
    {
        return $response->withCookie(self::NAME, '', $this->options(time() - 3600));
    }

    /** @return array<string, mixed> */
    private function options(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Core\Response;
use Patylook\Core\ResponseFilterInterface;

/** Adds the security headers every response carries. */
final class SecurityHeaders implements ResponseFilterInterface
{
    public function __construct(private readonly bool $hsts)
    {
    }

    public function apply(Response $response): Response
    {
        $response
            ->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'")
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin');
        if ($this->hsts) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}

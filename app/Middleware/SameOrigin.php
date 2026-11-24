<?php

declare(strict_types=1);

namespace Patylook\Middleware;

use Patylook\Core\MiddlewareInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\HttpException;

/**
 * Rejects cross-site state-changing requests using the browser-supplied Sec-Fetch-Site / Origin
 * headers. It complements the per-session CSRF token.
 */
final class SameOrigin implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (!$request->isSafe() && $request->method !== 'OPTIONS' && !$this->allowed($request)) {
            throw new HttpException(403, 'Requisição bloqueada', 'A origem da requisição não é permitida.');
        }
        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== '') {
            return $site === 'same-origin' || $site === 'none';
        }
        $origin = $request->header('Origin');
        if ($origin === '') {
            return true;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $port = parse_url($origin, PHP_URL_PORT);
        $originHost = $host . (is_int($port) ? ':' . $port : '');
        return strcasecmp($originHost, $request->host()) === 0;
    }
}

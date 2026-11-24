<?php

declare(strict_types=1);

namespace Patylook\Middleware;

use Patylook\Core\MiddlewareInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Infrastructure\StartupTasks;

/** Makes sure the database is ready (and the first administrator exists) before handling a request. */
final class EnsureReady implements MiddlewareInterface
{
    public function __construct(private readonly StartupTasks $startup)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($request->path !== '/healthz') { // the liveness probe must not depend on the database
            $this->startup->runIfDue();
        }
        return $next($request);
    }
}

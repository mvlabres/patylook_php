<?php

declare(strict_types=1);

namespace Patylook\Core;

interface MiddlewareInterface
{
    /**
     * Handles the request or passes it on.
     *
     * @param callable(Request): Response $next the rest of the pipeline
     */
    public function process(Request $request, callable $next): Response;
}

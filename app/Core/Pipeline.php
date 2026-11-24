<?php

declare(strict_types=1);

namespace Patylook\Core;

/** Runs a request through a chain of middleware and, at the end, the handler. */
final class Pipeline
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param list<class-string<MiddlewareInterface>> $middleware outermost first
     * @param callable(Request): Response $core the innermost handler
     */
    public function run(array $middleware, Request $request, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $class) {
            $current = $this->container->get($class);
            $inner = $next;
            $next = static fn (Request $r): Response => $current->process($r, $inner);
        }
        return $next($request);
    }
}

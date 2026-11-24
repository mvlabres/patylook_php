<?php

declare(strict_types=1);

namespace Patylook\Core;

/** Collects the application's routes and finds the one that answers a request. */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<class-string<MiddlewareInterface>> */
    private array $groupMiddleware = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string<MiddlewareInterface>> $middleware extra middleware, run after the group's
     */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $this->routes[] = new Route(strtoupper($method), $pattern, $handler, [...$this->groupMiddleware, ...$middleware]);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * Registers every route defined in $routes with the given middleware wrapped around it.
     *
     * @param list<class-string<MiddlewareInterface>> $middleware
     * @param callable(self): void $routes
     */
    public function group(array $middleware, callable $routes): void
    {
        $outer = $this->groupMiddleware;
        $this->groupMiddleware = [...$outer, ...$middleware];
        try {
            $routes($this);
        } finally {
            $this->groupMiddleware = $outer;
        }
    }

    /**
     * Finds the route for a request. HEAD is answered by the matching GET route.
     *
     * @return array{0: Route, 1: array<string, string>}|null the route and its path parameters
     */
    public function match(string $method, string $path): ?array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        foreach ($this->routes as $route) {
            if ($route->method !== $method) {
                continue;
            }
            $params = $route->match($path);
            if ($params !== null) {
                return [$route, $params];
            }
        }
        return null;
    }
}

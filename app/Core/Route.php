<?php

declare(strict_types=1);

namespace Patylook\Core;

/** A registered route: HTTP method, URL pattern, handler and the middleware that wraps it. */
final readonly class Route
{
    private string $regex;

    /**
     * @param array{0: class-string, 1: string} $handler controller class and method
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        public string $method,
        public string $pattern,
        public array $handler,
        public array $middleware = [],
    ) {
        // "{name}" matches one non-empty path segment; everything else is literal.
        $parts = preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= preg_match('/^\{(\w+)\}$/', $part, $m) === 1
                ? '(?P<' . $m[1] . '>[^/]+)'
                : preg_quote($part, '#');
        }
        $this->regex = '#^' . $regex . '$#D';
    }

    /**
     * @return array<string, string>|null the decoded path parameters when the path matches
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $m) !== 1) {
            return null;
        }
        $params = [];
        foreach ($m as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }
        return $params;
    }
}

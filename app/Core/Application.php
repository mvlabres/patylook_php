<?php

declare(strict_types=1);

namespace Patylook\Core;

use Patylook\Contracts\LoggerInterface;
use Patylook\Exceptions\HttpException;
use Throwable;

/**
 * The front controller's kernel: every request goes through handle(). It matches the route, runs
 * the middleware pipeline and the controller, and turns any failure into an error response.
 */
final class Application
{
    /**
     * @param list<class-string<MiddlewareInterface>> $globalMiddleware run for every request, outermost first
     * @param list<ResponseFilterInterface> $responseFilters applied to every response, errors included
     * @param list<TerminableInterface> $terminables run after the response has been sent
     */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly Pipeline $pipeline,
        private readonly ExceptionHandlerInterface $exceptions,
        private readonly LoggerInterface $log,
        private readonly array $globalMiddleware = [],
        private readonly array $responseFilters = [],
        private readonly array $terminables = [],
    ) {
    }

    /** Handles the current PHP request end to end. */
    public function run(): void
    {
        $started = microtime(true);
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        $response->send($request->method === 'HEAD');

        $this->log->debug('request', [
            'method' => $request->method,
            'path' => $request->path,
            'status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $this->terminate();
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->exceptions->handle($e, $request);
        }
        foreach ($this->responseFilters as $filter) {
            $response = $filter->apply($response);
        }
        return $response;
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method, $request->path);

        $middleware = $this->globalMiddleware;
        if ($match === null) {
            $core = static fn (Request $r): Response => throw new HttpException(404, 'Página não encontrada', 'O endereço acessado não existe.');
        } else {
            [$route, $params] = $match;
            $request->setRouteParams($params);
            $middleware = [...$middleware, ...$route->middleware];
            $core = function (Request $r) use ($route): Response {
                [$class, $method] = $route->handler;
                return $this->container->get($class)->$method($r);
            };
        }
        return $this->pipeline->run($middleware, $request, $core);
    }

    private function terminate(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($this->terminables as $terminable) {
            try {
                $terminable->terminate();
            } catch (Throwable $e) {
                $this->log->warning('tarefa pós-resposta', ['err' => $e->getMessage()]);
            }
        }
    }
}

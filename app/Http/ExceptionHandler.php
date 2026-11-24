<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Contracts\LoggerInterface;
use Patylook\Core\ExceptionHandlerInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ForbiddenException;
use Patylook\Exceptions\HttpException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\UnauthenticatedException;
use Throwable;

/** Translates exceptions that escape a handler into HTTP responses. */
final class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly Responder $responder,
        private readonly LoggerInterface $log,
    ) {
    }

    public function handle(Throwable $error, Request $request): Response
    {
        return match (true) {
            $error instanceof HttpException => $this->responder->errorPage($request, $error->status, $error->title, $error->detail),
            $error instanceof NotFoundException => $this->responder->errorPage($request, 404, 'Registro não encontrado', 'O registro solicitado não existe.'),
            $error instanceof ForbiddenException => $this->responder->errorPage($request, 403, 'Acesso negado', 'Você não tem permissão para acessar esta página.'),
            $error instanceof UnauthenticatedException => Response::redirect('/login'),
            default => $this->internalError($error, $request),
        };
    }

    private function internalError(Throwable $error, Request $request): Response
    {
        $this->log->error('erro interno', [
            'method' => $request->method,
            'path' => $request->path,
            'err' => $error::class . ': ' . $error->getMessage(),
            'at' => $error->getFile() . ':' . $error->getLine(),
        ]);
        try {
            return $this->responder->errorPage($request, 500, 'Erro interno', 'Algo deu errado. Tente novamente em instantes.');
        } catch (Throwable) {
            return Response::text('Erro interno', 500);
        }
    }
}

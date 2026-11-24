<?php

declare(strict_types=1);

namespace Patylook\Middleware;

use Patylook\Contracts\AuthServiceInterface;
use Patylook\Core\MiddlewareInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\HttpException;
use Patylook\Exceptions\UnauthenticatedException;
use Patylook\Http\SessionCookie;

/**
 * Resolves the session cookie, enforces the CSRF token on unsafe methods and attaches the
 * principal (user and session) to the request.
 */
final class RequireAuth implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly SessionCookie $cookie,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            $principal = $this->auth->authenticate($request->cookie(SessionCookie::NAME));
        } catch (UnauthenticatedException) {
            return $this->cookie->clear(Response::redirect('/login'));
        }

        if (!$request->isSafe()) {
            $this->guardUploadSize($request);
            $given = $request->post('csrf_token');
            if ($given === '') {
                $given = $request->header('X-CSRF-Token');
            }
            if (!hash_equals($principal->session->csrfToken, $given)) {
                throw new HttpException(403, 'Sessão inválida', 'O formulário expirou. Volte e tente novamente.');
            }
        }

        $request->setAttribute('principal', $principal);
        return $next($request);
    }

    /**
     * PHP silently drops the whole body of a multipart request that exceeds post_max_size; report
     * that precisely instead of as an expired form.
     */
    private function guardUploadSize(Request $request): void
    {
        if (!str_starts_with($request->header('Content-Type'), 'multipart/form-data')) {
            return;
        }
        if ($request->postAll() !== [] || $request->files() !== []) {
            return;
        }
        $limit = ini_parse_quantity((string) ini_get('post_max_size'));
        if ($limit > 0 && (int) $request->header('Content-Length') > $limit) {
            throw new HttpException(
                413,
                'Arquivos muito grandes',
                'O envio excede o tamanho máximo permitido. Reduza o número ou o tamanho das imagens.',
            );
        }
        throw new HttpException(400, 'Requisição inválida', 'Não foi possível ler o formulário enviado.');
    }
}

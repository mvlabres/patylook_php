<?php

declare(strict_types=1);

namespace Patylook\Middleware;

use Patylook\Core\MiddlewareInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Dto\Principal;
use Patylook\Exceptions\ForbiddenException;

/** Lets only administrators through. Must run after RequireAuth. */
final class RequireAdmin implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $principal = $request->attribute('principal');
        if (!$principal instanceof Principal || !$principal->user->admin) {
            throw new ForbiddenException();
        }
        return $next($request);
    }
}

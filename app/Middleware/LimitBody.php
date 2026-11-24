<?php

declare(strict_types=1);

namespace Patylook\Middleware;

use Patylook\Core\MiddlewareInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Models\ImagePaths;

/** Refuses uploads whose declared size exceeds what the form allows, before anything reads them. */
final class LimitBody implements MiddlewareInterface
{
    /** The main image plus every carousel image, plus room for the other fields. */
    public const MAX_UPLOAD_BODY = (1 + ImagePaths::MAX_EXTRA) * ImagePaths::MAX_BYTES + (1 << 20);

    public function process(Request $request, callable $next): Response
    {
        if ((int) $request->header('Content-Length') > self::MAX_UPLOAD_BODY) {
            return Response::text('Arquivos muito grandes', 413);
        }
        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Core\Request;
use Patylook\Core\Response;

/** Liveness probe: answers without touching the database. */
final class HealthController
{
    public function check(Request $request): Response
    {
        return Response::text('ok');
    }
}

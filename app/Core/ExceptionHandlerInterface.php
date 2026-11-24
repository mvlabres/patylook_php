<?php

declare(strict_types=1);

namespace Patylook\Core;

use Throwable;

/** Turns a failure that escaped the handler into the response the client receives. */
interface ExceptionHandlerInterface
{
    public function handle(Throwable $error, Request $request): Response;
}

<?php

declare(strict_types=1);

namespace Patylook\Core;

/** Adjusts every response before it is sent (for example, adding headers). */
interface ResponseFilterInterface
{
    public function apply(Response $response): Response;
}

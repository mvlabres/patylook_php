<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;

/** The current time in the application's time zone. */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

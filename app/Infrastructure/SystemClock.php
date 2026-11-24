<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Patylook\Contracts\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function __construct(private readonly DateTimeZone $timezone)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}

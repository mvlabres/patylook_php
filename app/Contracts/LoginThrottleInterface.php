<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;

/** Throttles repeated failed logins per key (client + username). */
interface LoginThrottleInterface
{
    public function blocked(string $key, DateTimeImmutable $now): bool;

    public function fail(string $key, DateTimeImmutable $now): void;

    public function reset(string $key): void;
}

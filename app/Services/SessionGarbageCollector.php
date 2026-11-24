<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Core\TerminableInterface;

/** Removes expired sessions now and then; PHP has no long-running process to do it on a timer. */
final class SessionGarbageCollector implements TerminableInterface
{
    /** @param int $divisor a sweep runs on roughly one request in $divisor */
    public function __construct(
        private readonly SessionStoreInterface $sessions,
        private readonly ClockInterface $clock,
        private readonly int $divisor = 100,
    ) {
    }

    /** Removes the sessions expired right now and returns how many there were. */
    public function collect(): int
    {
        return $this->sessions->deleteExpired($this->clock->now());
    }

    public function terminate(): void
    {
        if ($this->divisor > 0 && random_int(1, $this->divisor) === 1) {
            $this->collect();
        }
    }
}

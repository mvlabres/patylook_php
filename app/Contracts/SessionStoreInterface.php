<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;
use Patylook\Models\Session;

/** Keeps server-side sessions keyed by the hash of the cookie token. */
interface SessionStoreInterface
{
    public function create(Session $session): void;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function find(string $tokenHash): Session;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function update(Session $session): void;

    public function delete(string $tokenHash): void;

    public function deleteByUser(int $userId): void;

    /** Removes the sessions expired at $now and returns how many there were. */
    public function deleteExpired(DateTimeImmutable $now): int;
}

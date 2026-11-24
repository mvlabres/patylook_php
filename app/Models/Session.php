<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;

/**
 * The server-side state of a logged-in user. The raw token only exists in the browser cookie;
 * the store keeps its hash.
 */
final class Session
{
    public function __construct(
        public string $tokenHash,
        public int $userId,
        public string $csrfToken,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?Flash $flash = null,
    ) {
    }
}

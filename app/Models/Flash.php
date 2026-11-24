<?php

declare(strict_types=1);

namespace Patylook\Models;

/** A one-shot message shown on the next page rendered for the session. */
final readonly class Flash
{
    public function __construct(public FlashKind $kind, public string $message)
    {
    }
}

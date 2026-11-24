<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Session;
use Patylook\Models\User;

/** The authenticated caller attached to a request. */
final readonly class Principal
{
    public function __construct(
        public User $user,
        public Session $session,
        public string $token,
    ) {
    }
}

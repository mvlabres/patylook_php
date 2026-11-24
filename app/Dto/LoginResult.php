<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Session;
use Patylook\Models\User;

final readonly class LoginResult
{
    public function __construct(
        public string $token, // raw cookie value
        public Session $session,
        public User $user,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

interface PasswordHasherInterface
{
    public function hash(string $password): string;

    /** False for wrong passwords and for hashes that are not valid bcrypt (e.g. legacy values). */
    public function verify(string $hash, string $password): bool;
}

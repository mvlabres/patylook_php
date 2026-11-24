<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use Patylook\Contracts\PasswordHasherInterface;
use RuntimeException;

final class BcryptHasher implements PasswordHasherInterface
{
    public const COST = 12;

    public function __construct(private readonly int $cost = self::COST)
    {
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
        if (!is_string($hash)) {
            throw new RuntimeException('não foi possível gerar o hash da senha');
        }
        return $hash;
    }

    /** False for wrong passwords and for hashes that are not valid bcrypt (e.g. legacy values). */
    public function verify(string $hash, string $password): bool
    {
        return password_verify($password, $hash);
    }
}

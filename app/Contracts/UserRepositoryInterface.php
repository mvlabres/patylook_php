<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Models\User;
use Patylook\Models\UserCategory;

interface UserRepositoryInterface
{
    public function count(): int;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): User;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findByUsername(string $username): User;

    /** @return ListResult<User> */
    public function list(ListParams $params): ListResult;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(User $user): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(User $user): void;

    public function updatePassword(int $id, string $hash): void;

    public function setActive(int $id, bool $active): void;

    /** @return list<UserCategory> */
    public function listCategories(): array;

    /** Returns the id of the named category, creating it if missing. */
    public function ensureCategory(string $name): int;
}

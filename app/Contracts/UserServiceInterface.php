<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Models\User;
use Patylook\Models\UserCategory;

interface UserServiceInterface
{
    /** @return Page<User> */
    public function list(User $actor, int $page, string $search): Page;

    public function get(User $actor, int $id): User;

    /** @return list<UserCategory> */
    public function categories(): array;

    public function create(User $actor, User $user, string $password): void;

    public function update(User $actor, User $user): void;

    public function resetPassword(User $actor, int $id, string $password): void;

    public function setActive(User $actor, int $id, bool $active): void;

    public function changeOwnPassword(User $actor, string $current, string $next): void;
}

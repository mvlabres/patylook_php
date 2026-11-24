<?php

declare(strict_types=1);

namespace Patylook\Services\Support;

use Patylook\Models\User;

/** Decides which user category is administrative. */
final readonly class AdminRule
{
    public function __construct(public string $adminCategory)
    {
    }

    /** Flags the user as admin when it belongs to the administrative category. */
    public function mark(User $user): User
    {
        $user->admin = $user->categoryName === $this->adminCategory;
        return $user;
    }
}

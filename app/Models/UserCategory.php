<?php

declare(strict_types=1);

namespace Patylook\Models;

final readonly class UserCategory
{
    public function __construct(public int $id = 0, public string $name = '')
    {
    }
}

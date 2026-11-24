<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\Pricebook;
use Patylook\Models\User;

interface PricebookServiceInterface
{
    /** @return list<Pricebook> */
    public function list(int $productId): array;

    public function add(User $actor, Pricebook $entry): void;
}

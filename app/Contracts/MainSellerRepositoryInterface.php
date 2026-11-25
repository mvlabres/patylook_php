<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\MainSeller;

interface MainSellerRepositoryInterface
{
    /** The main seller (the oldest registered one), or null when none is registered. */
    public function find(): ?MainSeller;
}

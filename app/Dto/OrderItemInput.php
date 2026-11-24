<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Money;

final readonly class OrderItemInput
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public Money $discount = new Money(0),
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Http;

/** One product line of the order form, kept as submitted so the form can be shown again on errors. */
final readonly class OrderRow
{
    public function __construct(
        public int $productId = 0,
        public string $quantity = '1',
        public string $discount = '',
    ) {
    }
}

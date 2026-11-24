<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Money;

/** Aggregates orders for the dashboard. */
final readonly class OrderSummary
{
    public function __construct(
        public int $openOrders = 0,
        public int $completedOrders = 0,
        public Money $completedTotal = new Money(0),
    ) {
    }
}

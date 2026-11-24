<?php

declare(strict_types=1);

namespace Patylook\Dto;

use DateTimeImmutable;
use Patylook\Models\Order;

final readonly class Dashboard
{
    /**
     * @param list<Order> $recentOrders
     */
    public function __construct(
        public OrderSummary $summary,
        public int $lowStock,
        public array $recentOrders,
        public DateTimeImmutable $periodStart,
    ) {
    }
}

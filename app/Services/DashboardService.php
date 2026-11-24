<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\DashboardServiceInterface;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Dto\Dashboard;

final class DashboardService implements DashboardServiceInterface
{
    private const LOW_STOCK_THRESHOLD = 5;
    private const RECENT_ORDERS_LIMIT = 8;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Summarizes the current month. */
    public function load(): Dashboard
    {
        $now = $this->clock->now();
        $start = $now->setDate((int) $now->format('Y'), (int) $now->format('n'), 1)->setTime(0, 0);

        return new Dashboard(
            $this->orders->summary($start),
            $this->products->countLowStock(self::LOW_STOCK_THRESHOLD),
            $this->orders->recent(self::RECENT_ORDERS_LIMIT),
            $start,
        );
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;
use Patylook\Dto\ListResult;
use Patylook\Dto\OrderFilter;
use Patylook\Dto\OrderSummary;
use Patylook\Models\Order;
use Patylook\Models\OrderStatus;

interface OrderRepositoryInterface
{
    /** Inserts the order and its items, setting $order->id. */
    public function create(Order $order): void;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): Order;

    /** @return ListResult<Order> */
    public function list(OrderFilter $filter): ListResult;

    /**
     * Changes the status only if it is currently $from.
     *
     * @throws \Patylook\Exceptions\ConflictException otherwise
     */
    public function updateStatus(int $id, OrderStatus $from, OrderStatus $to): void;

    /** Aggregates orders created at or after $since. */
    public function summary(DateTimeImmutable $since): OrderSummary;

    /** @return list<Order> */
    public function recent(int $limit): array;
}

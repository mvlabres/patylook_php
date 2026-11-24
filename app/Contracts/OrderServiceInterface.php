<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\CreateOrderInput;
use Patylook\Dto\OrderFormData;
use Patylook\Dto\Page;
use Patylook\Models\Order;
use Patylook\Models\OrderStatus;
use Patylook\Models\User;

interface OrderServiceInterface
{
    public function formData(): OrderFormData;

    public function create(User $actor, CreateOrderInput $input): Order;

    public function get(int $id): Order;

    /** @return Page<Order> */
    public function list(int $page, ?OrderStatus $status, string $search): Page;

    public function changeStatus(int $id, OrderStatus $next): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Money;

final readonly class CreateOrderInput
{
    /**
     * @param list<OrderItemInput> $items
     */
    public function __construct(
        public int $customerId,
        public int $paymentTypeId,
        public Money $discount,
        public string $notes,
        public array $items,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Models;

final class OrderItem
{
    public function __construct(
        public int $productId = 0,
        public int $quantity = 0,
        public Money $unitPrice = new Money(0),
        public Money $unitCost = new Money(0),
        public Money $discount = new Money(0), // total discount applied to the line
        public ?int $pricebookId = null,
        public string $productName = '', // read-only projections
        public string $productSku = '',
    ) {
    }

    public function gross(): Money
    {
        return $this->unitPrice->times($this->quantity);
    }

    public function total(): Money
    {
        return $this->gross()->minus($this->discount);
    }
}

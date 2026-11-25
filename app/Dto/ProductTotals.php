<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Money;
use Patylook\Models\Percent;
use Patylook\Support\Fixed2;

/**
 * Current price and registered costs summed over a set of products (per unit, not weighted by
 * stock). Products without a current price are left out.
 */
final readonly class ProductTotals
{
    public function __construct(
        /** How many products have a current price and therefore take part in the sums. */
        public int $priced,
        public Money $price,
        public Money $cost,
    ) {
    }

    /** Real gain: total price minus total costs. */
    public function gain(): Money
    {
        return $this->price->minus($this->cost);
    }

    /** Markup of the totals, (price - cost) / cost. Null when there are no costs to apply it to. */
    public function markup(): ?Percent
    {
        if ($this->cost->cents <= 0) {
            return null;
        }
        return new Percent(Fixed2::roundDiv($this->gain()->cents * 10000, $this->cost->cents));
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\OrderStatus;

/** Restricts order listings. The search matches the customer name or the order id. */
final readonly class OrderFilter
{
    public function __construct(public ListParams $params, public ?OrderStatus $status = null)
    {
    }
}

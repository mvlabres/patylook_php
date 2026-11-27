<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\Customer;
use Patylook\Models\MainSeller;
use Patylook\Models\Order;

/** Draws the portrait order summary image that is sent to the customer. */
interface OrderBannerRendererInterface
{
    /** @return string PNG file contents; nothing is written to disk */
    public function render(Order $order, Customer $customer, ?MainSeller $seller): string;
}

<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Customer;
use Patylook\Models\PaymentType;
use Patylook\Models\Product;

/** The lookups needed to render the order form. */
final readonly class OrderFormData
{
    /**
     * @param list<Customer>    $customers
     * @param list<PaymentType> $paymentTypes
     * @param list<Product>     $products
     */
    public function __construct(
        public array $customers,
        public array $paymentTypes,
        public array $products,
    ) {
    }
}

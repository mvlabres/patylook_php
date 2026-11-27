<?php

declare(strict_types=1);

namespace Patylook\Http;

/** The submitted order, so the form can be re-rendered on errors. */
final readonly class OrderDraft
{
    /**
     * @param list<OrderRow> $rows
     */
    public function __construct(
        public int $customerId = 0,
        public int $paymentTypeId = 0,
        public string $discount = '',
        public string $notes = '',
        public array $rows = [],
        public string $installments = '',
    ) {
    }
}

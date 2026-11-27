<?php

declare(strict_types=1);

namespace Patylook\Dto;

/** What the "send by WhatsApp" dialog needs about a completed order. */
final readonly class OrderShare
{
    public function __construct(
        public int $orderId,
        public string $customerName,
        public string $customerFirstName,
        public string $customerPhone,
        /** Link to the customer's chat; null when the customer has no usable phone. */
        public ?string $chatUrl,
        /** The seller whose WhatsApp should be signed in, when one is registered. */
        public ?string $sellerName,
        public ?string $sellerPhone,
    ) {
    }
}

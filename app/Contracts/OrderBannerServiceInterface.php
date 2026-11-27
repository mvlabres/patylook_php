<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\OrderShare;

interface OrderBannerServiceInterface
{
    /**
     * The banner image of a completed order, generated on demand (never stored).
     *
     * @throws \Patylook\Exceptions\NotFoundException
     * @throws \Patylook\Exceptions\HttpException when the order is not completed
     */
    public function png(int $orderId): string;

    /**
     * Customer and seller data for the WhatsApp dialog.
     *
     * @throws \Patylook\Exceptions\NotFoundException
     * @throws \Patylook\Exceptions\HttpException when the order is not completed
     */
    public function share(int $orderId): OrderShare;
}

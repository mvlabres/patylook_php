<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;
use Patylook\Models\Pricebook;

interface PricebookRepositoryInterface
{
    /** @return list<Pricebook> */
    public function listByProduct(int $productId): array;

    /**
     * The entry valid on the date.
     *
     * @throws \Patylook\Exceptions\NotFoundException
     */
    public function findCurrent(int $productId, DateTimeImmutable $on): Pricebook;

    public function create(Pricebook $entry): void;
}

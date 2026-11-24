<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\PricebookRepositoryInterface;
use Patylook\Contracts\PricebookServiceInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Models\Pricebook;
use Patylook\Models\User;

final class PricebookService implements PricebookServiceInterface
{
    public function __construct(
        private readonly PricebookRepositoryInterface $prices,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function list(int $productId): array
    {
        return $this->prices->listByProduct($productId);
    }

    public function add(User $actor, Pricebook $entry): void
    {
        $entry->validFrom = $entry->validFrom?->setTime(0, 0);
        $entry->validTo = $entry->validTo?->setTime(0, 0);
        // Validation derives the markup from the informed final price.
        $entry->validate();
        $this->products->findById($entry->productId);
        $entry->createdBy = $actor->id;
        $this->prices->create($entry);
    }
}

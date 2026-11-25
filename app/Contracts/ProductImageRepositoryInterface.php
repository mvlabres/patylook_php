<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\ProductImage;

/** Persists the registered image paths of products. */
interface ProductImageRepositoryInterface
{
    /**
     * The images ordered main first, then by sort order.
     *
     * @return list<ProductImage>
     */
    public function listByProduct(int $productId): array;

    /**
     * @param list<int> $productIds
     * @return array<int, list<ProductImage>> images grouped by product id, main first
     */
    public function listByProducts(array $productIds): array;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): ProductImage;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findByPath(string $path): ProductImage;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(ProductImage $image): void;

    public function delete(int $id): void;
}

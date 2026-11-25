<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use DateTimeImmutable;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Dto\ProductTotals;
use Patylook\Models\Product;

interface ProductRepositoryInterface
{
    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): Product;

    /**
     * Products with the price valid on the given date.
     *
     * @return ListResult<Product>
     */
    public function list(ListParams $params, DateTimeImmutable $on): ListResult;

    /** Price, cost and gain summed over every product matching the search (not just one page). */
    public function totals(ListParams $params, DateTimeImmutable $on): ProductTotals;

    /**
     * Active products with stock and a valid price, used by the order form.
     *
     * @return list<Product>
     */
    public function listSellable(DateTimeImmutable $on): array;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(Product $product): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(Product $product): void;

    public function setActive(int $id, bool $active): void;

    /** @throws \Patylook\Exceptions\InsufficientStockException when stock < qty */
    public function decrementStock(int $id, int $qty): void;

    public function incrementStock(int $id, int $qty): void;

    public function countLowStock(int $threshold): int;
}

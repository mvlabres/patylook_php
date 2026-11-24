<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\ProductCategory;

interface ProductCategoryRepositoryInterface
{
    /** @return list<ProductCategory> */
    public function list(): array;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): ProductCategory;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(ProductCategory $category): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(ProductCategory $category): void;

    /** @throws \Patylook\Exceptions\InUseException */
    public function delete(int $id): void;
}

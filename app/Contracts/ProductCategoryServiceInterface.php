<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\ProductCategory;

interface ProductCategoryServiceInterface
{
    /** @return list<ProductCategory> */
    public function list(): array;

    public function get(int $id): ProductCategory;

    /** Creates the category when its id is 0. */
    public function save(ProductCategory $category): void;

    public function delete(int $id): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Dto\ProductImages;
use Patylook\Models\Product;
use Patylook\Models\User;

interface ProductServiceInterface
{
    /** @return Page<Product> */
    public function list(int $page, string $search): Page;

    public function get(int $id): Product;

    /** Registers the product together with its images; the main image is required. */
    public function create(User $actor, Product $product, ProductImages $images): void;

    public function update(Product $product): void;

    public function setActive(int $id, bool $active): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\ProductImages;
use Patylook\Dto\StoredFile;
use Patylook\Models\ProductImage;
use Patylook\Models\User;

interface ProductImageServiceInterface
{
    /** @return list<ProductImage> */
    public function list(int $productId): array;

    /** Replaces the main image (when given) and appends carousel images. */
    public function add(User $actor, int $productId, ProductImages $images): void;

    public function delete(int $productId, int $imageId): void;

    /** Locates a stored image file for serving. */
    public function open(string $folder, string $name): StoredFile;
}

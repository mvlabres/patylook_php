<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;

/** A registered image file of a product. */
final class ProductImage
{
    public function __construct(
        public int $id = 0,
        public int $productId = 0,
        public string $fileName = '',
        public string $path = '', // relative, e.g. images/products/CAM-1/principal.jpg
        public int $sortOrder = 0,
        public int $createdBy = 0,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function isMain(): bool
    {
        return $this->fileName === ImagePaths::MAIN_NAME;
    }

    /** The address the application serves the image from. */
    public function url(): string
    {
        return '/' . $this->path;
    }
}

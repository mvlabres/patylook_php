<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Product;
use Patylook\Models\ProductImage;

/** A product as shown in the showcase. */
final readonly class ShowcaseItem
{
    /**
     * @param list<ProductImage> $images main image first
     */
    public function __construct(public Product $product, public array $images)
    {
    }

    /** The main image, or null when the product has none. */
    public function main(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->isMain()) {
                return $image;
            }
        }
        return null;
    }
}

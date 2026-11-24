<?php

declare(strict_types=1);

namespace Patylook\Dto;

/** The uploads of one request: an optional main image and carousel images. */
final readonly class ProductImages
{
    /**
     * @param list<ImageUpload> $extra
     */
    public function __construct(public ?ImageUpload $main = null, public array $extra = [])
    {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Dto;

/** A raw uploaded file. */
final readonly class ImageUpload
{
    public function __construct(public string $filename, public string $data)
    {
    }
}

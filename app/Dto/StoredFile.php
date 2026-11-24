<?php

declare(strict_types=1);

namespace Patylook\Dto;

use DateTimeImmutable;

/** A file kept by the image storage. */
final readonly class StoredFile
{
    public function __construct(
        public string $path, // absolute path on disk
        public DateTimeImmutable $modifiedAt,
        public int $size,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Dto;

/** A validated, normalized (JPEG) image ready to be stored. */
final readonly class PreparedImage
{
    public function __construct(
        public string $name,
        public string $data,
        public int $sortOrder,
        public bool $isMain,
    ) {
    }
}

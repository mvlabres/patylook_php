<?php

declare(strict_types=1);

namespace Patylook\Dto;

/** Search and pagination options for list queries. */
final readonly class ListParams
{
    public function __construct(
        public string $search = '',
        public int $limit = 20,
        public int $offset = 0,
        /** Restricts product listings to active products (ignored by other repositories). */
        public bool $onlyActive = false,
    ) {
    }

    public function withOnlyActive(): self
    {
        return new self($this->search, $this->limit, $this->offset, true);
    }
}

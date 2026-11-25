<?php

declare(strict_types=1);

namespace Patylook\Dto;

use Patylook\Models\Gender;

/** Search and pagination options for list queries. */
final readonly class ListParams
{
    public function __construct(
        public string $search = '',
        public int $limit = 20,
        public int $offset = 0,
        /** Restricts product listings to active products (ignored by other repositories). */
        public bool $onlyActive = false,
        /** Restricts product listings to one gender (ignored by other repositories). */
        public ?Gender $gender = null,
    ) {
    }

    public function withOnlyActive(): self
    {
        return new self($this->search, $this->limit, $this->offset, true, $this->gender);
    }

    public function withGender(?Gender $gender): self
    {
        return new self($this->search, $this->limit, $this->offset, $this->onlyActive, $gender);
    }
}

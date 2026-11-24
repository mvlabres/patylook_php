<?php

declare(strict_types=1);

namespace Patylook\Dto;

/**
 * A slice of a larger result set.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
    }

    /** Total number of pages (at least one). */
    public function pages(): int
    {
        if ($this->pageSize <= 0) {
            return 1;
        }
        return max(1, intdiv($this->total + $this->pageSize - 1, $this->pageSize));
    }
}

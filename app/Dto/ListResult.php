<?php

declare(strict_types=1);

namespace Patylook\Dto;

/**
 * Rows returned by a repository list query plus the total number of matching rows.
 *
 * @template T
 */
final readonly class ListResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(public array $items, public int $total)
    {
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Services\Support;

use Patylook\Dto\ListParams;
use Patylook\Dto\Page;

/** Paging rules shared by every list use case. */
final class Pagination
{
    /** Number of rows shown per list page. */
    public const PAGE_SIZE = 20;

    private function __construct()
    {
    }

    /**
     * Normalizes the requested page and builds the matching query parameters.
     *
     * @return array{0: int, 1: ListParams}
     */
    public static function params(int $page, string $search): array
    {
        $page = max(1, $page);
        return [$page, new ListParams($search, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)];
    }

    /**
     * @template T
     * @param list<T> $items
     * @return Page<T>
     */
    public static function page(array $items, int $total, int $page): Page
    {
        return new Page($items, $total, $page, self::PAGE_SIZE);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Core\Request;
use Patylook\Dto\Page;

/** Data for the pagination partial. */
final readonly class Pager
{
    public function __construct(
        public int $page,
        public int $pages,
        public int $total,
        public string $prevUrl = '',
        public string $nextUrl = '',
    ) {
    }

    /** @param Page<mixed> $page */
    public static function for(Request $request, Page $page): self
    {
        $pages = $page->pages();
        $link = static function (int $n) use ($request): string {
            $query = $request->queryAll();
            $query['page'] = (string) $n;
            ksort($query);
            return $request->path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC1738);
        };
        return new self(
            $page->page,
            $pages,
            $page->total,
            $page->page > 1 ? $link($page->page - 1) : '',
            $page->page < $pages ? $link($page->page + 1) : '',
        );
    }
}

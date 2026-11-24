<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\ShowcaseServiceInterface;
use Patylook\Dto\Page;
use Patylook\Dto\ShowcaseItem;
use Patylook\Models\Product;
use Patylook\Services\Support\Pagination;

final class ShowcaseService implements ShowcaseServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductImageRepositoryInterface $images,
        private readonly ClockInterface $clock,
    ) {
    }

    public function list(int $page, string $search): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->products->list($params->withOnlyActive(), $this->clock->now()->setTime(0, 0));
        $ids = array_map(fn (Product $p): int => $p->id, $result->items);
        $images = $this->images->listByProducts($ids);
        $items = array_map(
            fn (Product $p): ShowcaseItem => new ShowcaseItem($p, $images[$p->id] ?? []),
            $result->items,
        );
        return Pagination::page($items, $result->total, $page);
    }
}

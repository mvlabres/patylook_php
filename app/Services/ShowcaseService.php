<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\MainSellerRepositoryInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\ShowcaseServiceInterface;
use Patylook\Dto\Page;
use Patylook\Dto\ShowcaseItem;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Gender;
use Patylook\Models\ImagePaths;
use Patylook\Models\MainSeller;
use Patylook\Models\Product;
use Patylook\Services\Support\Pagination;

final class ShowcaseService implements ShowcaseServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductImageRepositoryInterface $images,
        private readonly ClockInterface $clock,
        private readonly MainSellerRepositoryInterface $sellers,
    ) {
    }

    public function seller(): ?MainSeller
    {
        return $this->sellers->find();
    }

    public function isPublicImage(string $folder, string $name): bool
    {
        try {
            $image = $this->images->findByPath(ImagePaths::path($folder, $name));
            return $this->products->findById($image->productId)->isActive;
        } catch (NotFoundException) {
            return false;
        }
    }

    public function list(int $page, string $search, ?Gender $gender = null): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->products->list($params->withOnlyActive()->withGender($gender), $this->clock->now()->setTime(0, 0));
        $ids = array_map(fn (Product $p): int => $p->id, $result->items);
        $images = $this->images->listByProducts($ids);
        $items = array_map(
            fn (Product $p): ShowcaseItem => new ShowcaseItem($p, $images[$p->id] ?? []),
            $result->items,
        );
        return Pagination::page($items, $result->total, $page);
    }
}

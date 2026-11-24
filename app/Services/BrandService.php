<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\BrandRepositoryInterface;
use Patylook\Contracts\BrandServiceInterface;
use Patylook\Dto\Page;
use Patylook\Models\Brand;
use Patylook\Models\User;
use Patylook\Services\Support\ConflictTranslator;
use Patylook\Services\Support\Pagination;

final class BrandService implements BrandServiceInterface
{
    private const CONFLICTS = ['name' => 'Já existe uma marca com esse nome'];

    public function __construct(private readonly BrandRepositoryInterface $brands)
    {
    }

    public function list(int $page, string $search): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->brands->list($params);
        return Pagination::page($result->items, $result->total, $page);
    }

    public function listActive(): array
    {
        return $this->brands->listActive();
    }

    public function get(int $id): Brand
    {
        return $this->brands->findById($id);
    }

    public function save(User $actor, Brand $brand): void
    {
        $brand->normalize();
        $brand->validate();
        ConflictTranslator::run(function () use ($actor, $brand): void {
            if ($brand->id === 0) {
                $brand->isActive = true;
                $brand->createdBy = $actor->id;
                $this->brands->create($brand);
            } else {
                $this->brands->update($brand);
            }
        }, self::CONFLICTS);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->brands->setActive($id, $active);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ProductCategoryRepositoryInterface;
use Patylook\Contracts\ProductCategoryServiceInterface;
use Patylook\Models\ProductCategory;
use Patylook\Services\Support\ConflictTranslator;

final class ProductCategoryService implements ProductCategoryServiceInterface
{
    private const CONFLICTS = ['name' => 'Já existe uma categoria com esse nome'];

    public function __construct(private readonly ProductCategoryRepositoryInterface $categories)
    {
    }

    public function list(): array
    {
        return $this->categories->list();
    }

    public function get(int $id): ProductCategory
    {
        return $this->categories->findById($id);
    }

    public function save(ProductCategory $category): void
    {
        $category->normalize();
        $category->validate();
        ConflictTranslator::run(function () use ($category): void {
            if ($category->id === 0) {
                $this->categories->create($category);
            } else {
                $this->categories->update($category);
            }
        }, self::CONFLICTS);
    }

    public function delete(int $id): void
    {
        $this->categories->delete($id);
    }
}

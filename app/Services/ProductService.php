<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\BrandRepositoryInterface;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\ProductCategoryRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\ProductServiceInterface;
use Patylook\Contracts\TransactionManagerInterface;
use Patylook\Dto\Page;
use Patylook\Dto\ProductImages;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\ImagePaths;
use Patylook\Models\Product;
use Patylook\Models\User;
use Patylook\Services\Support\ConflictTranslator;
use Patylook\Services\Support\FileJournal;
use Patylook\Services\Support\Pagination;
use Throwable;

final class ProductService implements ProductServiceInterface
{
    /**
     * The SKU is generated from category, brand and the minute of creation, so a collision means
     * the same category/brand pair was registered within the same minute.
     */
    private const CONFLICTS = [
        'sku' => 'Já existe um produto com esse SKU (mesma categoria e marca no mesmo minuto). Aguarde um minuto e tente novamente',
    ];

    public function __construct(
        private readonly ProductCategoryRepositoryInterface $categories,
        private readonly BrandRepositoryInterface $brands,
        private readonly ProductRepositoryInterface $products,
        private readonly ProductImageStore $imageStore,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
    ) {
    }

    public function list(int $page, string $search): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->products->list($params, $this->clock->now()->setTime(0, 0));
        return Pagination::page($result->items, $result->total, $page);
    }

    public function get(int $id): Product
    {
        return $this->products->findById($id);
    }

    public function create(User $actor, Product $product, ProductImages $images): void
    {
        $product->normalize();
        $product->sku = ''; // never typed: generated below from category, brand and the creation time
        $v = new ValidationException();
        try {
            $product->validate();
        } catch (ValidationException $e) {
            $v->merge($e);
        }
        if ($product->brandId === 0) {
            $v->add('brand_id', 'Marca é obrigatória');
        }

        $category = '';
        $brand = '';
        if ($product->categoryId > 0) {
            try {
                $category = $this->categories->findById($product->categoryId)->name;
            } catch (NotFoundException) {
                $v->add('product_category_id', 'Categoria não encontrada');
            }
        }
        if ($product->brandId > 0) {
            try {
                $brand = $this->activeBrandName($product->brandId);
            } catch (ValidationException $e) {
                $v->merge($e);
            }
        }
        $folder = '';
        if ($category !== '' && $brand !== '') {
            $product->sku = Product::generateSku($category, $brand, $this->clock->now());
            // A generated SKU is always a safe folder name.
            $folder = ImagePaths::folderFor($product->sku) ?? throw new \LogicException('SKU gerado sem nome de pasta válido');
        }

        if ($images->main === null) {
            $v->add('main_image', 'A imagem principal é obrigatória');
        }
        $files = [];
        try {
            $files = $this->imageStore->prepare($images, []);
        } catch (ValidationException $e) {
            $v->merge($e);
        }
        $v->throwIfAny();

        $product->createdBy = $actor->id;
        $product->isActive = true;
        $written = new FileJournal();
        try {
            $this->tx->run(function () use ($actor, $product, $folder, $files, $written): void {
                ConflictTranslator::run(fn () => $this->products->create($product), self::CONFLICTS);
                $this->imageStore->store($actor, $product->id, $folder, $files, false, $written);
            });
        } catch (Throwable $e) {
            $this->imageStore->removeFiles($folder, $written->names());
            throw ProductImageStore::translateConflict($e);
        }
    }

    /**
     * Returns the name of an existing, active brand.
     *
     * @throws ValidationException on brand_id
     */
    private function activeBrandName(int $id): string
    {
        try {
            $brand = $this->brands->findById($id);
        } catch (NotFoundException) {
            throw ValidationException::single('brand_id', 'Marca não encontrada');
        }
        if (!$brand->isActive) {
            throw ValidationException::single('brand_id', 'Marca inativa');
        }
        return $brand->name;
    }

    public function update(Product $product): void
    {
        $product->normalize();
        $current = $this->products->findById($product->id);
        // The SKU is generated at creation and names the image folder: it is never edited.
        $product->sku = $current->sku;
        $product->validate();
        if ($product->brandId > 0 && $product->brandId !== $current->brandId) {
            $this->activeBrandName($product->brandId);
        }
        ConflictTranslator::run(fn () => $this->products->update($product), self::CONFLICTS);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->products->setActive($id, $active);
    }
}

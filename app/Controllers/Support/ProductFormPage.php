<?php

declare(strict_types=1);

namespace Patylook\Controllers\Support;

use Patylook\Contracts\BrandServiceInterface;
use Patylook\Contracts\ProductCategoryServiceInterface;
use Patylook\Contracts\ProductImageServiceInterface;
use Patylook\Contracts\ProductServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\NotFoundException;
use Patylook\Http\Responder;
use Patylook\Models\Brand;
use Patylook\Models\ImagePaths;
use Patylook\Models\Product;

/** Renders the product form (and, for a stored product, its image manager). Used by two controllers. */
final class ProductFormPage
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProductServiceInterface $products,
        private readonly ProductCategoryServiceInterface $categories,
        private readonly BrandServiceInterface $brands,
        private readonly ProductImageServiceInterface $images,
    ) {
    }

    /** @param array<string, string> $errors */
    public function render(Request $request, int $status, Product $product, array $errors = []): Response
    {
        $brands = $this->brands->listActive();
        // A product keeps showing its brand after the brand is deactivated.
        if ($product->brandId !== 0 && $product->brandName !== '') {
            $listed = false;
            foreach ($brands as $brand) {
                $listed = $listed || $brand->id === $product->brandId;
            }
            if (!$listed) {
                $brands[] = new Brand(id: $product->brandId, name: $product->brandName);
            }
        }

        $title = 'Novo produto';
        $images = [];
        if ($product->id !== 0) {
            $title = 'Editar produto';
            $images = $this->images->list($product->id);
        }
        $hasMain = false;
        foreach ($images as $image) {
            $hasMain = $hasMain || $image->isMain();
        }

        $page = $this->responder->newPage($request, $title, 'products');
        $page->errors = $errors;
        $page->data['product'] = $product;
        $page->data['categories'] = $this->categories->list();
        $page->data['brands'] = $brands;
        $page->data['images'] = $images;
        $page->data['hasMain'] = $hasMain;
        $page->data['maxExtra'] = ImagePaths::MAX_EXTRA;
        $page->data['maxMB'] = ImagePaths::MAX_BYTES >> 20;
        return $this->responder->view('product_form', $page, $status);
    }

    /**
     * Renders the edit form of a stored product.
     *
     * @param array<string, string> $errors
     * @throws NotFoundException
     */
    public function renderStored(Request $request, int $status, int $id, array $errors = []): Response
    {
        return $this->render($request, $status, $this->products->get($id), $errors);
    }
}

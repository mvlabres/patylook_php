<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\ProductServiceInterface;
use Patylook\Controllers\Support\ProductFormPage;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Pager;
use Patylook\Http\Responder;
use Patylook\Http\UploadReader;
use Patylook\Models\Product;
use Throwable;

final class ProductController extends Controller
{
    public function __construct(
        Responder $responder,
        private readonly ProductServiceInterface $products,
        private readonly ProductFormPage $formPage,
        private readonly UploadReader $uploads,
    ) {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $q = $request->query('q');
        $result = $this->products->list($this->pageNumber($request), $q);
        $page = $this->responder->newPage($request, 'Produtos', 'products');
        $page->data['products'] = $result->items;
        $page->data['query'] = $q;
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('product_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->formPage->render($request, 200, new Product(isActive: true));
    }

    public function edit(Request $request): Response
    {
        return $this->formPage->renderStored($request, 200, $this->routeId($request));
    }

    public function store(Request $request): Response
    {
        $form = new Form($request);
        $product = $this->fromForm($form);
        $images = $this->uploads->read($request, $form->errors);
        try {
            $form->throwIfInvalid();
            $this->products->create($this->user($request), $product, $images);
        } catch (ValidationException $e) {
            return $this->formPage->render($request, 422, $product, $e->fields());
        }
        return $this->responder->success($request, '/products/' . $product->id . '/prices', 'Produto cadastrado. Defina agora o preço de venda.');
    }

    public function update(Request $request): Response
    {
        $id = $this->routeId($request);
        $form = new Form($request);
        $product = $this->fromForm($form);
        $product->id = $id;
        try {
            $form->throwIfInvalid();
            $this->products->update($product);
        } catch (ValidationException $e) {
            return $this->formPage->render($request, 422, $product, $e->fields());
        }
        return $this->responder->success($request, '/products', 'Produto atualizado.');
    }

    public function setActive(Request $request): Response
    {
        $id = $this->routeId($request);
        $active = $request->post('active') === '1';
        try {
            $this->products->setActive($id, $active);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/products', $e);
        }
        return $this->responder->success($request, '/products', $active ? 'Produto ativado.' : 'Produto desativado.');
    }

    /**
     * Reads the editable fields. The SKU is not among them: the service generates it when the
     * product is created and keeps it afterwards.
     */
    private function fromForm(Form $form): Product
    {
        return new Product(
            name: $form->str('name'),
            description: $form->str('description'),
            categoryId: $form->integer('product_category_id'),
            brandId: $form->integer('brand_id'),
            stock: $form->intField('stock', 'Estoque'),
        );
    }
}

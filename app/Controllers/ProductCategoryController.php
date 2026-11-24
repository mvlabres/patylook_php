<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\ProductCategoryServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Responder;
use Patylook\Models\ProductCategory;
use Throwable;

final class ProductCategoryController extends Controller
{
    public function __construct(
        Responder $responder,
        private readonly ProductCategoryServiceInterface $categories,
    ) {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $page = $this->responder->newPage($request, 'Categorias de produtos', 'categories');
        $page->data['categories'] = $this->categories->list();
        return $this->responder->view('category_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new ProductCategory());
    }

    public function edit(Request $request): Response
    {
        return $this->form($request, 200, $this->categories->get($this->routeId($request)));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0, 'Categoria cadastrada.');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, $this->routeId($request), 'Categoria atualizada.');
    }

    public function delete(Request $request): Response
    {
        $id = $this->routeId($request);
        try {
            $this->categories->delete($id);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/categories', $e);
        }
        return $this->responder->success($request, '/categories', 'Categoria excluída.');
    }

    private function save(Request $request, int $id, string $message): Response
    {
        $form = new Form($request);
        $category = new ProductCategory($id, $form->str('name'), $form->str('description'));
        try {
            $this->categories->save($category);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $category, $e->fields());
        }
        return $this->responder->success($request, '/categories', $message);
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, ProductCategory $category, array $errors = []): Response
    {
        $page = $this->responder->newPage($request, $category->id !== 0 ? 'Editar categoria' : 'Nova categoria', 'categories');
        $page->errors = $errors;
        $page->data['category'] = $category;
        return $this->responder->view('category_form', $page, $status);
    }
}

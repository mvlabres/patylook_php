<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\BrandServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Pager;
use Patylook\Http\Responder;
use Patylook\Models\Brand;
use Throwable;

final class BrandController extends Controller
{
    public function __construct(Responder $responder, private readonly BrandServiceInterface $brands)
    {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $q = $request->query('q');
        $result = $this->brands->list($this->pageNumber($request), $q);
        $page = $this->responder->newPage($request, 'Marcas', 'brands');
        $page->data['brands'] = $result->items;
        $page->data['query'] = $q;
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('brand_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new Brand(isActive: true));
    }

    public function edit(Request $request): Response
    {
        return $this->form($request, 200, $this->brands->get($this->routeId($request)));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0, 'Marca cadastrada.');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, $this->routeId($request), 'Marca atualizada.');
    }

    public function setActive(Request $request): Response
    {
        $id = $this->routeId($request);
        $active = $request->post('active') === '1';
        try {
            $this->brands->setActive($id, $active);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/brands', $e);
        }
        return $this->responder->success($request, '/brands', $active ? 'Marca ativada.' : 'Marca desativada.');
    }

    private function save(Request $request, int $id, string $message): Response
    {
        $brand = new Brand(id: $id, name: (new Form($request))->str('name'));
        try {
            $this->brands->save($this->user($request), $brand);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $brand, $e->fields());
        }
        return $this->responder->success($request, '/brands', $message);
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, Brand $brand, array $errors = []): Response
    {
        $page = $this->responder->newPage($request, $brand->id !== 0 ? 'Editar marca' : 'Nova marca', 'brands');
        $page->errors = $errors;
        $page->data['brand'] = $brand;
        return $this->responder->view('brand_form', $page, $status);
    }
}

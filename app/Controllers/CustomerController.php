<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\CustomerServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Pager;
use Patylook\Http\Responder;
use Patylook\Models\Customer;
use Throwable;

final class CustomerController extends Controller
{
    public function __construct(Responder $responder, private readonly CustomerServiceInterface $customers)
    {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $q = $request->query('q');
        $result = $this->customers->list($this->pageNumber($request), $q);
        $page = $this->responder->newPage($request, 'Clientes', 'customers');
        $page->data['customers'] = $result->items;
        $page->data['query'] = $q;
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('customer_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new Customer(isActive: true));
    }

    public function edit(Request $request): Response
    {
        return $this->form($request, 200, $this->customers->get($this->routeId($request)));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0, 'Cliente cadastrado.');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, $this->routeId($request), 'Cliente atualizado.');
    }

    public function setActive(Request $request): Response
    {
        $id = $this->routeId($request);
        $active = $request->post('active') === '1';
        try {
            $this->customers->setActive($id, $active);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/customers', $e);
        }
        return $this->responder->success($request, '/customers', $active ? 'Cliente ativado.' : 'Cliente desativado.');
    }

    private function save(Request $request, int $id, string $message): Response
    {
        $form = new Form($request);
        $customer = new Customer(
            id: $id,
            name: $form->str('name'),
            document: $form->str('document'),
            phone: $form->str('phone'),
            email: $form->str('email'),
        );
        try {
            $this->customers->save($customer);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $customer, $e->fields());
        }
        return $this->responder->success($request, '/customers', $message);
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, Customer $customer, array $errors = []): Response
    {
        $page = $this->responder->newPage($request, $customer->id !== 0 ? 'Editar cliente' : 'Novo cliente', 'customers');
        $page->errors = $errors;
        $page->data['customer'] = $customer;
        return $this->responder->view('customer_form', $page, $status);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\PaymentTypeServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Responder;
use Patylook\Models\PaymentCategory;
use Patylook\Models\PaymentType;
use Throwable;

final class PaymentTypeController extends Controller
{
    public function __construct(Responder $responder, private readonly PaymentTypeServiceInterface $types)
    {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $page = $this->responder->newPage($request, 'Formas de pagamento', 'payment-types');
        $page->data['paymentTypes'] = $this->types->list();
        return $this->responder->view('payment_type_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new PaymentType());
    }

    public function edit(Request $request): Response
    {
        return $this->form($request, 200, $this->types->get($this->routeId($request)));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0, 'Forma de pagamento cadastrada.');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, $this->routeId($request), 'Forma de pagamento atualizada.');
    }

    public function delete(Request $request): Response
    {
        $id = $this->routeId($request);
        try {
            $this->types->delete($id);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, '/payment-types', $e);
        }
        return $this->responder->success($request, '/payment-types', 'Forma de pagamento excluída.');
    }

    private function save(Request $request, int $id, string $message): Response
    {
        $form = new Form($request);
        $type = new PaymentType(
            id: $id,
            name: $form->str('name'),
            description: $form->str('description'),
            surchargePercent: $form->percent('surcharge_percent', 'Acréscimo', false),
            category: PaymentCategory::tryFrom($form->str('category')),
        );
        try {
            $form->throwIfInvalid();
            $this->types->save($type);
        } catch (ValidationException $e) {
            return $this->form($request, 422, $type, $e->fields());
        }
        return $this->responder->success($request, '/payment-types', $message);
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, PaymentType $type, array $errors = []): Response
    {
        $title = $type->id !== 0 ? 'Editar forma de pagamento' : 'Nova forma de pagamento';
        $page = $this->responder->newPage($request, $title, 'payment-types');
        $page->errors = $errors;
        $page->data['paymentType'] = $type;
        return $this->responder->view('payment_type_form', $page, $status);
    }
}

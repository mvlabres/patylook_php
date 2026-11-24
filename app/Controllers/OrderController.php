<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\OrderServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Dto\CreateOrderInput;
use Patylook\Dto\OrderItemInput;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\OrderDraft;
use Patylook\Http\OrderRow;
use Patylook\Http\Pager;
use Patylook\Http\Responder;
use Patylook\Models\FlashKind;
use Patylook\Models\Money;
use Patylook\Models\OrderStatus;
use Patylook\Support\Str;
use Throwable;

final class OrderController extends Controller
{
    public function __construct(Responder $responder, private readonly OrderServiceInterface $orders)
    {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $q = $request->query('q');
        $status = OrderStatus::tryFrom($request->query('status'));
        $result = $this->orders->list($this->pageNumber($request), $status, $q);
        $page = $this->responder->newPage($request, 'Pedidos', 'orders');
        $page->data['orders'] = $result->items;
        $page->data['query'] = $q;
        $page->data['status'] = $status?->value ?? '';
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('order_list', $page);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, 200, new OrderDraft());
    }

    public function store(Request $request): Response
    {
        $form = new Form($request);
        $customerId = $form->integer('customer_id');
        $paymentTypeId = $form->integer('payment_type_id');
        $notes = $form->str('notes');
        $discount = $form->money('discount', 'Desconto', false);

        $rows = [];
        $items = [];
        $quantities = $request->postList('quantity');
        $discounts = $request->postList('item_discount');
        foreach ($request->postList('product_id') as $i => $rawProduct) {
            if ($rawProduct === '') {
                continue; // empty row
            }
            $row = new OrderRow(
                productId: Str::toInt($rawProduct) ?? 0,
                quantity: $quantities[$i] ?? '',
                discount: $discounts[$i] ?? '',
            );
            $quantity = Str::toInt($row->quantity);
            if ($quantity === null) {
                $form->errors->add('items', 'Quantidade inválida');
            }
            $itemDiscount = Money::zero();
            if ($row->discount !== '') {
                $parsed = Money::parse($row->discount);
                if ($parsed === null) {
                    $form->errors->add('items', 'Desconto do item inválido');
                } else {
                    $itemDiscount = $parsed;
                }
            }
            $rows[] = $row;
            $items[] = new OrderItemInput($row->productId, $quantity ?? 0, $itemDiscount);
        }

        $draft = new OrderDraft($customerId, $paymentTypeId, $form->str('discount'), $notes, $rows);
        try {
            $form->throwIfInvalid();
            $order = $this->orders->create(
                $this->user($request),
                new CreateOrderInput($customerId, $paymentTypeId, $discount, $notes, $items),
            );
        } catch (ValidationException $e) {
            return $this->form($request, 422, $draft, $e->fields());
        }
        return $this->responder->success($request, '/orders/' . $order->id, sprintf('Pedido #%d criado.', $order->id));
    }

    public function show(Request $request): Response
    {
        $order = $this->orders->get($this->routeId($request));
        $page = $this->responder->newPage($request, sprintf('Pedido #%d', $order->id), 'orders');
        $page->data['order'] = $order;
        return $this->responder->view('order_detail', $page);
    }

    public function changeStatus(Request $request): Response
    {
        $id = $this->routeId($request);
        $next = OrderStatus::tryFrom($request->post('status'));
        $destination = '/orders/' . $id;
        if ($next !== OrderStatus::Completed && $next !== OrderStatus::Canceled) {
            return $this->responder->redirect($request, $destination, FlashKind::Error, 'Status inválido.');
        }
        try {
            $this->orders->changeStatus($id, $next);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, $destination, $e);
        }
        return $this->responder->success(
            $request,
            $destination,
            $next === OrderStatus::Canceled ? 'Pedido cancelado e estoque devolvido.' : 'Pedido concluído.',
        );
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, OrderDraft $draft, array $errors = []): Response
    {
        $data = $this->orders->formData();
        if ($draft->rows === []) {
            $draft = new OrderDraft($draft->customerId, $draft->paymentTypeId, $draft->discount, $draft->notes, [new OrderRow()]);
        }
        $page = $this->responder->newPage($request, 'Novo pedido', 'orders');
        $page->errors = $errors;
        $page->data['form'] = $draft;
        $page->data['customers'] = $data->customers;
        $page->data['paymentTypes'] = $data->paymentTypes;
        $page->data['products'] = $data->products;
        return $this->responder->view('order_form', $page, $status);
    }
}

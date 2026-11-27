<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Contracts\OrderBannerServiceInterface;
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
    public function __construct(
        Responder $responder,
        private readonly OrderServiceInterface $orders,
        private readonly OrderBannerServiceInterface $banners,
    ) {
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
        $rawInstallments = $form->str('installments');
        $installments = Str::toInt($rawInstallments);
        if ($rawInstallments !== '' && $installments === null) {
            $form->errors->add('installments', 'Quantidade de parcelas inválida');
        }

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

        $draft = new OrderDraft($customerId, $paymentTypeId, $form->str('discount'), $notes, $rows, $rawInstallments);
        try {
            $form->throwIfInvalid();
            $order = $this->orders->create(
                $this->user($request),
                new CreateOrderInput($customerId, $paymentTypeId, $discount, $notes, $items, $installments),
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
        // Right after completing the order the page opens the "send by WhatsApp" dialog.
        $page->data['offerWhatsapp'] = $order->status === OrderStatus::Completed && $request->query('whatsapp') === '1';
        return $this->responder->view('order_detail', $page);
    }

    /** The order summary image for WhatsApp, drawn on demand (completed orders only). */
    public function banner(Request $request): Response
    {
        $id = $this->routeId($request);
        return Response::png($this->banners->png($id), sprintf('pedido-%d.png', $id));
    }

    /** Customer, seller and chat link for the "send by WhatsApp" dialog. */
    public function share(Request $request): Response
    {
        $s = $this->banners->share($this->routeId($request));
        return Response::json([
            'orderId' => $s->orderId,
            'customerName' => $s->customerName,
            'customerFirstName' => $s->customerFirstName,
            'customerPhone' => $s->customerPhone,
            'chatUrl' => $s->chatUrl,
            'sellerName' => $s->sellerName,
            'sellerPhone' => $s->sellerPhone,
        ]);
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
        $completed = $next === OrderStatus::Completed;
        return $this->responder->success(
            $request,
            $completed ? $destination . '?whatsapp=1' : $destination,
            $completed ? 'Pedido concluído.' : 'Pedido cancelado e estoque devolvido.',
        );
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, int $status, OrderDraft $draft, array $errors = []): Response
    {
        $data = $this->orders->formData();
        if ($draft->rows === []) {
            $draft = new OrderDraft($draft->customerId, $draft->paymentTypeId, $draft->discount, $draft->notes, [new OrderRow()], $draft->installments);
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

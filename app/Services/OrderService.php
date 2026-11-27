<?php

declare(strict_types=1);

namespace Patylook\Services;

use DateTimeImmutable;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Contracts\OrderServiceInterface;
use Patylook\Contracts\PaymentTypeRepositoryInterface;
use Patylook\Contracts\PricebookRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\TransactionManagerInterface;
use Patylook\Dto\CreateOrderInput;
use Patylook\Dto\OrderFilter;
use Patylook\Dto\OrderFormData;
use Patylook\Dto\OrderItemInput;
use Patylook\Dto\Page;
use Patylook\Exceptions\InsufficientStockException;
use Patylook\Exceptions\InvalidTransitionException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\Order;
use Patylook\Models\OrderItem;
use Patylook\Models\OrderStatus;
use Patylook\Models\PaymentCategory;
use Patylook\Models\Percent;
use Patylook\Models\User;
use Patylook\Services\Support\Pagination;

final class OrderService implements OrderServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly PricebookRepositoryInterface $prices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly PaymentTypeRepositoryInterface $payments,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
    ) {
    }

    public function formData(): OrderFormData
    {
        return new OrderFormData(
            $this->customers->listActive(),
            $this->payments->list(),
            $this->products->listSellable($this->clock->now()->setTime(0, 0)),
        );
    }

    public function create(User $actor, CreateOrderInput $input): Order
    {
        $v = new ValidationException();
        $order = new Order(
            userId: $actor->id,
            customerId: $input->customerId,
            paymentTypeId: $input->paymentTypeId,
            status: OrderStatus::Open,
            discount: $input->discount,
            notes: $input->notes,
            installments: $input->installments,
        );
        $order->normalize();

        try {
            $customer = $this->customers->findById($input->customerId);
            if (!$customer->isActive) {
                $v->add('customer_id', 'Cliente inativo');
            }
        } catch (NotFoundException) {
            $v->add('customer_id', 'Cliente não encontrado');
        }

        $surcharge = Percent::zero();
        $credit = false;
        try {
            $payment = $this->payments->findById($input->paymentTypeId);
            $surcharge = $payment->surchargePercent;
            $credit = $payment->category === PaymentCategory::Credit;
        } catch (NotFoundException) {
            $v->add('payment_type_id', 'Forma de pagamento não encontrada');
        }

        $today = $this->clock->now()->setTime(0, 0);
        foreach ($input->items as $item) {
            try {
                $order->items[] = $this->buildItem($item, $today);
            } catch (ValidationException $e) {
                $v->merge($e);
            }
        }
        $v->throwIfAny();

        $order->calculate($surcharge, $credit);

        // Lock stock rows in a stable order so concurrent orders cannot deadlock.
        usort($order->items, fn (OrderItem $a, OrderItem $b): int => $a->productId <=> $b->productId);

        $this->tx->run(function () use ($order): void {
            $this->orders->create($order);
            foreach ($order->items as $item) {
                try {
                    $this->products->decrementStock($item->productId, $item->quantity);
                } catch (InsufficientStockException) {
                    throw ValidationException::single('items', sprintf('Estoque insuficiente para %s', $item->productName));
                }
            }
        });
        return $order;
    }

    /**
     * Snapshots the current price and cost of a product into an order item.
     *
     * @throws ValidationException on "items"
     */
    private function buildItem(OrderItemInput $input, DateTimeImmutable $today): OrderItem
    {
        try {
            $product = $this->products->findById($input->productId);
        } catch (NotFoundException) {
            throw ValidationException::single('items', 'Produto não encontrado');
        }
        if (!$product->isActive) {
            throw ValidationException::single('items', sprintf('Produto %s está inativo', $product->name));
        }
        try {
            $price = $this->prices->findCurrent($product->id, $today);
        } catch (NotFoundException) {
            throw ValidationException::single('items', sprintf('Produto %s não possui preço vigente', $product->name));
        }
        return new OrderItem(
            productId: $product->id,
            quantity: $input->quantity,
            unitPrice: $price->finalPrice,
            unitCost: $price->totalCost(),
            discount: $input->discount,
            pricebookId: $price->id,
            productName: $product->name,
            productSku: $product->sku,
        );
    }

    public function get(int $id): Order
    {
        return $this->orders->findById($id);
    }

    public function list(int $page, ?OrderStatus $status, string $search): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->orders->list(new OrderFilter($params, $status));
        return Pagination::page($result->items, $result->total, $page);
    }

    public function changeStatus(int $id, OrderStatus $next): void
    {
        $this->tx->run(function () use ($id, $next): void {
            $order = $this->orders->findById($id);
            if (!$order->status->canTransitionTo($next)) {
                throw new InvalidTransitionException();
            }
            $this->orders->updateStatus($id, $order->status, $next);
            if ($next === OrderStatus::Canceled) {
                // Stock was reserved at creation; give it back.
                foreach ($order->items as $item) {
                    $this->products->incrementStock($item->productId, $item->quantity);
                }
            }
        });
    }
}

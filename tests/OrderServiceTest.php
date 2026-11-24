<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Patylook\Dto\CreateOrderInput;
use Patylook\Dto\OrderItemInput;
use Patylook\Exceptions\InvalidTransitionException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\Customer;
use Patylook\Models\Money;
use Patylook\Models\OrderStatus;
use Patylook\Models\PaymentType;
use Patylook\Models\Percent;
use Patylook\Models\Pricebook;
use Patylook\Models\Product;
use Patylook\Models\User;
use Patylook\Services\OrderService;
use Patylook\Tests\Fakes\FakeClock;
use Patylook\Tests\Fakes\FakeCustomers;
use Patylook\Tests\Fakes\FakeOrders;
use Patylook\Tests\Fakes\FakePayments;
use Patylook\Tests\Fakes\FakePrices;
use Patylook\Tests\Fakes\FakeProducts;
use Patylook\Tests\Fakes\FakeTransactions;

final class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private FakeProducts $products;
    private FakeOrders $orders;
    private User $seller;

    protected function setUp(): void
    {
        $this->products = new FakeProducts(
            new Product(id: 1, name: 'Camiseta', sku: 'CAM-1', isActive: true, stock: 10),
            new Product(id: 2, name: 'Caneca', sku: 'CAN-1', isActive: true, stock: 3),
            new Product(id: 3, name: 'Sem preço', sku: 'SP-1', isActive: true, stock: 5),
            new Product(id: 4, name: 'Inativo', sku: 'IN-1', isActive: false, stock: 5),
        );
        $prices = new FakePrices();
        $prices->byProduct = [
            1 => new Pricebook(id: 11, productId: 1, productCost: new Money(3000), packagingCost: new Money(500), finalPrice: new Money(5250)),
            2 => new Pricebook(id: 12, productId: 2, productCost: new Money(1000), finalPrice: new Money(2000)),
            4 => new Pricebook(id: 14, productId: 4, productCost: new Money(1000), finalPrice: new Money(2000)),
        ];
        $customers = new FakeCustomers();
        $customers->items = [
            1 => new Customer(id: 1, name: 'Cliente', isActive: true),
            2 => new Customer(id: 2, name: 'Cliente inativo', isActive: false),
        ];
        $payments = new FakePayments();
        $payments->items = [
            1 => new PaymentType(id: 1, name: 'Cartão', surchargePercent: new Percent(500)),
            2 => new PaymentType(id: 2, name: 'PIX'),
        ];
        $this->orders = new FakeOrders();
        $this->service = new OrderService(
            $this->orders,
            $this->products,
            $prices,
            $customers,
            $payments,
            new FakeTransactions($this->products, $this->orders),
            new FakeClock(),
        );
        $this->seller = new User(id: 9);
    }

    /** @param list<array{0: int, 1: int, 2?: int}> $items product, quantity, item discount */
    private function input(int $customer, int $payment, array $items, int $discount = 0, string $notes = ''): CreateOrderInput
    {
        return new CreateOrderInput(
            $customer,
            $payment,
            new Money($discount),
            $notes,
            array_map(fn (array $i): OrderItemInput => new OrderItemInput($i[0], $i[1], new Money($i[2] ?? 0)), $items),
        );
    }

    public function testCreateOrderSnapshotsPricesAndReservesStock(): void
    {
        $order = $this->service->create($this->seller, $this->input(1, 1, [[2, 1], [1, 2, 250]], 500, ' obs '));
        // 2 x 52,50 - 2,50 = 102,50 ; + 1 x 20,00 = 122,50 ; - 5,00 = 117,50 ; +5% = 5,88 (round half up)
        $this->assertSame(12250, $order->subtotal->cents);
        $this->assertSame(588, $order->paymentAddition->cents);
        $this->assertSame(12338, $order->total->cents);
        $this->assertSame(OrderStatus::Open, $order->status);
        $this->assertSame(9, $order->userId);
        $this->assertSame('obs', $order->notes);
        $this->assertSame(1, $order->items[0]->productId, 'itens ordenados por produto');
        $this->assertSame(5250, $order->items[0]->unitPrice->cents);
        $this->assertSame(3500, $order->items[0]->unitCost->cents);
        $this->assertSame(11, $order->items[0]->pricebookId);
        $this->assertSame(8, $this->products->items[1]->stock);
        $this->assertSame(2, $this->products->items[2]->stock);
    }

    public function testCreateOrderRejections(): void
    {
        $cases = [
            'inactive customer' => $this->input(2, 2, [[1, 1]]),
            'unknown customer' => $this->input(99, 2, [[1, 1]]),
            'unknown payment' => $this->input(1, 99, [[1, 1]]),
            'no items' => $this->input(1, 2, []),
            'no price' => $this->input(1, 2, [[3, 1]]),
            'inactive product' => $this->input(1, 2, [[4, 1]]),
            'unknown product' => $this->input(1, 2, [[99, 1]]),
            'zero quantity' => $this->input(1, 2, [[1, 0]]),
            'duplicate product' => $this->input(1, 2, [[1, 1], [1, 1]]),
            'discount too big' => $this->input(1, 2, [[1, 1]], 999999),
        ];
        foreach ($cases as $name => $input) {
            $this->setUp();
            $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->seller, $input), $name);
            $this->assertSame([], $this->orders->items, "$name: nada persistido");
            $this->assertSame(10, $this->products->items[1]->stock, "$name: estoque intacto");
        }
    }

    public function testInsufficientStockRollsBack(): void
    {
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->seller, $this->input(1, 2, [[1, 5], [2, 4]])));
        $this->assertTrue($e->has('items'));
        $this->assertContains('Caneca', $e->fields()['items']);
        $this->assertSame(10, $this->products->items[1]->stock);
        $this->assertSame(3, $this->products->items[2]->stock);
        $this->assertSame([], $this->orders->items);
    }

    public function testChangeStatus(): void
    {
        $order = $this->service->create($this->seller, $this->input(1, 2, [[1, 4]]));
        $this->assertSame(6, $this->products->items[1]->stock);

        $this->service->changeStatus($order->id, OrderStatus::Completed);
        $this->assertSame(6, $this->products->items[1]->stock, 'concluir não mexe no estoque');
        $this->assertThrows(InvalidTransitionException::class, fn () => $this->service->changeStatus($order->id, OrderStatus::Open));
        $this->service->changeStatus($order->id, OrderStatus::Canceled);
        $this->assertSame(10, $this->products->items[1]->stock, 'cancelar devolve o estoque');
        $this->assertThrows(InvalidTransitionException::class, fn () => $this->service->changeStatus($order->id, OrderStatus::Canceled));
        $this->assertSame(10, $this->products->items[1]->stock, 'cancelar duas vezes não devolve duas vezes');
    }
}

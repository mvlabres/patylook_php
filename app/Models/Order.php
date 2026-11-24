<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

final class Order
{
    private const MAX_NOTES = 2000;

    /**
     * @param list<OrderItem> $items
     */
    public function __construct(
        public int $id = 0,
        public int $userId = 0,
        public int $customerId = 0,
        public int $paymentTypeId = 0,
        public OrderStatus $status = OrderStatus::Open,
        public Money $subtotal = new Money(0),
        public Money $discount = new Money(0),
        public Money $paymentAddition = new Money(0),
        public Money $total = new Money(0),
        public string $notes = '',
        public ?DateTimeImmutable $createdAt = null,
        public array $items = [],
        public string $userName = '', // read-only projections
        public string $customerName = '',
        public string $paymentTypeName = '',
    ) {
    }

    public function normalize(): void
    {
        $this->notes = Str::trim($this->notes);
    }

    /**
     * Validates the order and derives subtotal, payment addition and total.
     * The payment surcharge applies over the subtotal after the order discount.
     *
     * @throws ValidationException
     */
    public function calculate(Percent $surcharge): void
    {
        $v = new ValidationException();
        if ($this->customerId <= 0) {
            $v->add('customer_id', 'Cliente é obrigatório');
        }
        if ($this->paymentTypeId <= 0) {
            $v->add('payment_type_id', 'Forma de pagamento é obrigatória');
        }
        Rules::optionalText($v, 'notes', 'Observações', $this->notes, self::MAX_NOTES);
        if ($this->items === []) {
            $v->add('items', 'Adicione ao menos um produto');
        }

        $seen = [];
        $subtotal = Money::zero();
        foreach ($this->items as $it) {
            if ($it->productId <= 0) {
                $v->add('items', 'Produto inválido');
            } elseif (isset($seen[$it->productId])) {
                $v->add('items', 'Produto repetido no pedido');
            } elseif ($it->quantity <= 0) {
                $v->add('items', 'Quantidade deve ser maior que zero');
            } elseif ($it->discount->isNegative() || $it->discount->cents > $it->gross()->cents) {
                $v->add('items', 'Desconto do item deve estar entre zero e o valor do item');
            }
            $seen[$it->productId] = true;
            $subtotal = $subtotal->plus($it->total());
        }
        if ($this->discount->isNegative() || $this->discount->cents > $subtotal->cents) {
            $v->add('discount', 'Desconto deve estar entre zero e o subtotal');
        }
        $v->throwIfAny();

        $base = $subtotal->minus($this->discount);
        $this->subtotal = $subtotal;
        $this->paymentAddition = $base->percent($surcharge);
        $this->total = $base->plus($this->paymentAddition);
    }
}

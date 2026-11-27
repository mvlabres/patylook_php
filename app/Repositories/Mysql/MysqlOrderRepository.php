<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use DateTimeImmutable;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Dto\ListResult;
use Patylook\Dto\OrderFilter;
use Patylook\Dto\OrderSummary;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Money;
use Patylook\Models\Order;
use Patylook\Models\OrderItem;
use Patylook\Models\OrderStatus;
use Patylook\Support\Str;

final class MysqlOrderRepository extends MysqlRepository implements OrderRepositoryInterface
{
    private const SELECT = "SELECT o.id, o.user_id, o.customer_id, o.payment_type_id, o.status, o.subtotal, o.discount, "
        . "o.payment_addition, o.total, COALESCE(o.notes, '') AS notes, o.installments, o.created_at, u.name AS user_name, "
        . 'c.name AS customer_name, pt.name AS payment_type_name '
        . 'FROM orders o JOIN `user` u ON u.id = o.user_id JOIN customer c ON c.id = o.customer_id '
        . 'JOIN payment_type pt ON pt.id = o.payment_type_id ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Order
    {
        return new Order(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            customerId: (int) $row['customer_id'],
            paymentTypeId: (int) $row['payment_type_id'],
            status: OrderStatus::from((string) $row['status']),
            subtotal: self::money($row['subtotal']),
            discount: self::money($row['discount']),
            paymentAddition: self::money($row['payment_addition']),
            total: self::money($row['total']),
            notes: (string) $row['notes'],
            installments: $row['installments'] === null ? null : (int) $row['installments'],
            createdAt: $this->timestamp($row['created_at']),
            userName: (string) $row['user_name'],
            customerName: (string) $row['customer_name'],
            paymentTypeName: (string) $row['payment_type_name'],
        );
    }

    public function create(Order $order): void
    {
        $this->db->execute(
            'INSERT INTO orders (user_id, customer_id, payment_type_id, status, subtotal, discount, payment_addition, total, notes, installments) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $order->userId, $order->customerId, $order->paymentTypeId, $order->status->value,
                $order->subtotal->decimal(), $order->discount->decimal(), $order->paymentAddition->decimal(),
                $order->total->decimal(), self::nullString($order->notes), $order->installments,
            ],
        );
        $order->id = $this->db->lastInsertId();

        foreach ($order->items as $item) {
            $this->db->execute(
                'INSERT INTO order_item (order_id, product_id, quantity, unit_price, unit_cost, discount, pricebook_id) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $order->id, $item->productId, $item->quantity, $item->unitPrice->decimal(),
                    $item->unitCost->decimal(), $item->discount->decimal(), $item->pricebookId,
                ],
            );
        }
    }

    public function findById(int $id): Order
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE o.id = ?', [$id]);
        if ($row === null) {
            throw new NotFoundException();
        }
        $order = $this->hydrate($row);

        $items = $this->db->select(
            'SELECT oi.product_id, oi.quantity, oi.unit_price, oi.unit_cost, oi.discount, oi.pricebook_id, '
            . 'p.name AS product_name, p.sku AS product_sku '
            . 'FROM order_item oi JOIN product p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY p.name',
            [$id],
        );
        foreach ($items as $r) {
            $order->items[] = new OrderItem(
                productId: (int) $r['product_id'],
                quantity: (int) $r['quantity'],
                unitPrice: self::money($r['unit_price']),
                unitCost: self::money($r['unit_cost']),
                discount: self::money($r['discount']),
                pricebookId: $r['pricebook_id'] === null ? null : (int) $r['pricebook_id'],
                productName: (string) $r['product_name'],
                productSku: (string) $r['product_sku'],
            );
        }
        return $order;
    }

    public function list(OrderFilter $filter): ListResult
    {
        $conditions = [];
        $args = [];
        if ($filter->status !== null) {
            $conditions[] = 'o.status = ?';
            $args[] = $filter->status->value;
        }
        $search = $filter->params->search;
        if ($search !== '') {
            $id = Str::toInt(str_starts_with($search, '#') ? substr($search, 1) : $search) ?? 0;
            $conditions[] = '(c.name LIKE ? OR o.id = ?)';
            array_push($args, self::likePattern($search), $id);
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions) . ' ';

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM orders o JOIN customer c ON c.id = o.customer_id ' . $where,
            $args,
        );
        $rows = $this->db->select(
            self::SELECT . $where . 'ORDER BY o.id DESC LIMIT ? OFFSET ?',
            [...$args, $filter->params->limit, $filter->params->offset],
        );
        return new ListResult(array_map($this->hydrate(...), $rows), $total);
    }

    public function recent(int $limit): array
    {
        $rows = $this->db->select(self::SELECT . 'ORDER BY o.id DESC LIMIT ?', [$limit]);
        return array_map($this->hydrate(...), $rows);
    }

    public function updateStatus(int $id, OrderStatus $from, OrderStatus $to): void
    {
        $affected = $this->db->execute(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [$to->value, $id, $from->value],
        );
        if ($affected === 0) {
            throw new ConflictException();
        }
    }

    public function summary(DateTimeImmutable $since): OrderSummary
    {
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(status = 'open'), 0) AS open_orders, COALESCE(SUM(status = 'completed'), 0) AS completed_orders, "
            . "COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS completed_total "
            . 'FROM orders WHERE created_at >= ?',
            [$since->format('Y-m-d H:i:s')],
        ) ?? ['open_orders' => 0, 'completed_orders' => 0, 'completed_total' => '0'];
        return new OrderSummary(
            (int) $row['open_orders'],
            (int) $row['completed_orders'],
            self::money($row['completed_total']),
        );
    }
}

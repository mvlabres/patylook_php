<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Customer;

final class MysqlCustomerRepository extends MysqlRepository implements CustomerRepositoryInterface
{
    private const SELECT = 'SELECT id, name, document, phone, email, is_active, created_at FROM customer ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Customer
    {
        return new Customer(
            id: (int) $row['id'],
            name: (string) $row['name'],
            document: (string) $row['document'],
            phone: (string) $row['phone'],
            email: (string) $row['email'],
            isActive: (bool) $row['is_active'],
            createdAt: $this->timestamp($row['created_at']),
        );
    }

    public function findById(int $id): Customer
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function list(ListParams $params): ListResult
    {
        $where = '';
        $args = [];
        if ($params->search !== '') {
            $like = self::likePattern($params->search);
            $where = 'WHERE (name LIKE ? OR document LIKE ? OR email LIKE ? OR phone LIKE ?) ';
            $args = [$like, $like, $like, $like];
        }
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM customer ' . $where, $args);
        $rows = $this->db->select(
            self::SELECT . $where . 'ORDER BY name LIMIT ? OFFSET ?',
            [...$args, $params->limit, $params->offset],
        );
        return new ListResult(array_map($this->hydrate(...), $rows), $total);
    }

    public function listActive(): array
    {
        return array_map($this->hydrate(...), $this->db->select(self::SELECT . 'WHERE is_active = TRUE ORDER BY name'));
    }

    public function create(Customer $customer): void
    {
        $this->db->execute(
            'INSERT INTO customer (name, document, phone, email, is_active) VALUES (?, ?, ?, ?, ?)',
            [$customer->name, $customer->document, $customer->phone, $customer->email, $customer->isActive],
        );
        $customer->id = $this->db->lastInsertId();
    }

    public function update(Customer $customer): void
    {
        $this->db->execute(
            'UPDATE customer SET name = ?, document = ?, phone = ?, email = ? WHERE id = ?',
            [$customer->name, $customer->document, $customer->phone, $customer->email, $customer->id],
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE customer SET is_active = ? WHERE id = ?', [$active, $id]);
    }
}

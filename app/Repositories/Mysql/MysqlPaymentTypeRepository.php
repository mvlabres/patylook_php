<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\PaymentTypeRepositoryInterface;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\PaymentCategory;
use Patylook\Models\PaymentType;

final class MysqlPaymentTypeRepository extends MysqlRepository implements PaymentTypeRepositoryInterface
{
    private const SELECT = 'SELECT id, name, description, surcharge_percent, category FROM payment_type ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PaymentType
    {
        return new PaymentType(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            surchargePercent: self::percent($row['surcharge_percent']),
            category: PaymentCategory::tryFrom((string) $row['category']),
        );
    }

    public function list(): array
    {
        return array_map($this->hydrate(...), $this->db->select(self::SELECT . 'ORDER BY name'));
    }

    public function findById(int $id): PaymentType
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function create(PaymentType $type): void
    {
        $this->db->execute(
            'INSERT INTO payment_type (name, description, surcharge_percent, category) VALUES (?, ?, ?, ?)',
            [$type->name, $type->description, $type->surchargePercent->decimal(), ($type->category ?? PaymentCategory::Cash)->value],
        );
        $type->id = $this->db->lastInsertId();
    }

    public function update(PaymentType $type): void
    {
        $this->db->execute(
            'UPDATE payment_type SET name = ?, description = ?, surcharge_percent = ?, category = ? WHERE id = ?',
            [$type->name, $type->description, $type->surchargePercent->decimal(), ($type->category ?? PaymentCategory::Cash)->value, $type->id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM payment_type WHERE id = ?', [$id]);
    }
}

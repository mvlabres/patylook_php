<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\BrandRepositoryInterface;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Brand;

final class MysqlBrandRepository extends MysqlRepository implements BrandRepositoryInterface
{
    private const SELECT = 'SELECT b.id, b.name AS name, b.is_active, b.created_by, u.name AS created_by_name, b.created_at '
        . 'FROM brand b JOIN `user` u ON u.id = b.created_by ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Brand
    {
        return new Brand(
            id: (int) $row['id'],
            name: (string) $row['name'],
            isActive: (bool) $row['is_active'],
            createdBy: (int) $row['created_by'],
            createdByName: (string) $row['created_by_name'],
            createdAt: $this->timestamp($row['created_at']),
        );
    }

    public function findById(int $id): Brand
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE b.id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function list(ListParams $params): ListResult
    {
        $where = '';
        $args = [];
        if ($params->search !== '') {
            $where = 'WHERE b.name LIKE ? ';
            $args = [self::likePattern($params->search)];
        }
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM brand b ' . $where, $args);
        $rows = $this->db->select(
            self::SELECT . $where . 'ORDER BY b.name LIMIT ? OFFSET ?',
            [...$args, $params->limit, $params->offset],
        );
        return new ListResult(array_map($this->hydrate(...), $rows), $total);
    }

    public function listActive(): array
    {
        $rows = $this->db->select(self::SELECT . 'WHERE b.is_active = TRUE ORDER BY b.name');
        return array_map($this->hydrate(...), $rows);
    }

    public function create(Brand $brand): void
    {
        $this->db->execute(
            'INSERT INTO brand (name, is_active, created_by) VALUES (?, ?, ?)',
            [$brand->name, $brand->isActive, $brand->createdBy],
        );
        $brand->id = $this->db->lastInsertId();
    }

    public function update(Brand $brand): void
    {
        $this->db->execute('UPDATE brand SET name = ? WHERE id = ?', [$brand->name, $brand->id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE brand SET is_active = ? WHERE id = ?', [$active, $id]);
    }
}

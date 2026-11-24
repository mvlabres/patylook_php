<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\ProductCategoryRepositoryInterface;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\ProductCategory;

final class MysqlProductCategoryRepository extends MysqlRepository implements ProductCategoryRepositoryInterface
{
    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProductCategory
    {
        return new ProductCategory((int) $row['id'], (string) $row['name'], (string) $row['description']);
    }

    public function list(): array
    {
        $rows = $this->db->select('SELECT id, name, description FROM product_category ORDER BY name');
        return array_map($this->hydrate(...), $rows);
    }

    public function findById(int $id): ProductCategory
    {
        $row = $this->db->selectOne('SELECT id, name, description FROM product_category WHERE id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function create(ProductCategory $category): void
    {
        $this->db->execute(
            'INSERT INTO product_category (name, description) VALUES (?, ?)',
            [$category->name, $category->description],
        );
        $category->id = $this->db->lastInsertId();
    }

    public function update(ProductCategory $category): void
    {
        $this->db->execute(
            'UPDATE product_category SET name = ?, description = ? WHERE id = ?',
            [$category->name, $category->description, $category->id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM product_category WHERE id = ?', [$id]);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\ImagePaths;
use Patylook\Models\ProductImage;

final class MysqlProductImageRepository extends MysqlRepository implements ProductImageRepositoryInterface
{
    private const SELECT = 'SELECT id, product_id, file_name, path, sort_order, created_by, created_at FROM product_images ';

    /** The main image first, then carousel order. */
    private const ORDER = " ORDER BY (file_name = '" . ImagePaths::MAIN_NAME . "') DESC, sort_order, id";

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProductImage
    {
        return new ProductImage(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            fileName: (string) $row['file_name'],
            path: (string) $row['path'],
            sortOrder: (int) $row['sort_order'],
            createdBy: (int) $row['created_by'],
            createdAt: $this->timestamp($row['created_at']),
        );
    }

    public function listByProduct(int $productId): array
    {
        $rows = $this->db->select(self::SELECT . 'WHERE product_id = ?' . self::ORDER, [$productId]);
        return array_map($this->hydrate(...), $rows);
    }

    public function listByProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($productIds), '?'));
        $rows = $this->db->select(self::SELECT . 'WHERE product_id IN (' . $marks . ')' . self::ORDER, array_values($productIds));
        $out = [];
        foreach ($rows as $row) {
            $image = $this->hydrate($row);
            $out[$image->productId][] = $image;
        }
        return $out;
    }

    public function findById(int $id): ProductImage
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function findByPath(string $path): ProductImage
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE path = ?', [$path]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function create(ProductImage $image): void
    {
        $this->db->execute(
            'INSERT INTO product_images (product_id, file_name, path, sort_order, created_by) VALUES (?, ?, ?, ?, ?)',
            [$image->productId, $image->fileName, $image->path, $image->sortOrder, $image->createdBy],
        );
        $image->id = $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM product_images WHERE id = ?', [$id]);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use DateTimeImmutable;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Exceptions\InsufficientStockException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Infrastructure\Database\Database;
use Patylook\Models\Product;

final class MysqlProductRepository extends MysqlRepository implements ProductRepositoryInterface
{
    /**
     * Projects the price valid on a date (two date placeholders come first). Joined names are
     * aliased because listSellable() wraps this query in a derived table, where duplicate column
     * names are an error.
     */
    private const SELECT = "SELECT p.id, p.name AS name, COALESCE(p.description, '') AS description, p.sku, p.product_category_id, "
        . 'pc.name AS category_name, p.brand_id, b.name AS brand_name, '
        . 'p.stock, p.is_active, p.created_by, p.created_at, '
        . '(SELECT pb.final_price FROM pricebook pb WHERE pb.product_id = p.id AND pb.valid_from <= ? '
        . 'AND (pb.valid_to IS NULL OR pb.valid_to >= ?) ORDER BY pb.valid_from DESC, pb.id DESC LIMIT 1) AS current_price '
        . 'FROM product p JOIN product_category pc ON pc.id = p.product_category_id '
        . 'LEFT JOIN brand b ON b.id = p.brand_id ';

    public function __construct(Database $db, private readonly ClockInterface $clock)
    {
        parent::__construct($db);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Product
    {
        return new Product(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            sku: (string) $row['sku'],
            categoryId: (int) $row['product_category_id'],
            categoryName: (string) $row['category_name'],
            brandId: (int) $row['brand_id'],
            brandName: (string) ($row['brand_name'] ?? ''),
            stock: (int) $row['stock'],
            isActive: (bool) $row['is_active'],
            createdBy: (int) $row['created_by'],
            createdAt: $this->timestamp($row['created_at']),
            currentPrice: $row['current_price'] === null ? null : self::money($row['current_price']),
        );
    }

    public function findById(int $id): Product
    {
        $day = self::dateParam($this->clock->now());
        $row = $this->db->selectOne(self::SELECT . 'WHERE p.id = ?', [$day, $day, $id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function list(ListParams $params, DateTimeImmutable $on): ListResult
    {
        $conditions = [];
        $args = [];
        if ($params->search !== '') {
            $like = self::likePattern($params->search);
            $conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            array_push($args, $like, $like);
        }
        if ($params->onlyActive) {
            $conditions[] = 'p.is_active = TRUE';
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions) . ' ';

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM product p ' . $where, $args);
        $day = self::dateParam($on);
        $rows = $this->db->select(
            self::SELECT . $where . 'ORDER BY p.name LIMIT ? OFFSET ?',
            [$day, $day, ...$args, $params->limit, $params->offset],
        );
        return new ListResult(array_map($this->hydrate(...), $rows), $total);
    }

    public function listSellable(DateTimeImmutable $on): array
    {
        $day = self::dateParam($on);
        $rows = $this->db->select(
            'SELECT * FROM (' . self::SELECT . 'WHERE p.is_active = TRUE AND p.stock > 0) t '
            . 'WHERE current_price IS NOT NULL ORDER BY name',
            [$day, $day],
        );
        return array_map($this->hydrate(...), $rows);
    }

    public function create(Product $product): void
    {
        $this->db->execute(
            'INSERT INTO product (name, description, sku, product_category_id, brand_id, stock, is_active, created_by) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $product->name, self::nullString($product->description), $product->sku, $product->categoryId,
                self::nullInt($product->brandId), $product->stock, $product->isActive, $product->createdBy,
            ],
        );
        $product->id = $this->db->lastInsertId();
    }

    public function update(Product $product): void
    {
        // The SKU is deliberately absent: it is set once, at creation.
        $this->db->execute(
            'UPDATE product SET name = ?, description = ?, product_category_id = ?, brand_id = ?, stock = ? WHERE id = ?',
            [
                $product->name, self::nullString($product->description), $product->categoryId,
                self::nullInt($product->brandId), $product->stock, $product->id,
            ],
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE product SET is_active = ? WHERE id = ?', [$active, $id]);
    }

    public function decrementStock(int $id, int $qty): void
    {
        $affected = $this->db->execute('UPDATE product SET stock = stock - ? WHERE id = ? AND stock >= ?', [$qty, $id, $qty]);
        if ($affected === 0) {
            throw new InsufficientStockException();
        }
    }

    public function incrementStock(int $id, int $qty): void
    {
        $this->db->execute('UPDATE product SET stock = stock + ? WHERE id = ?', [$qty, $id]);
    }

    public function countLowStock(int $threshold): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM product WHERE is_active = TRUE AND stock <= ?', [$threshold]);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use DateTimeImmutable;
use Patylook\Contracts\PricebookRepositoryInterface;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Pricebook;

final class MysqlPricebookRepository extends MysqlRepository implements PricebookRepositoryInterface
{
    private const SELECT = 'SELECT pb.id, pb.product_id, pb.product_cost, pb.markup_percent, pb.packaging_cost, pb.logistics_cost, '
        . 'pb.other_costs, pb.final_price, pb.valid_from, pb.valid_to, pb.created_by, u.name AS created_by_name, pb.created_at '
        . 'FROM pricebook pb JOIN `user` u ON u.id = pb.created_by ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Pricebook
    {
        return new Pricebook(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            productCost: self::money($row['product_cost']),
            markupPercent: self::percent($row['markup_percent']),
            packagingCost: self::money($row['packaging_cost']),
            logisticsCost: self::money($row['logistics_cost']),
            otherCosts: self::money($row['other_costs']),
            finalPrice: self::money($row['final_price']),
            validFrom: $this->date($row['valid_from']),
            validTo: $this->date($row['valid_to']),
            createdBy: (int) $row['created_by'],
            createdByName: (string) $row['created_by_name'],
            createdAt: $this->timestamp($row['created_at']),
        );
    }

    public function listByProduct(int $productId): array
    {
        $rows = $this->db->select(
            self::SELECT . 'WHERE pb.product_id = ? ORDER BY pb.valid_from DESC, pb.id DESC',
            [$productId],
        );
        return array_map($this->hydrate(...), $rows);
    }

    public function findCurrent(int $productId, DateTimeImmutable $on): Pricebook
    {
        $day = self::dateParam($on);
        $row = $this->db->selectOne(
            self::SELECT . 'WHERE pb.product_id = ? AND pb.valid_from <= ? AND (pb.valid_to IS NULL OR pb.valid_to >= ?) '
            . 'ORDER BY pb.valid_from DESC, pb.id DESC LIMIT 1',
            [$productId, $day, $day],
        );
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function create(Pricebook $entry): void
    {
        $this->db->execute(
            'INSERT INTO pricebook (product_id, product_cost, markup_percent, valid_from, valid_to, final_price, '
            . 'logistics_cost, other_costs, packaging_cost, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $entry->productId,
                $entry->productCost->decimal(),
                $entry->markupPercent->decimal(),
                $entry->validFrom === null ? null : self::dateParam($entry->validFrom),
                $entry->validTo === null ? null : self::dateParam($entry->validTo),
                $entry->finalPrice->decimal(),
                $entry->logisticsCost->decimal(),
                $entry->otherCosts->decimal(),
                $entry->packagingCost->decimal(),
                $entry->createdBy,
            ],
        );
        $entry->id = $this->db->lastInsertId();
    }
}

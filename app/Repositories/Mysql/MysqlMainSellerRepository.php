<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\MainSellerRepositoryInterface;
use Patylook\Models\MainSeller;

final class MysqlMainSellerRepository extends MysqlRepository implements MainSellerRepositoryInterface
{
    public function find(): ?MainSeller
    {
        $row = $this->db->selectOne('SELECT id, name, whatsapp FROM main_seller ORDER BY id LIMIT 1');
        return $row === null ? null : new MainSeller((int) $row['id'], (string) $row['name'], (string) $row['whatsapp']);
    }
}

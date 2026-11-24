<?php

declare(strict_types=1);

namespace Patylook\Infrastructure\Database;

use Patylook\Contracts\TransactionManagerInterface;

final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function run(callable $work): mixed
    {
        return $this->database->transaction($work);
    }
}

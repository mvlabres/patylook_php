<?php

declare(strict_types=1);

namespace Patylook\Contracts;

interface TransactionManagerInterface
{
    /**
     * Runs $work inside a database transaction: it commits when the callback returns and rolls
     * back when it throws. Repositories share the connection, so they take part in the transaction
     * automatically; a nested call joins the outer transaction.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}

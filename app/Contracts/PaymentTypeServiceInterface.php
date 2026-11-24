<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\PaymentType;

interface PaymentTypeServiceInterface
{
    /** @return list<PaymentType> */
    public function list(): array;

    public function get(int $id): PaymentType;

    /** Creates the payment type when its id is 0. */
    public function save(PaymentType $type): void;

    public function delete(int $id): void;
}

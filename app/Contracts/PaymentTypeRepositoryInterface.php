<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Models\PaymentType;

interface PaymentTypeRepositoryInterface
{
    /** @return list<PaymentType> */
    public function list(): array;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): PaymentType;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(PaymentType $type): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(PaymentType $type): void;

    /** @throws \Patylook\Exceptions\InUseException */
    public function delete(int $id): void;
}

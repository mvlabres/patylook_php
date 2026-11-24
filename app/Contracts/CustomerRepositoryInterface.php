<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Models\Customer;

interface CustomerRepositoryInterface
{
    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): Customer;

    /** @return ListResult<Customer> */
    public function list(ListParams $params): ListResult;

    /** @return list<Customer> */
    public function listActive(): array;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(Customer $customer): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(Customer $customer): void;

    public function setActive(int $id, bool $active): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Models\Customer;

interface CustomerServiceInterface
{
    /** @return Page<Customer> */
    public function list(int $page, string $search): Page;

    public function get(int $id): Customer;

    /** Creates the customer when its id is 0. */
    public function save(Customer $customer): void;

    public function setActive(int $id, bool $active): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Contracts\CustomerServiceInterface;
use Patylook\Dto\Page;
use Patylook\Models\Customer;
use Patylook\Services\Support\ConflictTranslator;
use Patylook\Services\Support\Pagination;

final class CustomerService implements CustomerServiceInterface
{
    private const CONFLICTS = ['document' => 'Já existe um cliente com esse CPF'];

    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function list(int $page, string $search): Page
    {
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->customers->list($params);
        return Pagination::page($result->items, $result->total, $page);
    }

    public function get(int $id): Customer
    {
        return $this->customers->findById($id);
    }

    public function save(Customer $customer): void
    {
        $customer->normalize();
        $customer->validate();
        ConflictTranslator::run(function () use ($customer): void {
            if ($customer->id === 0) {
                $customer->isActive = true;
                $this->customers->create($customer);
            } else {
                $this->customers->update($customer);
            }
        }, self::CONFLICTS);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->customers->setActive($id, $active);
    }
}

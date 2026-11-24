<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Models\Brand;

interface BrandRepositoryInterface
{
    /** @throws \Patylook\Exceptions\NotFoundException */
    public function findById(int $id): Brand;

    /** @return ListResult<Brand> */
    public function list(ListParams $params): ListResult;

    /** @return list<Brand> */
    public function listActive(): array;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function create(Brand $brand): void;

    /** @throws \Patylook\Exceptions\ConflictException */
    public function update(Brand $brand): void;

    public function setActive(int $id, bool $active): void;
}

<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Models\Brand;
use Patylook\Models\User;

interface BrandServiceInterface
{
    /** @return Page<Brand> */
    public function list(int $page, string $search): Page;

    /**
     * Every active brand, for selection lists.
     *
     * @return list<Brand>
     */
    public function listActive(): array;

    public function get(int $id): Brand;

    /** Creates the brand when its id is 0 (recording $actor as its creator) or updates it. */
    public function save(User $actor, Brand $brand): void;

    public function setActive(int $id, bool $active): void;
}

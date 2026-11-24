<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Dto\ShowcaseItem;

interface ShowcaseServiceInterface
{
    /**
     * Active products with their images.
     *
     * @return Page<ShowcaseItem>
     */
    public function list(int $page, string $search): Page;
}

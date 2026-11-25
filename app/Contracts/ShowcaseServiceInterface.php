<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Page;
use Patylook\Dto\ShowcaseItem;
use Patylook\Models\Gender;
use Patylook\Models\MainSeller;

interface ShowcaseServiceInterface
{
    /**
     * Active products with their images.
     *
     * @return Page<ShowcaseItem>
     */
    public function list(int $page, string $search, ?Gender $gender = null): Page;

    /** The seller the public showcase sends customers to, if one is registered. */
    public function seller(): ?MainSeller;

    /** Whether the stored image may be shown to the public: it exists and its product is active. */
    public function isPublicImage(string $folder, string $name): bool;
}

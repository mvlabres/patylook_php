<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

/** The manufacturer/label a product can be linked to (product.brand_id). */
final class Brand
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public bool $isActive = false,
        public int $createdBy = 0,
        public string $createdByName = '', // read-only projection
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function normalize(): void
    {
        $this->name = Str::trim($this->name);
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'name', 'Nome', $this->name, 100);
        $v->throwIfAny();
    }
}

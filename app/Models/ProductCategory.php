<?php

declare(strict_types=1);

namespace Patylook\Models;

use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

final class ProductCategory
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $description = '',
    ) {
    }

    public function normalize(): void
    {
        $this->name = Str::trim($this->name);
        $this->description = Str::trim($this->description);
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'name', 'Nome', $this->name, 45);
        Rules::optionalText($v, 'description', 'Descrição', $this->description, 45);
        $v->throwIfAny();
    }
}

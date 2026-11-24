<?php

declare(strict_types=1);

namespace Patylook\Core;

use Patylook\Models\Flash;
use Patylook\Models\User;

/** The root object every page template receives. */
final class PageData
{
    /**
     * @param array<string, string> $errors field errors; the "" key holds form-level messages
     * @param array<string, mixed> $data page-specific values
     */
    public function __construct(
        public string $title,
        public string $active = '', // navigation key of the current section
        public ?User $user = null,
        public string $csrf = '',
        public ?Flash $flash = null,
        public array $errors = [],
        public array $data = [],
    ) {
    }
}

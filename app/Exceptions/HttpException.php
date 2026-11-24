<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

/** A failure that must be answered with a specific HTTP status and error page. */
final class HttpException extends AppException
{
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly string $detail,
    ) {
        parent::__construct($title);
    }
}

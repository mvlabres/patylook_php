<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

/** A unique-constraint violation on a specific field. */
final class ConflictException extends AppException
{
    public function __construct(public readonly string $field = '')
    {
        parent::__construct('registro duplicado ou em conflito' . ($field !== '' ? ': ' . $field : ''));
    }
}

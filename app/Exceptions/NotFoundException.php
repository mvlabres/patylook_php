<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class NotFoundException extends AppException
{
    public function __construct(string $message = 'registro não encontrado')
    {
        parent::__construct($message);
    }
}

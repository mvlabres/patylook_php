<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class ForbiddenException extends AppException
{
    public function __construct()
    {
        parent::__construct('acesso negado');
    }
}

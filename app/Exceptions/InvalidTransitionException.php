<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class InvalidTransitionException extends AppException
{
    public function __construct()
    {
        parent::__construct('transição de status inválida');
    }
}

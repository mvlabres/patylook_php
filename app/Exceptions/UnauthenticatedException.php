<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class UnauthenticatedException extends AppException
{
    public function __construct()
    {
        parent::__construct('autenticação necessária');
    }
}

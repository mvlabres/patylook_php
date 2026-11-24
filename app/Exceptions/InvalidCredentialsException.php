<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class InvalidCredentialsException extends AppException
{
    public function __construct()
    {
        parent::__construct('usuário ou senha inválidos');
    }
}

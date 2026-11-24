<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class TooManyAttemptsException extends AppException
{
    public function __construct()
    {
        parent::__construct('muitas tentativas, tente novamente mais tarde');
    }
}

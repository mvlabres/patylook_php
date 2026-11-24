<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class InUseException extends AppException
{
    public function __construct()
    {
        parent::__construct('registro em uso por outros dados');
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

final class InsufficientStockException extends AppException
{
    public function __construct()
    {
        parent::__construct('estoque insuficiente');
    }
}

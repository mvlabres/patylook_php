<?php

declare(strict_types=1);

namespace Patylook\Models;

enum FlashKind: string
{
    case Success = 'success';
    case Error = 'error';
}
